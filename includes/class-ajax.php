<?php
defined( 'ABSPATH' ) || exit;

class DCT_Ajax {

    public static function init(): void {
        $actions = [
            'dct_save_session'    => 'save_session',
            'dct_get_dashboard'   => 'get_dashboard',
            'dct_rename_project'  => 'rename_project',
            'dct_delete_project'  => 'delete_project',
            'dct_clear_user_data' => 'clear_user_data',
        ];
        foreach ( $actions as $action => $method ) {
            add_action( "wp_ajax_{$action}", [ __CLASS__, $method ] );
        }
    }

    public static function save_session(): void {
        check_ajax_referer( 'dct_nonce', 'nonce' );

        $started_at_utc = sanitize_text_field( wp_unslash( $_POST['started_at'] ?? '' ) );
        $ended_at_utc   = sanitize_text_field( wp_unslash( $_POST['ended_at']   ?? '' ) );

        // Validate YYYY-MM-DD HH:MM:SS format before touching the DB.
        $dt_pattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if ( ! preg_match( $dt_pattern, $started_at_utc ) || ! preg_match( $dt_pattern, $ended_at_utc ) ) {
            wp_send_json_error( 'invalid_datetime', 400 );
        }

        // Cap duration to 24 h to prevent inflated totals from crafted requests.
        $duration_sec = min( absint( wp_unslash( $_POST['duration_sec'] ?? 0 ) ), 86400 );

        // Enforce server-side minimum (JS also filters, but AJAX is public to logged-in users).
        if ( $duration_sec < max( 1, DCT_Settings::min_session_sec() ) ) {
            wp_send_json_success( [ 'skipped' => true ] );
        }

        $data = [
            'post_id'      => ! empty( $_POST['post_id'] )    ? absint( $_POST['post_id'] )                                     : null,
            'post_type'    => ! empty( $_POST['post_type'] )  ? substr( sanitize_key( $_POST['post_type'] ),  0, 50 )  : null,
            'admin_page'   => ! empty( $_POST['admin_page'] ) ? substr( sanitize_key( $_POST['admin_page'] ), 0, 100 ) : null,
            // JS sends UTC (toISOString). Convert to WP local time so daily totals align with
            // current_time() comparisons and what users see in the WordPress timezone.
            'started_at'   => get_date_from_gmt( $started_at_utc ),
            'ended_at'     => get_date_from_gmt( $ended_at_utc ),
            'duration_sec' => $duration_sec,
        ];

        $id = DCT_DB::insert_session( $data );
        $id ? wp_send_json_success( [ 'id' => $id ] ) : wp_send_json_error( 'db_error', 500 );
    }

    public static function get_dashboard(): void {
        check_ajax_referer( 'dct_nonce', 'nonce' );
        wp_send_json_success( DCT_DB::get_dashboard( get_current_user_id() ) );
    }

    public static function rename_project(): void {
        check_ajax_referer( 'dct_nonce', 'nonce' );
        global $wpdb;
        $id    = absint( $_POST['project_id'] ?? 0 );
        $label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
        if ( ! $id || ! $label ) {
            wp_send_json_error( 'invalid_data', 400 );
        }
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'dct_projects',
            [ 'label' => $label ],
            [ 'id' => $id, 'user_id' => get_current_user_id() ],
            [ '%s' ], [ '%d', '%d' ]
        );
        wp_send_json_success();
    }

    public static function delete_project(): void {
        check_ajax_referer( 'dct_nonce', 'nonce' );
        global $wpdb;
        $id = absint( $_POST['project_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( 'invalid_data', 400 );
        }
        $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'dct_projects',
            [ 'id' => $id, 'user_id' => get_current_user_id() ],
            [ '%d', '%d' ]
        );
        wp_send_json_success();
    }

    public static function clear_user_data(): void {
        check_ajax_referer( 'dct_nonce', 'nonce' );
        DCT_DB::delete_user_data( get_current_user_id() );
        wp_send_json_success();
    }
}
