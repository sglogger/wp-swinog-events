=== SwiNOG Events ===
Contributors: stevenglogger
Tags: events, agenda, sponsors, presentations, ical, rest-api
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
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
4. Configure sponsor tiers under **Settings → SwiNOG Events**.

== Frequently Asked Questions ==

= Will my old shortcodes break? =

No. `[swinog_list_presentations]`, `[swinog_sponsor]` and `[swinog_event]`
behave the same as before – plus three new ones.

= How do I export an event as iCal? =

Append `?stgl_ical={event-slug}` to your site URL. The
`[swinog_event_card]` shortcode renders a button automatically.

= Can I roll back to 0.x? =

Yes. The migration only adds and normalises data. Your old plugin
folder will keep working if you reactivate it, although new fields and
the Settings page will not be available there.

== Changelog ==

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

= 1.0.0 =
Drop-in upgrade from 0.x. Deactivate the old plugin, install this one,
activate – the migration runs automatically and your data is preserved.
