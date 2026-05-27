<?php
defined( 'ABSPATH' ) || exit;

class DCT_Admin {

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'wp_dashboard_setup',    [ __CLASS__, 'register_widget' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
        add_action( 'admin_bar_menu',        [ __CLASS__, 'toolbar_item' ], 100 );
        // Elementor's editor template calls do_action('elementor/editor/footer') instead of
        // wp_footer(), so footer-queued scripts are never printed. Force-print tracker.js here.
        add_action( 'elementor/editor/footer', [ __CLASS__, 'elementor_editor_footer' ] );
    }

    public static function register_menu(): void {
        // Top-level sidebar entry.
        add_menu_page(
            'Dev Code Tracker',
            'Dev Code Tracker',
            'read',
            'dct',
            [ __CLASS__, 'page_dashboard' ],
            'dashicons-clock',
            25
        );

        // First submenu replaces the parent label with "Dashboard".
        add_submenu_page( 'dct', 'Dashboard',    'Dashboard',    'read',           'dct',          [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'dct', 'Sessions Log', 'Sessions Log', 'read',           'dct-sessions', [ __CLASS__, 'page_sessions'  ] );
        add_submenu_page( 'dct', 'Settings',     'Settings',     'manage_options', 'dct-settings', [ __CLASS__, 'page_settings'  ] );
    }

    public static function register_widget(): void {
        wp_add_dashboard_widget(
            'dct_today_widget',
            'Dev Code Tracker — Today',
            [ __CLASS__, 'widget_today' ]
        );
    }

    public static function enqueue_scripts( string $hook ): void {
        // DCT admin pages need the nonce config even when the current user's role isn't tracked
        // (e.g. an admin who removed 'administrator' from tracked roles still needs to clear data).
        $on_dct_page = in_array( $hook, [
            'toplevel_page_dct',
            'dev-code-tracker_page_dct-sessions',
            'dev-code-tracker_page_dct-settings',
        ], true );

        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'dct_nonce' ),
            'idleMs'        => DCT_Settings::idle_ms(),
            'minSessionSec' => DCT_Settings::min_session_sec(),
        ];

        // Always register the handle so dct-dashboard can declare it as a dependency safely.
        wp_register_script( 'dct-tracker', DCT_PLUGIN_URL . 'assets/tracker.js', [], DCT_VERSION, true );

        if ( DCT_Settings::is_tracked_role() ) {
            // Enqueue tracker.js on every wp-admin screen for tracked roles.
            wp_enqueue_script( 'dct-tracker' );
            wp_localize_script( 'dct-tracker', 'dctConfig', $config );
        } elseif ( $on_dct_page ) {
            // User can view DCT pages but isn't tracked — inject config for the clear-data button.
            wp_add_inline_script( 'jquery', 'window.dctConfig=' . wp_json_encode( $config ) . ';' );
        }

        // dashboard.js + Chart.js only on the DCT dashboard page (not WP dashboard/index.php).
        if ( $hook === 'toplevel_page_dct' ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
                [],
                '4',
                true
            );
            // No dependency on dct-tracker — liveSessionSec() handles window.dctTracker being absent.
            wp_enqueue_script(
                'dct-dashboard',
                DCT_PLUGIN_URL . 'assets/dashboard.js',
                [ 'chartjs' ],
                DCT_VERSION,
                true
            );
        }
    }

    public static function toolbar_item( WP_Admin_Bar $bar ): void {
        if ( ! is_admin() || ! DCT_Settings::is_tracked_role() ) {
            return;
        }
        $bar->add_node( [
            'id'    => 'dct-status',
            'title' => '&#9679; DCT: <span id="dct-toolbar-time">0:00</span>',
            'href'  => admin_url( 'admin.php?page=dct' ),
            'meta'  => [ 'class' => 'dct-toolbar-node' ],
        ] );
    }

    public static function page_dashboard(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'dev-code-tracker' ) );
        }
        include DCT_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public static function page_sessions(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'dev-code-tracker' ) );
        }
        global $wpdb;
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, COALESCE(p.post_title, s.admin_page, 'Unknown') AS page_label
                 FROM {$wpdb->prefix}dct_time_sessions s
                 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = s.post_id AND s.post_id > 0
                 WHERE s.user_id = %d
                 ORDER BY s.started_at DESC
                 LIMIT 200",
                get_current_user_id()
            ),
            ARRAY_A
        );
        ?>
        <div class="wrap">
            <h1>Sessions Log</h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Started</th>
                        <th>Page / Post</th>
                        <th>Type</th>
                        <th>Post ID</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $sessions ) ) : ?>
                    <tr><td colspan="5" style="text-align:center;color:#888">No sessions recorded yet.</td></tr>
                <?php else : ?>
                    <?php foreach ( $sessions as $s ) :
                        $sec = (int) $s['duration_sec'];
                        $h   = intdiv( $sec, 3600 );
                        $m   = intdiv( $sec % 3600, 60 );
                        $s2  = $sec % 60;
                        $dur = $h > 0
                            ? sprintf( '%dh %dm %ds', $h, $m, $s2 )
                            : sprintf( '%dm %ds', $m, $s2 );
                    ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'd-m-Y H:i', strtotime( $s['started_at'] ) ) ); ?></td>
                        <td><?php echo esc_html( $s['page_label'] ); ?></td>
                        <td><?php echo esc_html( $s['post_type'] ?? '—' ); ?></td>
                        <td><?php echo $s['post_id'] ? esc_html( $s['post_id'] ) : '—'; ?></td>
                        <td><?php echo esc_html( $dur ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'dev-code-tracker' ) );
        }
        ?>
        <div class="wrap">
            <h1>Dev Code Tracker — Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( DCT_Settings::OPTION_GROUP );
                do_settings_sections( DCT_Settings::PAGE );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }

    public static function elementor_editor_footer(): void {
        if ( ! DCT_Settings::is_tracked_role() ) {
            return;
        }
        // Skip if wp_footer() already printed tracker.js (some Elementor versions do call it).
        global $wp_scripts;
        if ( ! empty( $wp_scripts->done ) && in_array( 'dct-tracker', $wp_scripts->done, true ) ) {
            return;
        }
        // Output inline — bypasses wp_footer() which Elementor's editor template does not call.
        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'dct_nonce' ),
            'idleMs'        => DCT_Settings::idle_ms(),
            'minSessionSec' => DCT_Settings::min_session_sec(),
        ];
        echo '<script>window.dctConfig=' . wp_json_encode( $config ) . ';</script>' . "\n";
        echo '<script src="' . esc_url( DCT_PLUGIN_URL . 'assets/tracker.js' ) . '?v=' . esc_attr( DCT_VERSION ) . '"></script>' . "\n";
    }

    public static function widget_today(): void {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $sec   = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(total_sec,0) FROM {$wpdb->prefix}dct_daily_summary
                 WHERE user_id=%d AND summary_date=%s",
                get_current_user_id(), $today
            )
        );
        if ( $sec === 0 ) {
            echo '<p style="text-align:center;color:#888;margin:8px 0">No activity yet today.</p>';
        } else {
            $h = intdiv( $sec, 3600 );
            $m = intdiv( $sec % 3600, 60 );
            printf( '<p style="font-size:2em;text-align:center;margin:8px 0">%dh %dm</p>', $h, $m );
        }
        printf(
            '<p style="text-align:center;margin:4px 0 0"><a href="%s">Full Dashboard →</a></p>',
            esc_url( admin_url( 'admin.php?page=dct' ) )
        );
    }
}
