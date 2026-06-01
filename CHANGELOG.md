# Changelog

All notable changes to Dev Code Tracker are documented here.

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
