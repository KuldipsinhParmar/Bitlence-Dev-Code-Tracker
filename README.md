<p align="center">
  <img src="assets/logo.png" alt="Dev Code Tracker" width="160">
</p>

# Dev Code Tracker

A WordPress plugin that tracks time spent in wp-admin per page/post — porting the VS Code Dev Code Tracker extension logic 1:1 into WordPress.

## Features

- Tracks active time on every wp-admin screen
- Idle detection — session ends automatically after configurable timeout
- Per-post/page breakdown with post title, post type, and time spent
- Today / This Week / All Time stat cards
- 30-day bar chart
- Streak tracker (consecutive active days)
- Dashboard widget showing today's total at a glance
- Admin toolbar live timer
- Multi-user support — each user's data is tracked separately
- Configurable: idle timeout, minimum session length, which roles are tracked
- localStorage queue — sessions are buffered locally and retried if a request fails
- Elementor editor support — tracks time inside the Elementor page builder
- SPA navigation tracking — works with WooCommerce Analytics and other React/Vue admin plugins

## Admin Menu

```
Dev Code Tracker → Dashboard
Dev Code Tracker → Sessions Log
Dev Code Tracker → Settings
```

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 6.0+    |
| PHP         | 8.0+    |
| MySQL       | 8.0+    |

## Installation

1. Upload the `wp-dev-tracker` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. The DB tables are created automatically on activation
4. Visit **Dev Code Tracker → Dashboard** to see your stats

## Screenshots

1. Dashboard — stat cards, 30-day bar chart, streak tracker
2. Per-page breakdown — post title, type, time, and session count
3. Sessions Log — full history with dd-mm-yyyy dates
4. Settings — idle timeout, minimum session, tracked roles

## FAQ

**Does it track front-end page views?**
No. It only tracks time spent inside wp-admin.

**Does it work with multisite?**
It creates per-site tables; multisite is not officially tested yet.

**What happens to my data if I deactivate the plugin?**
Data is preserved. Tables are only removed when you **delete** the plugin.

**Can I track only certain user roles?**
Yes — go to **Dev Code Tracker → Settings** and choose which roles are tracked.

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
