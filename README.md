# SwiNOG Events

Manage SwiNOG (Swiss Network Operators Group) events, call-for-papers, agendas,
sponsors and presentation slides – cleanly, with a modern code base and a
fully-automatic migration from the legacy `swinog_events` plugin (v0.x).

* **Version:** 1.0.0
* **Requires WordPress:** 6.0+
* **Requires PHP:** 7.4+
* **License:** GPL-2.0-or-later
* **Author:** Steven Glogger – <https://www.glogger.ch>

---

## Why a rewrite?

The original plugin (`swinog_events` 0.7) shipped a number of issues that have
been fixed here without breaking any existing data. See `MIGRATION.md` for
the bug-by-bug list. In short:

* PHP 7+ compatibility (no more `ereg`/`split`).
* Proper escaping (XSS hardening) on every output.
* All the missing `require` files that would fatal on load are gone –
  the plugin now uses a PSR-style class loader.
* Sponsor levels can finally be edited from the WordPress admin.
* The unused `{prefix}swinog_events` table is dropped on upgrade.
* New shortcodes, REST API, iCal export and JSON-LD structured data.

## Drop-in upgrade from 0.x

1. **Deactivate** the old `swinog_events` plugin (do **not** delete it yet
   if you want a rollback option).
2. Upload `swinog-events.zip` and activate it.
3. The first activation runs an idempotent migration:
    * Backfills the missing `stgl_swinog_event_levels` option from your
      existing data so old shortcodes keep rendering.
    * Drops the empty legacy `{$wpdb->prefix}swinog_events` table.
    * Normalises legacy boolean meta values (`"true"`, `"on"`, `"yes"` → `"1"`).
    * Stores a `stgl_swinog_data_version` flag so it never runs twice.
4. Visit any presentation / event / sponsor – everything renders, every
   meta field is preserved, every URL slug stays the same
   (`/presentations/`, `/meeting/`, `/sponsors/`).
5. Once you're happy, you can delete the old `swinog_events` directory.

> **Backup first.** Even though the migration is non-destructive, a database
> backup before any plugin swap is always a good idea.

## What's preserved (backward compatibility)

| Area | v0.x | v1.0 |
|------|------|------|
| Post type for talks | `stgl_presentation` | identical |
| Post type for events | `stgl_event` | identical |
| Post type for sponsors | `stgl_sponsor` | identical |
| Taxonomy | `stgl_presentation_cat` | identical |
| URL slugs | `presentations`, `meeting`, `sponsors` | identical |
| All meta keys | preserved (incl. typo `stgl_presenter_lenght`) | identical |
| Shortcodes | `[swinog_list_presentations]`, `[swinog_sponsor]`, `[swinog_event]` | identical |
| Sponsor levels option | `stgl_swinog_sponsor_levels` | identical shape, now editable |

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[swinog_list_presentations cat="swinog-37"]` | Renders the agenda table for an event (taxonomy term slug). |
| `[swinog_sponsor cat="swinog-37"]` | Renders sponsors grouped by tier. |
| `[swinog_event cat="swinog-37"]` | Renders the event metadata block (location, date, registration). |
| `[swinog_event_card slug="swinog-37"]` *(new)* | Compact card with date / location / CFP / registration / iCal button. |
| `[swinog_upcoming_events posts="3"]` *(new)* | Lists upcoming events filtered by `stgl_event_date >= today`. |
| `[swinog_cfp]` *(new)* | Renders a CFP banner for any event whose `stgl_event_cfp_open` is true. |

## REST API

Namespace: `swinog/v1`

| Endpoint | Description |
|----------|-------------|
| `GET /wp-json/swinog/v1/events` | List events (`?upcoming=1` to filter). |
| `GET /wp-json/swinog/v1/events/{slug}` | Single event with metadata. |
| `GET /wp-json/swinog/v1/events/{slug}/agenda` | Talks for an event, ordered by start time. |
| `GET /wp-json/swinog/v1/events/{slug}/sponsors` | Sponsors for an event, grouped by tier. |

All custom post types also expose the standard core REST routes
(`/wp-json/wp/v2/stgl_event`, etc.) because they're registered with
`show_in_rest`. Custom meta fields are exposed with proper types.

## iCal export

Each event has a stable iCal URL:

```
https://example.com/?stgl_ical={event-slug}
```

The output is a UTF-8 ICS file (`text/calendar`) containing one `VEVENT`
per talk, RFC 5545 line-folded at 73 octets and properly escaped.
Use it from the `[swinog_event_card]` shortcode or link it directly.

## SEO – Schema.org

When a single `stgl_event` page is rendered, the plugin injects a
`<script type="application/ld+json">` block following the `Event`
schema, including `startDate`, `endDate`, `location.name`,
`organizer` and the registration URL as a `Reservation` action.

## New meta fields

These are **additive** – they default to empty for legacy posts.

**Events**

* `stgl_event_end_date` – multi-day support.
* `stgl_event_cfp_open` (bool) + `stgl_event_cfp_url`.
* `stgl_event_max_seats`.
* `stgl_event_participants_url`.
* `stgl_event_venue_lat` / `stgl_event_venue_lng`.

**Presentations**

* `stgl_presenter_bio`.
* `stgl_presenter_twitter`, `stgl_presenter_linkedin`.

## Settings

**Settings → SwiNOG Events** – add, rename or remove sponsor tiers.
Existing tiers are preserved.

## Development

```
swinog-events/
├── swinog-events.php           Bootstrap (constants, autoload, activation hook)
├── uninstall.php               Delegates to Installer::uninstall()
├── includes/
│   ├── class-plugin.php        Singleton boot
│   ├── class-installer.php     Activation, migration, uninstall
│   ├── class-post-types.php    CPT + taxonomy + meta registration
│   ├── class-meta-boxes.php    Edit-screen UI + save handlers
│   ├── class-admin.php         List columns, settings page
│   ├── class-shortcodes.php    All public shortcodes
│   ├── class-rest-api.php      REST namespace + endpoints
│   ├── class-ical.php          ?stgl_ical=… ICS endpoint
│   ├── class-schema.php        JSON-LD output
│   ├── class-assets.php        Conditional CSS/JS enqueue
│   └── helpers.php             Date utilities + BC global function shims
├── admin/views/
├── public/views/
├── assets/
│   ├── css/{public,admin}.css
│   └── js/admin.js             Media Library picker
├── languages/                  Drop your .po/.mo here (textdomain: stgl)
├── LICENSE.txt                 GPL-2.0
├── README.md                   This file
├── readme.txt                  WordPress.org-style readme
└── MIGRATION.md                Bug-fix and migration notes
```

Coding conventions: `declare(strict_types=1)`, namespaced classes, PSR-style
file names. No global state apart from the `Plugin` singleton and a few
intentional global function shims for backward compatibility with old
template hacks.

## License

GPL-2.0-or-later. See `LICENSE.txt`.
