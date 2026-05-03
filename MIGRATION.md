# Migration & change log – `swinog_events` 0.7 → `swinog-events` 1.0.0

This document is for whoever maintains the SwiNOG website. It explains
**what was wrong**, **what was fixed**, **what was added**, and **what was
intentionally kept the same** so the existing site keeps working.

---

## 1. Bugs fixed

| # | Severity | v0.7 problem | Fix in v1.0 |
|---|----------|--------------|-------------|
| 1 | **Fatal** | `swinog_events.php` `require`s files that don't exist (`admin/admin-menu.php`, `admin/swinog_events-page.php`, `admin/swinog_test.php`, `includes/core-functions.php`, `includes/list_agenda.php`, `public/public-shortcode.php`, `includes/shortcode-subpages.php`). On any PHP version with `display_errors`, the plugin would white-screen on load. | Replaced ad-hoc `require` chain with a namespaced class loader (`Stgl\SwinogEvents\*`) booted from `Plugin::instance()->boot()` on `plugins_loaded`. |
| 2 | **Fatal on PHP 7+** | Uses `ereg()` and `split()` (removed in PHP 7.0). | Replaced with `preg_match()` / `preg_split()` / `explode()` as appropriate. |
| 3 | **Functional** | Code reads option `stgl_swinog_event_levels` which is **never written** anywhere – sponsor tiers therefore came from a hard-coded default array. | Added a Settings page under **Settings → SwiNOG Events** that writes this option. The installer also backfills it from defaults so existing pages keep rendering. |
| 4 | **Display** | `[swinog_event]` shortcode prints "Sponsors of …" as its heading (copy-paste from the sponsor shortcode). | Heading now reads "Event details" / event title. |
| 5 | **Display / WP-warning** | `wp_enqueue_style()` is called at file-load time, not on `wp_enqueue_scripts`. Triggers `_doing_it_wrong` notices and double-loads. | All asset enqueue moved into `Assets::enqueue_public()` / `enqueue_admin()`, hooked correctly and conditional on the page actually using a shortcode or CPT screen. |
| 6 | **Dead code** | A `{$wpdb->prefix}swinog_events` table is created on activation but never read or written. | Table is dropped during migration, no longer created. |
| 7 | **Fragility** | Submenu hooks are hard-coded by numeric index (`$submenu['…'][5]`). Breaks the moment WP changes its submenu order. | Replaced with proper `add_submenu_page()` / capability checks. |
| 8 | **Security – XSS** | Shortcode output mixes raw `$post->post_title`, raw meta values, raw `$_GET`/option values into HTML without escaping. | Every output is now escaped at the boundary with `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` (for the abstract field). |
| 9 | **Security – CSRF / notices** | Save handlers read `$_POST['stgl_…_nonce']` without `isset()` and trigger PHP notices; in some paths the nonce check is missing entirely. | All save handlers now `isset()`-guard, `wp_verify_nonce()`-check, `current_user_can()`-check, and skip during autosave / REST. |
| 10 | **Cleanup incomplete** | `uninstall.php` deletes a single option and leaves all post-meta and the unused table behind. | New `Installer::uninstall()` removes the option set, drops the legacy table, deletes plugin posts and their meta, and clears the data-version flag. |
| 11 | **Security – upload validation** | `wp_check_filetype()` validates by file extension only. | Switched to `media_handle_upload()` (Media Library) which uses `wp_check_filetype_and_ext()` (real type sniffing). |
| 12 | **Typo** | Meta key `stgl_presenter_lenght` (missing `t`). | **Intentionally preserved** to avoid breaking existing data. New code reads & writes the same key. |

## 2. Backward compatibility commitments

These are guaranteed not to break for anyone migrating from 0.x:

* Post type slugs: `stgl_presentation`, `stgl_event`, `stgl_sponsor`.
* Taxonomy: `stgl_presentation_cat`.
* URL rewrite slugs: `presentations`, `meeting`, `sponsors`.
* Shortcodes: `[swinog_list_presentations]`, `[swinog_sponsor]`, `[swinog_event]`.
* All meta keys, including the typo `stgl_presenter_lenght`.
* The `stgl_swinog_sponsor_levels` option keeps the same shape
  (level → label map). New options are additive.
* File uploads written before 1.0 (in `wp_custom_attachment` array meta)
  remain readable. New uploads use a Media Library reference
  (`_stgl_presentation_attachment_id`) and mirror the legacy array so
  any third-party theme template that reads `wp_custom_attachment`
  keeps working.

## 3. Migration steps performed on activation

`Installer::activate()` / `migrate_to_1_0()` is **idempotent** and
guarded by the `stgl_swinog_data_version` option. It will:

1. Register post types, taxonomies and meta (so `flush_rewrite_rules`
   sees them) and flush rewrite rules once.
2. Drop the unused `{$wpdb->prefix}swinog_events` table if present and
   empty.
3. If `stgl_swinog_event_levels` is missing, create it from the default
   tier list so existing sponsor shortcodes keep rendering.
4. Run a single SQL `UPDATE` on `wp_postmeta` to normalise the legacy
   boolean meta values `"true"`, `"on"`, `"yes"` → `"1"` for the
   `stgl_event_registration` and `stgl_event_cfp_open` keys.
5. Set `stgl_swinog_data_version = '1.0'` so the migration never re-runs.

## 4. New features

### Shortcodes

* `[swinog_event_card slug="swinog-37"]` – compact card with date,
  location, CFP/registration pills, and an iCal download button.
* `[swinog_upcoming_events posts="3"]` – upcoming-events list filtered
  by `stgl_event_date >= today`, sortable by date.
* `[swinog_cfp]` – renders a banner for any event whose
  `stgl_event_cfp_open` meta is true, linking to `stgl_event_cfp_url`.

### REST API – namespace `swinog/v1`

```
GET  /wp-json/swinog/v1/events            (?upcoming=1)
GET  /wp-json/swinog/v1/events/{slug}
GET  /wp-json/swinog/v1/events/{slug}/agenda
GET  /wp-json/swinog/v1/events/{slug}/sponsors
```

All custom post types also expose the standard
`/wp-json/wp/v2/stgl_event` etc. routes (`show_in_rest = true`) and
all meta fields are registered with `register_post_meta`, so they're
visible to the block editor and to the REST API by default.

### iCal export

`?stgl_ical={event-slug}` produces an RFC 5545-compliant ICS file
with:

* one `VEVENT` per talk (start/end derived from `stgl_event_date` +
  `stgl_presenter_time` + `stgl_presenter_lenght`),
* timezone-aware times via `wp_date()`,
* proper escaping of `\`, `,`, `;` and newlines per § 3.3.11,
* 73-octet line wrapping with leading-space continuation.

### Schema.org / SEO

Single event pages now include a JSON-LD `Event` block with
`startDate`, `endDate`, `location.name`, `organizer` and a
`potentialAction` for the registration URL.

### New meta fields (additive – default empty)

**Events**

* `stgl_event_end_date` – multi-day support.
* `stgl_event_cfp_open` (bool) + `stgl_event_cfp_url`.
* `stgl_event_max_seats`.
* `stgl_event_participants_url`.
* `stgl_event_venue_lat` / `stgl_event_venue_lng`.

**Presentations**

* `stgl_presenter_bio`.
* `stgl_presenter_twitter`, `stgl_presenter_linkedin`.

### Admin

* List columns for date / location / level on each post type, sortable.
* Settings page for sponsor tiers (formerly hard-coded).
* Media Library picker for attachments (proper file-type validation).
* All meta boxes use a single styled table with help text per field.

## 5. Suggested follow-up work

These didn't make it into 1.0 but are easy adds:

* **Block editor blocks** wrapping each shortcode (the underlying
  render functions are already pure callables).
* **CFP submission form** – a Gravity Forms / WPForms hook that creates
  a `stgl_presentation` draft tied to the open CFP event.
* **Per-event landing pages** that auto-pull agenda, sponsors and CFP
  banner from the taxonomy term, removing the need to author shortcodes
  by hand.
* **Email digest** of upcoming events (cron + WP_Mail).
* **Caching layer** for the REST endpoints (transients keyed by
  taxonomy term), useful once the public site grows.
