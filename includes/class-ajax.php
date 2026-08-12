<?php
defined( 'ABSPATH' ) || exit;

class BDCT_Ajax {

    public static function init(): void {
        $actions = [
            'bdct_save_session'     => 'save_session',
            'bdct_get_dashboard'    => 'get_dashboard',
            'bdct_clear_user_data'  => 'clear_user_data',
            'bdct_get_team_summary' => 'get_team_summary',
        ];
        foreach ( $actions as $action => $method ) {
            add_action( "wp_ajax_{$action}", [ __CLASS__, $method ] );
        }
    }

    public static function save_session(): void {
        check_ajax_referer( 'bdct_nonce', 'nonce' );

        // Only tracked roles may write sessions — nonce alone doesn't enforce role.
        if ( ! BDCT_Settings::is_tracked_role() ) {
            wp_send_json_error( 'not_tracked', 403 );
        }

        $started_at_utc = sanitize_text_field( wp_unslash( $_POST['started_at'] ?? '' ) );
        $ended_at_utc   = sanitize_text_field( wp_unslash( $_POST['ended_at']   ?? '' ) );

        // Validate YYYY-MM-DD HH:MM:SS format before touching the DB.
        $dt_pattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if ( ! preg_match( $dt_pattern, $started_at_utc ) || ! preg_match( $dt_pattern, $ended_at_utc ) ) {
            wp_send_json_error( 'invalid_datetime', 400 );
        }

        // Recompute duration from timestamps — don't trust the client value.
        $computed_sec = max( 0, (int) ( strtotime( $ended_at_utc ) - strtotime( $started_at_utc ) ) );
        $duration_sec = min( $computed_sec, 86400 );

        // Enforce server-side minimum (JS also filters, but AJAX is public to logged-in users).
        if ( $duration_sec < max( 1, BDCT_Settings::min_session_sec() ) ) {
            wp_send_json_success( [ 'skipped' => true ] );
        }

        $post_id   = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : null;
        $post_type = ! empty( $_POST['post_type'] ) ? substr( sanitize_key( $_POST['post_type'] ), 0, 50 ) : null;

        // JS can't always read typenow (Elementor, etc.) — resolve from DB when missing.
        if ( ! $post_type && $post_id ) {
            $resolved  = get_post_type( $post_id );
            $post_type = $resolved ?: null;
        }

        $data = [
            'post_id'      => $post_id,
            'post_type'    => $post_type,
            'admin_page'   => ! empty( $_POST['admin_page'] ) ? substr( sanitize_key( $_POST['admin_page'] ), 0, 100 ) : null,
            // JS sends UTC (toISOString). Convert to WP local time so daily totals align with
            // current_time() comparisons and what users see in the WordPress timezone.
            'started_at'   => get_date_from_gmt( $started_at_utc ),
            'ended_at'     => get_date_from_gmt( $ended_at_utc ),
            'duration_sec' => $duration_sec,
        ];

        $id = BDCT_DB::insert_session( $data );
        $id ? wp_send_json_success( [ 'id' => $id ] ) : wp_send_json_error( 'db_error', 500 );
    }

    public static function get_dashboard(): void {
        check_ajax_referer( 'bdct_nonce', 'nonce' );
        wp_send_json_success( BDCT_DB::get_dashboard( get_current_user_id() ) );
    }

    public static function clear_user_data(): void {
        check_ajax_referer( 'bdct_nonce', 'nonce' );
        BDCT_DB::delete_user_data( get_current_user_id() );
        wp_send_json_success();
    }

    // Team-wide per-user totals — admin only, so teammates on the same site are visible to whoever manages it.
    public static function get_team_summary(): void {
        check_ajax_referer( 'bdct_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'forbidden', 403 );
        }

        $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
        $from = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) );
        $to   = sanitize_text_field( wp_unslash( $_POST['to']   ?? '' ) );
        $from = preg_match( $date_pattern, $from ) ? $from : '';
        $to   = preg_match( $date_pattern, $to )   ? $to   : '';

        wp_send_json_success( BDCT_DB::get_team_summary( $from, $to ) );
    }
}
