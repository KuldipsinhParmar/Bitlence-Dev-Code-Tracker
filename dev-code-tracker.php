<?php
/**
 * Plugin Name:       Dev Code Tracker
 * Plugin URI:        https://github.com/kuldipparmar18/wp-dev-tracker
 * Description:       Track time spent in wp-admin per page/post with idle detection, a 30-day chart, streak tracker, and per-page breakdown.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Kuldip Parmar
 * Author URI:        https://github.com/kuldipparmar18
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dev-code-tracker
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'DCT_VERSION',    '1.0.0' );
define( 'DCT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DCT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DCT_PLUGIN_DIR . 'includes/class-db.php';
require_once DCT_PLUGIN_DIR . 'includes/class-ajax.php';
require_once DCT_PLUGIN_DIR . 'includes/class-settings.php';
require_once DCT_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, [ 'DCT_DB', 'install' ] );

// Re-run install on version upgrade so DB schema stays current.
add_action( 'plugins_loaded', function () {
    if ( get_option( 'dct_db_version' ) !== DCT_VERSION ) {
        DCT_DB::install();
    }

    DCT_Ajax::init();
    DCT_Settings::init();
    DCT_Admin::init();
} );
