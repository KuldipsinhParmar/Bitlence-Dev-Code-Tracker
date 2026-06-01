=== Bitlence Dev Code Tracker ===
Contributors:      kuldip8213
Tags:              time tracking, developer, admin, productivity, dashboard
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      8.0
Stable tag:        1.2.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Track time spent in wp-admin per page/post with idle detection, a 30-day chart, streak tracker, and per-page breakdown.

== Description ==

Ever wonder how many hours you actually spend inside WordPress admin? Bitlence Dev Code Tracker answers that question. It silently monitors your active time on every wp-admin screen — with smart idle detection, per-page breakdowns, and a streak tracker to keep you motivated.

Built for WordPress developers, agencies, and power users who want visibility into where their time goes — without leaving wp-admin.

**Features**

* Tracks active time on every wp-admin screen
* Idle detection — session ends automatically after configurable timeout
* Per-post/page breakdown with post title, post type, and time spent
* Today / This Week / All Time stat cards
* 30-day bar chart
* Streak tracker (consecutive active days)
* Dashboard widget showing today's total at a glance
* Admin toolbar live timer with floating badge in page builders
* Multi-user support — each user's data is tracked separately
* Configurable: idle timeout, minimum session length, which roles are tracked
* localStorage queue — sessions are buffered locally and retried if a request fails
* Universal page builder support — Elementor, Oxygen, Beaver Builder, Divi, WPBakery, Brizy, Thrive Architect, Bricks, Breakdance, SeedProd
* Clickable post titles in Sessions Log and Dashboard
* SPA navigation tracking (WooCommerce Analytics, React/Vue-based admin plugins)

**Admin menu structure**

`Dev Code Tracker → Dashboard`
`Dev Code Tracker → Sessions Log`
`Dev Code Tracker → Settings`

== Installation ==

1. Upload the `bitlence-dev-code-tracker` folder to `/wp-content/plugins/` or install directly from the WordPress plugin directory
2. Activate the plugin via **Plugins → Installed Plugins**
3. The DB tables are created automatically on activation
4. Visit **Dev Code Tracker → Dashboard** to see your stats

== Frequently Asked Questions ==

= Does it track front-end page views? =
Only when a supported frontend page builder is active (Oxygen, Beaver Builder, Divi Visual Builder, WPBakery Frontend, Brizy, Thrive Architect, SeedProd). Regular frontend browsing is not tracked.

= Does it slow down my site? =
No. The tracker runs only in wp-admin for logged-in users. It has no impact on your front-end or visitor experience.

= Where is the data stored? =
All data is stored locally in your WordPress database. Nothing is sent to any external server.

= Can I export my data? =
Yes — go to **Dev Code Tracker → Sessions Log** and click **Export CSV**. The export respects any active date filter, so you can export a specific date range or all sessions.

= Does it work with multisite? =
It creates per-site tables; multisite is not officially tested yet.

= What happens to my data if I deactivate the plugin? =
Data is preserved. Tables are only removed when you **delete** the plugin.

= Can I track only certain user roles? =
Yes — go to **Dev Code Tracker → Settings** and choose which roles are tracked.

== Screenshots ==

1. Dashboard — Today / This Week / All Time stat cards, 30-day activity bar chart, streak tracker, and per-page/post time breakdown
2. Sessions Log — paginated session history with date range filter and CSV export
3. Settings — configure idle timeout, minimum session length, and which user roles are tracked
4. Admin toolbar — live session timer shown in the top bar while browsing wp-admin

== Changelog ==

= 1.2.0 =
* Added: universal frontend page-builder support — Oxygen, Beaver Builder, Divi Visual Builder, WPBakery Frontend, Brizy, Thrive Architect, SeedProd
* Added: clickable post titles in Sessions Log and Dashboard link to the post edit screen
* Added: human-readable labels for admin pages (bdct-settings → "DCT Settings", etc.)
* Added: floating timer badge in any builder that hides the WP admin bar
* Fixed: Elementor editor time not tracked — self-contained script registration and printing
* Fixed: post type missing in sessions — resolved server-side via get_post_type() when JS cannot read typenow
* Fixed: iframe activity lost after editing multiple elements — document reference tracking replaces boolean flag
* Fixed: old sessions with null post_type backfilled on upgrade
* Changed: session starts on first user interaction instead of immediately on page load
* Changed: auto-checkpoint interval raised from 5 to 30 minutes — less noise in the Sessions Log
* Changed: minimum session length default lowered from 60 s to 30 s

= 1.1.1 =
* Added: date range filter on the By Page / Post breakdown
* Added: CSV export on the Sessions Log page
* Added: Sessions Log pagination (50 per page)
* Added: streak at-risk warning when no activity recorded today
* Fixed: save_session endpoint now enforces tracked-role check before writing to the DB
* Fixed: session duration recomputed server-side from timestamps — client value no longer trusted
* Fixed: toolbar timer no longer flashes "0:00" on page load
* Fixed: dashboard auto-refresh skips when tab is hidden
* Fixed: localStorage queue cleared before dispatching to prevent duplicate sends
* Changed: today/week/all-time stats now fetched in a single DB query instead of three
* Removed: unused projects table and AJAX endpoints (automatically cleaned up on upgrade)

= 1.0.0 =
* Initial release
* Session tracking with idle detection
* Per-page/post breakdown with post title lookup
* 30-day bar chart, streak tracker, dashboard widget
* localStorage pending queue for reliability
* Dates displayed as dd-mm-yyyy throughout
* Elementor editor support
* SPA navigation tracking

== Upgrade Notice ==

= 1.2.0 =
New: universal page builder tracking (Oxygen, Beaver Builder, Divi Visual Builder, WPBakery Frontend, Brizy, Thrive Architect, SeedProd). Existing sessions with missing post types are backfilled automatically on upgrade.

= 1.1.1 =
Minor internal cleanup — removes an unused database table created in 1.0.0. No data loss.

= 1.0.0 =
Initial release.
