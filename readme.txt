=== Bitlence Dev Code Tracker ===
Contributors:      kuldip8213
Tags:              time tracking, developer, admin, productivity, dashboard
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Track time spent in wp-admin per page/post with idle detection, a 30-day chart, streak tracker, and per-page breakdown.

== Description ==

Bitlence Dev Code Tracker monitors how much time you spend on each WordPress admin page or post. It tracks developer activity inside wp-admin with idle detection, daily summaries, and per-page breakdowns.

**Features**

* Tracks active time on every wp-admin screen
* Idle detection — session ends automatically after configurable timeout
* Per-post/page breakdown with post title, post type, and time spent
* Today / This Week / All Time stat cards
* 30-day bar chart
* Streak tracker (consecutive active days)
* Dashboard widget showing today's total at a glance
* Admin toolbar live timer
* Multi-user support — each user's data is tracked separately
* Configurable: idle timeout, minimum session length, which roles are tracked
* localStorage queue — sessions are buffered locally and retried if a request fails
* Elementor editor support
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
No. It only tracks time spent inside wp-admin.

= Does it work with multisite? =
It creates per-site tables; multisite is not officially tested yet.

= What happens to my data if I deactivate the plugin? =
Data is preserved. Tables are only removed when you **delete** the plugin.

= Can I track only certain user roles? =
Yes — go to **Dev Code Tracker → Settings** and choose which roles are tracked.

== Screenshots ==

1. Dashboard — stat cards, 30-day bar chart, streak tracker
2. Per-page breakdown — post title, type, time, and session count
3. Sessions Log — full history with dd-mm-yyyy dates
4. Settings — idle timeout, minimum session, tracked roles

== Changelog ==

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

= 1.0.0 =
Initial release.
