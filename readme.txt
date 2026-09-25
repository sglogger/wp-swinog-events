=== SwiNOG Events ===
Contributors: glogger
Tags: events, agenda, sponsors, presentations, ical, rest-api
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage SwiNOG events, agendas, sponsors and presentations with shortcodes, REST API, iCal export and JSON-LD structured data.

== Description ==

SwiNOG Events is a complete rewrite of the legacy `swinog_events` plugin (0.x). It manages three custom post types — Events, Presentations and Sponsors — and ties them together via a category taxonomy so each event has its own agenda and sponsor list.

**Features**

* Three custom post types: events, presentations (talks), sponsors.
* Taxonomy-based grouping per event.
* Shortcodes for agenda table, sponsor wall, event metadata.
* New: shortcodes for event cards, upcoming events list and CFP banner.
* New: REST API namespace `swinog/v1` for events, agenda and sponsors.
* New: iCal (`text/calendar`) export per event.
* New: JSON-LD `Event` structured data on single event pages.
* Editable sponsor tiers from a dedicated Settings page.
* Agenda entry types (Talk, Break, Keynote, Transportation, Social, Other) –
  editable list, overwritable per presentation, shown as a Type column.
* CFP Sync Tool: imports the agenda of an event from the SwiNOG CFP tool,
  one event category per event day, with a dry run before anything changes.
* Modern Media Library picker for slide / video file uploads.
* PHP 7.4+ codebase with strict types, namespaced classes, escaped output.

**Backward compatibility**

* All v0.x meta keys are preserved, including the historical typo
  `stgl_presenter_lenght`.
* Post type slugs and rewrite rules are unchanged
  (`/presentations/`, `/meeting/`, `/sponsors/`).
* Existing shortcodes keep working unchanged.
* On activation an idempotent migration runs to drop the unused
  `{prefix}swinog_events` table, normalise legacy boolean meta values
  and backfill the missing `stgl_swinog_event_levels` option.

== Installation ==

1. Deactivate the old `swinog_events` plugin if installed.
2. Upload `swinog-events` to `/wp-content/plugins/`.
3. Activate it from **Plugins**. The migration runs automatically.
4. Configure sponsor tiers and presentation types under
   **Presentations → Settings**.

== Frequently Asked Questions ==

= Will my old shortcodes break? =

No. `[swinog_list_presentations]`, `[swinog_sponsor]` and `[swinog_event]`
behave the same as before – plus new ones including `[swinog_list_agenda]`
and `[swinog_list_all_events]`.

= How do I export an event as iCal? =

Append `?stgl_ical={event-slug}` to your site URL. The
`[swinog_event_card]` shortcode renders a button automatically.

= Can I roll back to 0.x? =

Yes. The migration only adds and normalises data. Your old plugin
folder will keep working if you reactivate it, although new fields and
the Settings page will not be available there.

== Changelog ==

= 1.1.0 =
* Added: **Presentations → Settings → API Settings** tab with the CFP server
  URL, an API key (sent as `Authorization: Bearer …`) and a mapping from CFP
  `slot_type` (presentation, break, social, housekeeping, custom) to
  presentation types. "Save & test connection" checks the server and key.
* Added: **Presentations → CFP Sync Tool**. Pick a CFP event, map each event
  day to an event category (e.g. day 2 of SwiNOG-42 → "SwiNOG #42-2"; guessed
  automatically from the names), review a dry run table and confirm. All
  presentations of the mapped categories go to the trash and are replaced by
  the CFP slots. Sponsors are never touched.
* The import refuses to run if a mapped category changed after the dry run.
* Imported presentations store the CFP slot and submission id in
  `_stgl_cfp_slot_id` / `_stgl_cfp_submission_id`.

= 1.0.10 =
* Added: agenda entry types. Every presentation now carries a type
  (Talk, Break, Keynote, Transportation, Social, Other) that can be
  overwritten per entry via a dropdown in **SwiNOG Presentation Details**.
  Entries left on "default" are shown as *Talk*, so existing content keeps
  rendering unchanged.
* Added: the type list is editable under **Presentations → Settings** –
  rename, remove or add your own types (leave the slug empty and it is
  derived from the label).
* Added: `[swinog_list_agenda]` renders a **Type** column; the row gets a
  `stgl-row-type-{slug}` class and the badge a `stgl-type-{slug}` class for
  theming. Use `show_type="0"` to hide it, or `show_type="1"` to switch it
  on for `[swinog_list_presentations]`.
* Added: sortable **Type** column on the presentations admin list.
* New meta key `stgl_presenter_type` (exposed via REST, empty = default).

= 1.0.9 =
* Fixed: the "View details" modal was nearly empty after the move to the
  built-in updater. It now parses the bundled readme.txt to populate the
  Description, Installation, FAQ and full Changelog tabs, the contributor
  list, and the correct "Tested up to" value (previously it showed the
  running WordPress version).

= 1.0.8 =
* Changed: updates are now served by a built-in GitHub Releases updater
  (`includes/class-updater.php`). The Git Updater plugin is no longer
  required — its headers have been removed from `swinog-events.php`.
  The updater caches its GitHub lookup and supports private repos via a
  token constant/filter.

= 1.0.7 =
* Fixed: editing presentation metadata no longer wipes the linked PDF /
  slide deck for posts whose file lives in the legacy
  `wp_custom_attachment` meta. The save handler now only deletes the
  file reference when the user clicks "Remove file"; an empty picker
  field means "no change".
* Improved: presentation/agenda abstracts are no longer truncated to
  40 words – the full abstract is rendered.
* Improved: abstract formatting saved in the editor (bold, italic,
  links, paragraphs, lists, …) is preserved on the agenda and
  presentations list. Output is sanitised via `wp_kses_post`.

= 1.0.6 =
* Added `[swinog_list_all_events]` shortcode to list all event pages
  (child pages of the current page) on a parent "Events" landing page.
  Optional `parent="<id>"` to list children of an arbitrary page.
* Updated admin Settings/help screen and README to document the new shortcode.

= 1.0.5 =
* Release/CI: merged `release.yml` into `auto-tag.yml` so tagging and
  ZIP-build/release happen in a single workflow run, fixing the case
  where bot-pushed tags never triggered the release job.
* Build & release steps no longer skip when the tag already exists –
  re-running rebuilds and re-uploads the ZIP.
* Added `workflow_dispatch` so the release can be triggered manually
  from the Actions tab.
* Bumped `actions/checkout` to v5 (clears Node 20 deprecation warning).

= 1.0.4 =
* Documentation: README release instructions updated to reflect the new
  single-workflow release flow (push to `main` → auto-tag → ZIP).

= 1.0.3 =
* Sponsors: fixed `.stgl-sponsor-tier` heading styling – the typography
  rule (`font-size`, `font-weight`, `line-height`) was scoped to a
  descendant `h3` that never existed in the rendered markup.
* Internal: validated end-to-end auto-commit / auto-tag workflow.

= 1.0.2 =
* Release tooling: added `Release Asset: true` and Git Updater headers
  to `swinog-events.php` so updates flow via Git Updater from GitHub
  releases.

= 1.0.1 =
* Switched update channel to Git Updater (GitHub releases) instead of
  manual ZIP installs.

= 1.0.0 =
* Full rewrite: namespaced classes, PHP 7.4+, strict types.
* Added REST API, iCal export, JSON-LD schema.
* Added shortcodes: `swinog_event_card`, `swinog_upcoming_events`, `swinog_cfp`.
* Added Settings page for sponsor tiers.
* Added new meta fields for CFP, multi-day events, presenter bio/social.
* Switched file uploads to Media Library, with v0.x compatibility shim.
* Fixed PHP 7+ incompatibilities (`ereg`, `split`).
* Fixed XSS in admin and shortcode output (full escaping).
* Fixed missing-include fatal errors from 0.x.
* Fixed copy-paste bug in `[swinog_event]` heading.
* Fixed asset enqueue (now hooked into `wp_enqueue_scripts`, conditional).
* Removed unused `{prefix}swinog_events` database table.

== Upgrade Notice ==

= 1.1.0 =
Adds the CFP Sync Tool and API settings for importing the agenda from the
SwiNOG CFP tool. Nothing changes until you run a sync and confirm the dry run.

= 1.0.10 =
Adds classifiable agenda entries (Talk, Break, Keynote, Transportation,
Social, Other) with an editable type list and a per-presentation override.
Existing presentations default to "Talk"; nothing needs to be re-entered.

= 1.0.9 =
Restores the full plugin "View details" popup (description, changelog,
FAQ) that was missing in 1.0.8.

= 1.0.8 =
Updates now come directly from GitHub — you can uninstall the Git Updater
plugin after upgrading. No other site-side changes required.

= 1.0.7 =
Fixes a regression that wiped legacy PDF/slide links on metadata
saves, and renders full, formatted abstracts in the agenda/presentation
shortcodes.

= 1.0.6 =
Adds `[swinog_list_all_events]` for auto-listing event child pages.

= 1.0.5 =
Release pipeline fix – tagging and ZIP-publishing now run in a single
workflow. No site-side changes required.

= 1.0.0 =
Drop-in upgrade from 0.x. Deactivate the old plugin, install this one,
activate – the migration runs automatically and your data is preserved.
