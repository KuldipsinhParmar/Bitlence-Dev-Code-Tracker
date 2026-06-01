<?php
defined( 'ABSPATH' ) || exit;

class BDCT_DB {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}bdct_time_sessions (
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

        dbDelta( "CREATE TABLE {$wpdb->prefix}bdct_daily_summary (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            summary_date  DATE NOT NULL,
            total_sec     INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_date (user_id, summary_date)
        ) $charset;" );

        // Projects table was removed in 1.1.0 — drop it for users upgrading from 1.0.0.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bdct_projects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

        // Backfill post_type for sessions saved before server-side resolution was added.
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "UPDATE {$wpdb->prefix}bdct_time_sessions ts
             INNER JOIN {$wpdb->prefix}posts p ON p.ID = ts.post_id
             SET ts.post_type = p.post_type
             WHERE ts.post_id > 0 AND ( ts.post_type IS NULL OR ts.post_type = '' )"
        );

        update_option( 'bdct_db_version', BDCT_VERSION );
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
            $wpdb->prefix . 'bdct_time_sessions',
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
                "INSERT INTO {$wpdb->prefix}bdct_daily_summary (user_id, summary_date, total_sec)
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

        // Combine today / week / all-time into a single query instead of three.
        $stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT
                    COALESCE( SUM( CASE WHEN summary_date = %s THEN total_sec ELSE 0 END ), 0 ) AS today_sec,
                    COALESCE( SUM( CASE WHEN summary_date BETWEEN %s AND %s THEN total_sec ELSE 0 END ), 0 ) AS week_sec,
                    COALESCE( SUM( total_sec ), 0 ) AS all_sec
                 FROM {$wpdb->prefix}bdct_daily_summary
                 WHERE user_id = %d",
                $today, $week, $today, $user_id
            ),
            ARRAY_A
        );
        $today_sec = (int) ( $stats['today_sec'] ?? 0 );
        $week_sec  = (int) ( $stats['week_sec']  ?? 0 );
        $all_sec   = (int) ( $stats['all_sec']   ?? 0 );

        $daily_30 = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT summary_date AS date, total_sec AS seconds
                 FROM {$wpdb->prefix}bdct_daily_summary
                 WHERE user_id=%d AND summary_date >= %s
                 ORDER BY summary_date ASC",
                $user_id, wp_date( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) )
            ),
            ARRAY_A
        );

        $recent = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT s.id, s.post_id, s.post_type, s.admin_page, s.started_at, s.duration_sec,
                        COALESCE(p.post_title, s.admin_page, 'Unknown') AS page_label
                 FROM {$wpdb->prefix}bdct_time_sessions s
                 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = s.post_id AND s.post_id > 0
                 WHERE s.user_id=%d
                 ORDER BY s.started_at DESC LIMIT 10",
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
                 FROM {$wpdb->prefix}bdct_time_sessions s
                 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = s.post_id AND s.post_id > 0
                 WHERE s.user_id = %d
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
                "SELECT summary_date FROM {$wpdb->prefix}bdct_daily_summary
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
        $wpdb->delete( $wpdb->prefix . 'bdct_time_sessions', [ 'user_id' => $user_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'bdct_daily_summary', [ 'user_id' => $user_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_today_sec( int $user_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE(total_sec,0) FROM {$wpdb->prefix}bdct_daily_summary
                 WHERE user_id=%d AND summary_date=%s",
                $user_id, current_time( 'Y-m-d' )
            )
        );
    }

    public static function count_sessions( int $user_id, string $from = '', string $to = '' ): int {
        global $wpdb;
        [ $where_sql, $where_args ] = self::sessions_where( $user_id, $from, $to );
        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bdct_time_sessions WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                ...$where_args
            )
        );
    }

    public static function get_sessions( int $user_id, int $limit = 50, int $offset = 0, string $from = '', string $to = '', string $orderby = 'started_at', string $order = 'DESC' ): array {
        global $wpdb;
        [ $where_sql, $where_args ] = self::sessions_where( $user_id, $from, $to );
        $args = array_merge( $where_args, [ $limit, $offset ] );

        // Whitelist column and direction — cannot use placeholders for ORDER BY identifiers.
        $orderby_col = in_array( $orderby, [ 'started_at', 'duration_sec' ], true ) ? $orderby : 'started_at';
        $order_dir   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->prepare(
                "SELECT s.*, COALESCE(p.post_title, s.admin_page, 'Unknown') AS page_label
                 FROM {$wpdb->prefix}bdct_time_sessions s
                 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = s.post_id AND s.post_id > 0
                 WHERE {$where_sql}
                 ORDER BY s.{$orderby_col} {$order_dir}
                 LIMIT %d OFFSET %d",
                ...$args
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    }

    private static function sessions_where( int $user_id, string $from, string $to ): array {
        $parts = [ 'user_id = %d' ];
        $args  = [ $user_id ];
        if ( $from !== '' ) { $parts[] = 'DATE(started_at) >= %s'; $args[] = $from; }
        if ( $to   !== '' ) { $parts[] = 'DATE(started_at) <= %s'; $args[] = $to;   }
        return [ implode( ' AND ', $parts ), $args ];
    }
}
