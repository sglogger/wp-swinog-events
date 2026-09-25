# SwiNOG Events

Manage SwiNOG (Swiss Network Operators Group) presentations and sponsors –
cleanly, with a modern code base and a fully-automatic migration from the
legacy `swinog_events` plugin (v0.x). Event pages live as regular WordPress
pages that embed the agenda / presentation / sponsor shortcodes; events
themselves are organised via the `stgl_presentation_cat` taxonomy (one term
per SwiNOG, e.g. `swinog-41`).

* **Version:** 1.2.0
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
* New `[swinog_list_agenda]` shortcode and a legacy `[stgl_list_presentations]` alias.

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
4. Visit any presentation or sponsor – everything renders, every
   meta field is preserved, the URL slugs stay the same
   (`/presentations/`, `/sponsors/`).
5. The `stgl_event` post type from v0.x is no longer registered – any
   existing event posts remain in the database (so you don't lose data)
   but stop appearing in the admin menu. Build event landing pages as
   regular WP pages and embed the shortcodes below.
6. Once you're happy, you can delete the old `swinog_events` directory.

> **Backup first.** Even though the migration is non-destructive, a database
> backup before any plugin swap is always a good idea.

## What's preserved (backward compatibility)

| Area | v0.x | v1.0 |
|------|------|------|
| Post type for talks | `stgl_presentation` | identical |
| Post type for sponsors | `stgl_sponsor` | identical |
| Taxonomy | `stgl_presentation_cat` | identical |
| URL slugs | `presentations`, `sponsors` | identical |
| All meta keys | preserved (incl. typo `stgl_presenter_lenght`) | identical |
| Shortcodes | `[swinog_list_presentations]`, `[swinog_list_agenda]`, `[swinog_sponsor]` | identical plus a legacy `[stgl_list_presentations]` alias |
| Sponsor levels option | `stgl_swinog_sponsor_levels` | identical shape, now editable |
| `stgl_event` post type | registered | **removed** – use a regular WP page with the shortcodes |

## Shortcodes

Use a regular WordPress page (one per SwiNOG) and embed the shortcodes below.
Each shortcode filters by the `stgl_presentation_cat` taxonomy slug
(`event="swinog-NN"`).

| Shortcode | Description |
|-----------|-------------|
| `[swinog_list_presentations event="swinog-41"]` | Presentations with slides/video links, no time column. |
| `[stgl_list_presentations event="swinog-41"]` *(legacy)* | Backwards-compatible alias for `[swinog_list_presentations]`. |
| `[swinog_list_agenda event="swinog-41"]` | Agenda with time slot and talk abstract, no slide/video links. |
| `[swinog_sponsor event="swinog-41" layout="tiers"]` | Sponsor grid grouped by level (use `layout="list"` for a flat grid). |
| `[swinog_list_all_events]` | Lists all event pages — i.e. the child pages of the current page. Drop on the parent "Events" page to auto-enumerate every SwiNOG. Optional `parent="<id>"` to list children of an arbitrary page. |
| `[swinog_list_speaker_lineup event="swinog-42"]` | Speaker cards (name, company, talk title) pulled from the CFP tool. `event` is the **CFP event slug**, not the event category. Lists submissions with status `accepted`, `needs_changes`, `scheduled` or `presented`, sorted by last name. Needs the API key (see [CFP sync](#cfp-sync)); cached 15 minutes, and the last good result is shown if the CFP tool is down. Optional `title="…"`. |

Optional attributes accepted by all four: `orderby`, `order`, `meta_key`,
`posts`. The presentation/agenda shortcodes additionally accept `show_type`
(`1`/`0`) to show or hide the **Type** column – on by default for
`[swinog_list_agenda]`, off for `[swinog_list_presentations]`. The full
attribute reference and worked examples live on the **Presentations →
Settings** screen.

The presentation and sponsor CPTs remain registered with `show_in_rest`,
so the standard `/wp-json/wp/v2/stgl_presentation` and `/wp-json/wp/v2/stgl_sponsor`
endpoints are available if you need raw data – custom meta fields are
exposed with proper types.

## Agenda entry types

Every presentation is classified in the timetable – `Talk`, `Break`,
`Keynote`, `Transportation`, `Social` or `Other`. Entries are talks unless
the type is overwritten:

* **Per entry:** the **Type** dropdown at the top of *SwiNOG Presentation
  Details*. Leaving it on *— default —* stores nothing and renders as
  `Talk`, so legacy presentations keep working untouched.
* **The list itself:** *Presentations → Settings* → **Presentation types**.
  Rename, delete (clear the label) or add types; an empty slug is derived
  from the label. Stored on the `stgl_swinog_presentation_types` option as
  `slug => label`.

The agenda renders the label as a badge in a `Type` column, and tags the row
with `stgl-row-type-{slug}` (badge: `stgl-type-{slug}`) so a theme can style
breaks and social slots differently from talks.

If a type is removed from the settings while presentations still use it, the
stored slug is kept and shown with a prettified label rather than being
silently relabelled.

## New meta fields

These are **additive** – they default to empty for legacy posts.

**Presentations**

* `stgl_presenter_bio`.
* `stgl_presenter_twitter`, `stgl_presenter_linkedin`.
* `stgl_presenter_type` – agenda entry type slug; empty means "default"
  (`talk`).

## CFP sync

The agenda can be imported from the SwiNOG CFP tool
(<https://cfp.swinog.ch>).

**Presentations → Settings → API Settings**

* **CFP server URL** – e.g. `https://cfp.swinog.ch` (a trailing `/api` or
  `/api/v1` is stripped).
* **API key** – a CFP API token (`swcfp_…`) with the `read-internal` scope,
  sent as `Authorization: Bearer …`. It is required, because
  only the admin slot list (`/api/v1/admin/events/{id}/slots`) carries
  presenter e-mail, consents and video URL. The stored key is never shown
  again; leave the field empty to keep it.
* **Slot type mapping** – the presentation type each CFP `slot_type` gets.
  Defaults: presentation → Talk, break → Break, social → Social,
  housekeeping/custom → Other.

**Presentations → CFP Sync Tool**

1. Pick the CFP event.
2. Map every event day to an event category. A two-day event such as
   SwiNOG-42 (2026-10-20 to 2026-10-21) has day 1 → `SwiNOG #42-1` and
   day 2 → `SwiNOG #42-2`; a one-day event maps to e.g. `SwiNOG #41`. These
   are guessed from the names and remembered per event
   (`stgl_swinog_cfp_event_map`). Days set to "skip" are left alone.
3. The dry run lists, per day, the presentations that go to the trash
   (entries with an attached slide file are flagged – files are not carried
   over) and the slots that get imported.
4. Tick the confirmation and import. If a mapped category changed after the
   dry run, nothing is done and the dry run has to be repeated.

Field mapping:

| Presentation field | CFP slot field |
|---|---|
| Title | `title` |
| Text | `abstract` |
| Type | `slot_type` (via the mapping) |
| Presenter name | `presenter_name` |
| Company | `presenter_organization` |
| Presenter e-mail | `presenter_email` |
| Publish presentation | `consent_publish_presentation` |
| Video URL | `video_url` |
| Publish video | `consent_publish_videos` |
| Schedule | time of `starts_at` |
| Length (minutes) | `duration_minutes` |
| Speaker bio | left empty |
| Event category | day mapping |

Imported entries are published immediately and keep the CFP slot and
submission id in `_stgl_cfp_slot_id` / `_stgl_cfp_submission_id`. Sponsors
share the event taxonomy but are never deleted by the sync.

## Settings

**Presentations → Settings** – add, rename or remove sponsor tiers and
presentation types, and find the full shortcode help (attribute reference +
worked examples). Existing tiers and types are preserved. The **API
Settings** tab holds the CFP connection (see [CFP sync](#cfp-sync)).

## Updates

This plugin is not on the WordPress.org directory; updates are delivered
straight from GitHub releases by a built-in updater
([`includes/class-updater.php`](includes/class-updater.php)). **No helper
plugin (Git Updater etc.) is required.**

### One-time setup on each WordPress site

None. The bundled updater checks
`github.com/sglogger/wp-swinog-events` for new releases on WordPress's normal
update schedule, so new tagged versions simply appear under
*Dashboard → Updates*. (Optional) tick **Enable auto-updates** next to the
plugin in *Plugins → Installed Plugins* to apply them in the background.

For a private repository, supply a token via the `STGL_SWINOG_GITHUB_TOKEN`
constant (e.g. in `wp-config.php`) or the `stgl_swinog_github_token` filter.

### Releasing a new version (maintainer)

The repo ships a single GitHub Actions workflow at
[`.github/workflows/auto-tag.yml`](.github/workflows/auto-tag.yml) that runs on
every push to `main` touching `swinog-events.php`. It reads the version from
the plugin file, creates the matching `vX.Y.Z` tag if it doesn't yet exist,
builds a clean ZIP and creates/updates the GitHub release.

```sh
# 1. Bump the version in two places (must match):
#    - swinog-events.php  →  Version:           1.0.4
#    - swinog-events.php  →  define('STGL_SWINOG_VERSION', '1.0.4');
# 2. Commit & push – the workflow does the rest.
git commit -am "release v1.0.4"
git push origin main
```

The workflow fails fast if the `Version:` header and the
`STGL_SWINOG_VERSION` constant are out of sync, so a typo never ships. You
can also re-run the workflow manually from the **Actions** tab
(`workflow_dispatch`) to rebuild and re-upload the ZIP for the current
version without bumping it.

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
│   ├── class-assets.php        Conditional CSS/JS enqueue
│   └── helpers.php             Email validator + BC global function shims
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
