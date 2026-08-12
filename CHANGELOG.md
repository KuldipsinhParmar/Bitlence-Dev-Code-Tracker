# Changelog

All notable changes to Dev Code Tracker are documented here.

## [1.4.0] — 2026-08-12

### Added
- **Team Overview moved to its own admin page** (`Dev Code Tracker → Team Overview`, `bdct-team` slug, `manage_options`) — was a section on the Dashboard in 1.3.0. New `templates/team.php` + dedicated `assets/team.js` (the team-overview JS was extracted out of `dashboard.js`, which no longer loads or executes any team-related code)
- **Date-range filter + CSV export for Team Overview** — `BDCT_DB::get_team_summary()` now takes optional `$from`/`$to` and adds a `range_sec` column when either is set; new `BDCT_Admin::maybe_export_team_csv()` hooked on `admin_init`, mirroring the existing Sessions Log export pattern (`wp_nonce_url` + `check_admin_referer('bdct_export_team_csv')`)
- **Sessions Log "User" column is now unconditional** — shown to every viewer (not just admins); the user-filter dropdown and "view all users" capability remain `manage_options`-only for privacy
- PHPUnit test suite (`composer.json`, `phpunit.xml.dist`, `tests/`) via `wp-phpunit/wp-phpunit` + `johnpbloch/wordpress-core` (composer-only, no SVN/WP-CLI needed) against a real MySQL DB. `DbStreakTest` covers `BDCT_DB::get_streak()` (consecutive days, gaps, zero-second days, per-user isolation); `AjaxSaveSessionTest` covers `BDCT_Ajax::save_session()` (server-side duration recompute/cap, min-session skip, untracked-role rejection, malformed-datetime rejection). See `tests/README.md` for local setup

### Fixed
- **`BDCT_DB::insert_session()` returned the wrong session ID** — caught by the new test suite. `self::upsert_daily_summary()` runs an `INSERT ... ON DUPLICATE KEY UPDATE` on `bdct_daily_summary` right after the `bdct_time_sessions` insert, which overwrites `$wpdb->insert_id`; the method then returned that (unrelated) id instead of the session's own. Fixed by capturing `$wpdb->insert_id` immediately after the sessions insert, before the daily-summary upsert runs. The `bdct_save_session` AJAX response's `id` field was affected; nothing in the shipped JS currently consumes that id, so this had no visible symptom in the UI, but any future feature (or third-party integration) relying on it would have silently gotten the wrong row
- **`BDCT_Settings::idle_ms()`** floors the idle-timeout option at 1 minute — `0` could previously be saved, effectively breaking idle detection
- **Sessions Log filter form** now includes hidden `orderby`/`order` fields, so applying a date/user filter no longer silently resets the current sort column back to `started_at DESC`
- **`BDCT_DB::get_team_summary()` / `get_tracked_users()`** switched from `INNER JOIN` to `LEFT JOIN` on `$wpdb->users` (carried over from a same-day fix in 1.3.0 development) — a deleted user's historical time still counts toward team totals and still appears in the user filter, consistent with how the Sessions Log already handles deleted users via `COALESCE(..., 'Unknown')`

### Changed
- Extracted the duplicated `%dh %dm %ds` duration-formatting block (previously copy-pasted in `page_sessions()` and `maybe_export_csv()`) into `BDCT_Admin::format_duration()`, now shared by those two plus the new `maybe_export_team_csv()`
- `docker-compose.yml`: `db` service now maps port 3306 to host `3311` (needed for PHPUnit, which runs on the host, to reach the test database)
- Added `.gitattributes` (`export-ignore`) alongside the existing `.distignore`, and a `bin/build.sh` helper that uses `git archive` to produce a clean, dev-tooling-free copy of the plugin. Plugin Check (and any WP.org submission) should always be run against that clean build, never against the raw dev checkout — the dev checkout legitimately contains `composer.json`, `tests/`, `phpunit.xml.dist`, `vendor/`, etc., none of which ship
- `phpunit.xml.dist`: disabled PHPUnit's result cache (`cacheResult="false"`) so `.phpunit.result.cache` is never written to the working tree
- `class-db.php`: added `phpcs:disable`/`enable` blocks around `count_sessions()` and `get_team_summary()` for the same `PreparedSQL`/`PreparedSQLPlaceholders` sniffs already suppressed elsewhere in this file — both queries are safe (no user-controlled SQL fragments), but PHPCS's static analysis can't verify that through the conditional `$where_sql`/`$range_select` string-building, same as the pre-existing pattern in `get_sessions()`

## [1.3.0] — 2026-08-12

### Added
- **Team Overview** on the Dashboard — admin-only (`manage_options`) table showing today/week/all-time totals per user, backed by a new `BDCT_DB::get_team_summary()` query and `bdct_get_team_summary` AJAX action (capability-checked server-side, independent of the personal per-user dashboard data)
- **User filter + column on the Sessions Log** — admins get a `bdct_user` dropdown (default "All Users") populated from `BDCT_DB::get_tracked_users()`, plus a "User" column showing `display_name` via a `LEFT JOIN` on `$wpdb->users`; CSV export respects the same filter and adds the User column when exporting as an admin
- **`BDCT_DB::sessions_where()`** now accepts `user_id = 0` to mean "all users" — used by both the Sessions Log table and CSV export; non-admins are always forced server-side to their own `user_id`, ignoring any `bdct_user` query param, so the filter can't be used to view another user's data without `manage_options`

### Fixed
- **Bricks and Breakdance builder detection** — both were listed in the readme as supported frontend page builders, but `is_frontend_builder()` never checked for their query params (`?bricks`, `?breakdance`), so `frontend_enqueue_scripts()` and the admin-bar toolbar node never activated on those builders' canvases. Detection added for both.

## [1.2.0] — 2026-06-01

### Added
- **Universal frontend page-builder support** — Oxygen Builder, Beaver Builder, Divi Visual Builder, WPBakery Frontend Editor, Brizy, Thrive Architect, and SeedProd are now tracked via `wp_enqueue_scripts` with query-param detection (`?ct_builder`, `?fl_builder`, `?et_fb`, `?vc_action=vc_inline`, `?brizy-edit`, `?tve=1`, `?seedprod_page`)
- **Clickable post titles** in both the Sessions Log and Dashboard tables — titles now link directly to the post edit screen
- **Human-readable admin page labels** — raw slugs (`bdct-settings`, `edit`, `plugins`, `woocommerce`, etc.) are mapped to friendly names in both PHP (Sessions Log) and JS (Dashboard)
- **Floating timer badge** — a fixed-position badge appears in the bottom-right corner whenever the WP admin bar is hidden; covers Elementor, Bricks Builder, Breakdance, and any future builder that hides `#wpadminbar`
- **`postId` / `postType` in JS config** — frontend enqueue passes the current post ID and type via `bdctConfig` so frontend-builder sessions are attributed to the correct post

### Fixed
- **Elementor time not tracked** — `elementor/editor/footer` fires before `admin_enqueue_scripts`; `elementor_editor_footer()` is now fully self-contained (registers, enqueues, and force-prints the script in one step using `wp_scripts()->do_items(['bdct-tracker'], 1)`)
- **Post type not shown** — when JS cannot read `window.typenow` (Elementor and other custom admin templates), the server now resolves the post type via `get_post_type($post_id)` before inserting the session
- **Iframe activity lost after nth element edit** — `hookEditorIframes` previously used a boolean `_bdctHooked` flag; replaced with `_bdctHookedDoc` (stores the document reference) so re-hooks are triggered when the iframe navigates to a new document (e.g. when switching elements in Elementor)
- **Old sessions with null post_type** — a backfill `UPDATE … INNER JOIN posts` runs on plugin upgrade to populate missing post types from existing sessions
- **`NonEnqueuedScript` PHPCS error** — replaced direct `echo '<script src="...">'` in `elementor_editor_footer` with `wp_scripts()->do_items()`
- **`$_GET['order']` and `$_GET['paged']` not unslashed** — added `wp_unslash()` + `sanitize_text_field()` / `absint()` wrappers (PHPCS `MissingUnslash` warnings)
- **Stable tag mismatch** in `readme.txt` (was `1.1.1`, now kept in sync)
- **PHPCS warnings** in `class-db.php` — added `phpcs:ignore` / `phpcs:disable` for `PreparedSQL.InterpolatedNotPrepared`, `ReplacementsWrongNumber`, and `UnescapedDBParameter` on whitelisted internal SQL fragments; added `NoCaching` suppress on the schema-change `DROP TABLE` query

### Changed
- **Session starts on first interaction** — removed the immediate `startSession()` call on script load; sessions now begin on the first `mousemove`, `keydown`, or `click` event, eliminating ghost sessions from pages opened without user interaction
- **Auto-checkpoint interval** raised from 5 min → 30 min — reduces Sessions Log noise by 6× for long continuous work blocks; data-loss window remains acceptable (max 30 min on hard crash)
- **Minimum session length default** lowered from 60 s → 30 s — captures short focused edits that were previously silently discarded
- **Floating badge condition** changed from `#elementor-panel` presence to computed `display` of `#wpadminbar` — works generically for any builder without per-builder selectors
- **Dashboard "Last 10 Sessions"** `recent` query extended with a `LEFT JOIN posts` to include `page_label` (post title); "Admin Page" column renamed to "Page / Post" with human labels applied
- **`toolbar_item`** now registers the admin bar node on the frontend during builder sessions (previously only on `is_admin()` pages)

## [1.1.1] — 2026-05-31

### Added
- **Date range filter** on the By Page / Post breakdown — From / To date inputs filter the table without reloading the page
- **CSV export** on the Sessions Log page — downloads all sessions as a `.csv` file
- **Pagination** on the Sessions Log — 50 sessions per page with standard WP pagination links; total count displayed in the header
- **Streak at-risk warning** — shows "⚠ at risk today" under the streak counter when there is an active streak but no activity recorded today yet
- `assets/admin.css` stylesheet — all admin UI styles moved out of inline `style=""` attributes

### Fixed
- `save_session` AJAX endpoint now enforces tracked-role check — previously any logged-in user (including subscribers) could write sessions regardless of the Settings role list
- Session duration is now recomputed server-side from `started_at` / `ended_at` timestamps instead of trusting the client-supplied `duration_sec` value
- Admin toolbar timer no longer flashes "0:00" on page load — the time display is hidden until a session is active
- Dashboard auto-refresh skips when the browser tab is hidden, reducing unnecessary DB queries
- `flushQueue` clears localStorage before dispatching — prevents duplicate sends on concurrent tabs; failed fetch requests are still re-enqueued

### Changed
- `BDCT_DB::get_dashboard()` now runs one combined query for today / week / all-time totals instead of three separate queries (7 queries → 5)
- Sessions Log page query moved into `BDCT_DB::get_sessions()` — consistent with the rest of the data layer
- `tracker.js` checkpoint interval uses a named constant (`CHECKPOINT_MS`) instead of an inline magic number

### Removed
- `bdct_projects` database table and its AJAX handlers (`rename_project`, `delete_project`) — the projects concept was scaffolded but never surfaced in the UI; existing installs have the table dropped automatically on upgrade

## [1.0.0] — 2026-05-27

### Added
- Session tracking with idle detection (port of VS Code Dev Code Tracker extension)
- Per-page/post breakdown with post title lookup
- Today / This Week / All Time stat cards
- 30-day bar chart (Chart.js 4)
- Streak tracker (consecutive active days)
- Dashboard widget showing today's total
- Admin toolbar live timer
- Multi-user support — data isolated per user
- Configurable idle timeout, minimum session length, and tracked roles
- localStorage pending queue for session reliability
- `navigator.sendBeacon` for reliable saves on page unload
- Elementor editor support via `elementor/editor/footer` hook
- SPA navigation tracking — wraps `history.pushState` / `replaceState`
- Iframe keep-alive for page builders (Elementor, Divi, Beaver Builder, WPBakery)
- Auto-checkpoint every 5 minutes to prevent data loss on browser crash
- Sessions Log page with dd-mm-yyyy date format
- Uninstall routine — drops all tables and options on plugin deletion
- Blank `index.php` files in all subdirectories (WP.org security standard)
