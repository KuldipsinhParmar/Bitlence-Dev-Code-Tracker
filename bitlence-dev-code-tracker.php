<?php
/**
 * Plugin Name:       Bitlence Dev Code Tracker
 * Plugin URI:        https://github.com/KuldipsinhParmar/Bitlence-Dev-Code-Tracker
 * Description:       Track time spent in wp-admin per page/post with idle detection, a 30-day chart, streak tracker, and per-page breakdown.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Kuldip Parmar
 * Author URI:        https://github.com/KuldipsinhParmar
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bitlence-dev-code-tracker
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'BDCT_VERSION',    '1.4.0' );
define( 'BDCT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDCT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BDCT_PLUGIN_DIR . 'includes/class-db.php';
require_once BDCT_PLUGIN_DIR . 'includes/class-ajax.php';
require_once BDCT_PLUGIN_DIR . 'includes/class-settings.php';
require_once BDCT_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, [ 'BDCT_DB', 'install' ] );

// Re-run install on version upgrade so DB schema stays current.
add_action( 'plugins_loaded', function () {
    if ( get_option( 'bdct_db_version' ) !== BDCT_VERSION ) {
        BDCT_DB::install();
    }

    BDCT_Ajax::init();
    BDCT_Settings::init();
    BDCT_Admin::init();
} );
