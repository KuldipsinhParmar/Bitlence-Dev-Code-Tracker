<?php
/**
 * PHPUnit bootstrap for Bitlence Dev Code Tracker.
 * See tests/README.md for one-time local setup.
 */

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

require_once $_tests_dir . '/includes/functions.php';

// Load the plugin the same way WP would, before the test suite spins up.
function _bdct_load_plugin() {
    require dirname( __DIR__ ) . '/bitlence-dev-code-tracker.php';
}
tests_add_filter( 'muplugins_loaded', '_bdct_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
