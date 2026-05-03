# SwiNOG Events

Manage SwiNOG (Swiss Network Operators Group) presentations and sponsors –
cleanly, with a modern code base and a fully-automatic migration from the
legacy `swinog_events` plugin (v0.x). Event pages live as regular WordPress
pages that embed the agenda / presentation / sponsor shortcodes; events
themselves are organised via the `stgl_presentation_cat` taxonomy (one term
per SwiNOG, e.g. `swinog-41`).

* **Version:** 1.0.3
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

Optional attributes accepted by all four: `orderby`, `order`, `meta_key`,
`posts`. The full attribute reference and worked examples live on the
**Presentations → Settings** screen.

The presentation and sponsor CPTs remain registered with `show_in_rest`,
so the standard `/wp-json/wp/v2/stgl_presentation` and `/wp-json/wp/v2/stgl_sponsor`
endpoints are available if you need raw data – custom meta fields are
exposed with proper types.

## New meta fields

These are **additive** – they default to empty for legacy posts.

**Presentations**

* `stgl_presenter_bio`.
* `stgl_presenter_twitter`, `stgl_presenter_linkedin`.

## Settings

**Presentations → Settings** – add, rename or remove sponsor tiers, and find
the full shortcode help (attribute reference + worked examples).
Existing tiers are preserved.

## Updates

This plugin is not on the WordPress.org directory; updates are delivered from
GitHub releases via [Git Updater](https://git-updater.com/).

### One-time setup on each WordPress site

1. Install and activate the **Git Updater** plugin (download the latest ZIP
   from <https://github.com/afragen/git-updater/releases> and upload via
   *Plugins → Add New → Upload Plugin*).
2. Git Updater discovers SwiNOG Events automatically thanks to the
   `GitHub Plugin URI: sglogger/wp-swinog-events` header in
   `swinog-events.php`. New tagged releases appear under
   *Dashboard → Updates*.
3. (Optional) Tick **Enable auto-updates** next to the plugin in
   *Plugins → Installed Plugins* to apply updates in the background.

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
