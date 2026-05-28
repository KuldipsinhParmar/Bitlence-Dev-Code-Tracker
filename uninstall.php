<?php
// WordPress calls this file only when the plugin is deleted from the admin.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdct_time_sessions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdct_daily_summary" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdct_projects" );      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

delete_option( 'bdct_db_version' );
delete_option( 'bdct_idle_timeout' );
delete_option( 'bdct_min_session_sec' );
delete_option( 'bdct_timezone_offset' );
delete_option( 'bdct_track_roles' );
