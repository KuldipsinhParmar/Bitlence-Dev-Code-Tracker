<?php
defined( 'ABSPATH' ) || exit;

class DCT_DB {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}dct_time_sessions (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            post_id       INT DEFAULT NULL,
            post_type     VARCHAR(50) DEFAULT NULL,
            admin_page    VARCHAR(100) DEFAULT NULL,
            started_at    DATETIME NOT NULL,
            ended_at      DATETIME NOT NULL,
            duration_sec  INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY started_at (started_at)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}dct_daily_summary (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            summary_date  DATE NOT NULL,
            total_sec     INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_date (user_id, summary_date)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}dct_projects (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            label         VARCHAR(255) NOT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset;" );

        update_option( 'dct_db_version', DCT_VERSION );
    }

    public static function insert_session( array $data ): int|false {
        global $wpdb;

        $fields  = [
            'user_id'      => get_current_user_id(),
            'post_type'    => $data['post_type']  ?? null,
            'admin_page'   => $data['admin_page'] ?? null,
            'started_at'   => $data['started_at'],
            'ended_at'     => $data['ended_at'],
            'duration_sec' => absint( $data['duration_sec'] ),
        ];
        $formats = [ '%d', '%s', '%s', '%s', '%s', '%d' ];

        if ( ! empty( $data['post_id'] ) ) {
            $fields['post_id'] = absint( $data['post_id'] );
            $formats[]         = '%d';
        }

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prefix . 'dct_time_sessions',
            $fields,
            $formats
        );
        if ( false === $result ) {
            return false;
        }
        self::upsert_daily_summary( $data['started_at'], absint( $data['duration_sec'] ) );
        return $wpdb->insert_id;
    }

    private static function upsert_daily_summary( string $started_at, int $seconds ): void {
        global $wpdb;
        $date    = substr( $started_at, 0, 10 );
        $user_id = get_current_user_id();
        // Pass $seconds twice: once for the INSERT value, once for the UPDATE expression.
        // Avoids both the deprecated VALUES() function and the MySQL 8.0.19+ row-alias syntax.
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}dct_daily_summary (user_id, summary_date, total_sec)
                 VALUES (%d, %s, %d)
                 ON DUPLICATE KEY UPDATE total_sec = total_sec + %d",
                $user_id, $date, $seconds, $seconds
            )
        );
    }

    public static function get_dashboard( int $user_id ): array {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $week  = wp_date( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );

        $today_sec = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE(total_sec,0) FROM {$wpdb->prefix}dct_daily_summary
                 WHERE user_id=%d AND summary_date=%s",
                $user_id, $today
            )
        );

        $week_sec = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_sec),0) FROM {$wpdb->prefix}dct_daily_summary
                 WHERE user_id=%d AND summary_date BETWEEN %s AND %s",
                $user_id, $week, $today
            )
        );

        $all_sec = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE(SUM(total_sec),0) FROM {$wpdb->prefix}dct_daily_summary
                 WHERE user_id=%d",
                $user_id
            )
        );

        $daily_30 = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT summary_date AS date, total_sec AS seconds
                 FROM {$wpdb->prefix}dct_daily_summary
                 WHERE user_id=%d AND summary_date >= %s
                 ORDER BY summary_date ASC",
                $user_id, wp_date( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) )
            ),
            ARRAY_A
        );

        $recent = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id, post_id, post_type, admin_page, started_at, duration_sec
                 FROM {$wpdb->prefix}dct_time_sessions
                 WHERE user_id=%d
                 ORDER BY started_at DESC LIMIT 50",
                $user_id
            ),
            ARRAY_A
        );

        $per_page = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT s.post_id, s.post_type, s.admin_page,
                        COALESCE(MAX(p.post_title), s.admin_page, 'Unknown') AS page_label,
                        SUM(s.duration_sec) AS total_sec,
                        COUNT(*) AS sessions
                 FROM {$wpdb->prefix}dct_time_sessions s
                 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = s.post_id AND s.post_id > 0
                 WHERE s.user_id=%d
                 GROUP BY s.post_id, s.post_type, s.admin_page
                 ORDER BY total_sec DESC
                 LIMIT 100",
                $user_id
            ),
            ARRAY_A
        );

        $streak = self::get_streak( $user_id );

        return compact( 'today_sec', 'week_sec', 'all_sec', 'streak', 'daily_30', 'recent', 'per_page' );
    }

    public static function get_streak( int $user_id ): int {
        global $wpdb;
        $dates = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT summary_date FROM {$wpdb->prefix}dct_daily_summary
                 WHERE user_id = %d AND total_sec > 0
                 ORDER BY summary_date DESC
                 LIMIT 366",
                $user_id
            )
        );

        if ( empty( $dates ) ) {
            return 0;
        }

        $today     = current_time( 'Y-m-d' );
        $yesterday = wp_date( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );

        // Streak is 0 if there's no activity today or yesterday.
        if ( $dates[0] !== $today && $dates[0] !== $yesterday ) {
            return 0;
        }

        $streak = 0;
        $check  = $dates[0];
        foreach ( $dates as $date ) {
            if ( $date === $check ) {
                $streak++;
                $check = wp_date( 'Y-m-d', strtotime( '-1 day', strtotime( $check ) ) );
            } else {
                break;
            }
        }

        return $streak;
    }

    public static function delete_user_data( int $user_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'dct_time_sessions',  [ 'user_id' => $user_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'dct_daily_summary',  [ 'user_id' => $user_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'dct_projects',       [ 'user_id' => $user_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
