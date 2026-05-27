# Changelog

All notable changes to Dev Code Tracker are documented here.

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
