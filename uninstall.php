<?php
// WordPress calls this file only when the plugin is deleted from the admin.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dct_time_sessions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dct_daily_summary" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dct_projects" );

delete_option( 'dct_db_version' );
delete_option( 'dct_idle_timeout' );
delete_option( 'dct_min_session_sec' );
delete_option( 'dct_timezone_offset' );
delete_option( 'dct_track_roles' );
