<?php
defined( 'ABSPATH' ) || exit;

class BDCT_Admin {

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
        // Top-level sidebar entry — position 85 keeps it below core Settings (80).
        add_menu_page(
            __( 'Dev Code Tracker', 'bitlence-dev-code-tracker' ),
            __( 'Dev Code Tracker', 'bitlence-dev-code-tracker' ),
            'read',
            'bdct',
            [ __CLASS__, 'page_dashboard' ],
            'dashicons-clock',
            85
        );

        // First submenu replaces the parent label with "Dashboard".
        add_submenu_page( 'bdct', __( 'Dashboard', 'bitlence-dev-code-tracker' ),    __( 'Dashboard', 'bitlence-dev-code-tracker' ),    'read',           'bdct',          [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'bdct', __( 'Sessions Log', 'bitlence-dev-code-tracker' ), __( 'Sessions Log', 'bitlence-dev-code-tracker' ), 'read',           'bdct-sessions', [ __CLASS__, 'page_sessions'  ] );
        add_submenu_page( 'bdct', __( 'Settings', 'bitlence-dev-code-tracker' ),     __( 'Settings', 'bitlence-dev-code-tracker' ),     'manage_options', 'bdct-settings', [ __CLASS__, 'page_settings'  ] );
    }

    public static function register_widget(): void {
        wp_add_dashboard_widget(
            'bdct_today_widget',
            __( 'Dev Code Tracker — Today', 'bitlence-dev-code-tracker' ),
            [ __CLASS__, 'widget_today' ]
        );
    }

    public static function enqueue_scripts( string $hook ): void {
        // BDCT admin pages need the nonce config even when the current user's role isn't tracked
        // (e.g. an admin who removed 'administrator' from tracked roles still needs to clear data).
        $on_bdct_page = in_array( $hook, [
            'toplevel_page_bdct',
            'dev-code-tracker_page_bdct-sessions',
            'dev-code-tracker_page_bdct-settings',
        ], true );

        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'bdct_nonce' ),
            'idleMs'        => BDCT_Settings::idle_ms(),
            'minSessionSec' => BDCT_Settings::min_session_sec(),
        ];

        // Always register the handle so bdct-dashboard can declare it as a dependency safely.
        wp_register_script( 'bdct-tracker', BDCT_PLUGIN_URL . 'assets/tracker.js', [], BDCT_VERSION, true );

        if ( BDCT_Settings::is_tracked_role() ) {
            // Enqueue tracker.js on every wp-admin screen for tracked roles.
            wp_enqueue_script( 'bdct-tracker' );
            wp_localize_script( 'bdct-tracker', 'bdctConfig', $config );
        } elseif ( $on_bdct_page ) {
            // User can view BDCT pages but isn't tracked — inject config for the clear-data button.
            wp_add_inline_script( 'jquery', 'window.bdctConfig=' . wp_json_encode( $config ) . ';' );
        }

        // dashboard.js + Chart.js only on the BDCT dashboard page (not WP dashboard/index.php).
        if ( $hook === 'toplevel_page_bdct' ) {
            wp_enqueue_script(
                'chartjs',
                BDCT_PLUGIN_URL . 'assets/chart.min.js',
                [],
                '4.5.1',
                true
            );
            // No dependency on bdct-tracker — liveSessionSec() handles window.bdctTracker being absent.
            wp_enqueue_script(
                'bdct-dashboard',
                BDCT_PLUGIN_URL . 'assets/dashboard.js',
                [ 'chartjs' ],
                BDCT_VERSION,
                true
            );
        }
    }

    public static function toolbar_item( WP_Admin_Bar $bar ): void {
        if ( ! is_admin() || ! BDCT_Settings::is_tracked_role() ) {
            return;
        }
        $bar->add_node( [
            'id'    => 'bdct-status',
            'title' => '&#9679; DCT: <span id="bdct-toolbar-time">0:00</span>',
            'href'  => admin_url( 'admin.php?page=bdct' ),
            'meta'  => [ 'class' => 'bdct-toolbar-node' ],
        ] );
    }

    public static function page_dashboard(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }
        include BDCT_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public static function page_sessions(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }
        global $wpdb;
        $sessions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT s.*, COALESCE(p.post_title, s.admin_page, 'Unknown') AS page_label
                 FROM {$wpdb->prefix}bdct_time_sessions s
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
            <h1><?php esc_html_e( 'Sessions Log', 'bitlence-dev-code-tracker' ); ?></h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Started', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Page / Post', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Post ID', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Duration', 'bitlence-dev-code-tracker' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $sessions ) ) : ?>
                    <tr><td colspan="5" style="text-align:center;color:#888"><?php esc_html_e( 'No sessions recorded yet.', 'bitlence-dev-code-tracker' ); ?></td></tr>
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
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Dev Code Tracker — Settings', 'bitlence-dev-code-tracker' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( BDCT_Settings::OPTION_GROUP );
                do_settings_sections( BDCT_Settings::PAGE );
                submit_button( esc_html__( 'Save Settings', 'bitlence-dev-code-tracker' ) );
                ?>
            </form>
        </div>
        <?php
    }

    public static function elementor_editor_footer(): void {
        if ( ! BDCT_Settings::is_tracked_role() ) {
            return;
        }
        // Skip if wp_footer() already printed tracker.js (some Elementor versions do call it).
        global $wp_scripts;
        if ( ! empty( $wp_scripts->done ) && in_array( 'bdct-tracker', $wp_scripts->done, true ) ) {
            return;
        }
        // Output inline — bypasses wp_footer() which Elementor's editor template does not call.
        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'bdct_nonce' ),
            'idleMs'        => BDCT_Settings::idle_ms(),
            'minSessionSec' => BDCT_Settings::min_session_sec(),
        ];
        wp_add_inline_script( 'bdct-tracker', 'window.bdctConfig=' . wp_json_encode( $config ) . ';', 'before' );
        wp_enqueue_script( 'bdct-tracker' );
        wp_print_scripts( [ 'bdct-tracker' ] );
    }

    public static function widget_today(): void {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $sec   = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE(total_sec,0) FROM {$wpdb->prefix}bdct_daily_summary
                 WHERE user_id=%d AND summary_date=%s",
                get_current_user_id(), $today
            )
        );
        if ( $sec === 0 ) {
            echo '<p style="text-align:center;color:#888;margin:8px 0">' . esc_html__( 'No activity yet today.', 'bitlence-dev-code-tracker' ) . '</p>';
        } else {
            $h = intdiv( $sec, 3600 );
            $m = intdiv( $sec % 3600, 60 );
            printf( '<p style="font-size:2em;text-align:center;margin:8px 0">%dh %dm</p>', absint( $h ), absint( $m ) );
        }
        printf(
            '<p style="text-align:center;margin:4px 0 0"><a href="%s">%s</a></p>',
            esc_url( admin_url( 'admin.php?page=bdct' ) ),
            esc_html__( 'Full Dashboard →', 'bitlence-dev-code-tracker' )
        );
    }
}
