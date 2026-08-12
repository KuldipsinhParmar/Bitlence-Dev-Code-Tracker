<?php
defined( 'ABSPATH' ) || exit;

class BDCT_Admin {

    public static function init(): void {
        add_action( 'admin_menu',              [ __CLASS__, 'register_menu' ] );
        add_action( 'wp_dashboard_setup',      [ __CLASS__, 'register_widget' ] );
        add_action( 'admin_enqueue_scripts',   [ __CLASS__, 'enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts',      [ __CLASS__, 'frontend_enqueue_scripts' ] );
        add_action( 'admin_bar_menu',          [ __CLASS__, 'toolbar_item' ], 100 );
        add_action( 'admin_init',              [ __CLASS__, 'maybe_export_csv' ] );
        add_action( 'admin_init',              [ __CLASS__, 'maybe_export_team_csv' ] );
        add_action( 'elementor/editor/footer', [ __CLASS__, 'elementor_editor_footer' ] );
    }

    private static function is_frontend_builder(): bool {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['ct_builder'] )                                               // Oxygen
            || isset( $_GET['fl_builder'] )                                               // Beaver Builder
            || isset( $_GET['et_fb'] )                                                    // Divi Visual Builder
            || ( isset( $_GET['vc_action'] ) && 'vc_inline' === $_GET['vc_action'] )      // WPBakery
            || isset( $_GET['brizy-edit'] )                                               // Brizy
            || ( isset( $_GET['tve'] ) && '1' === $_GET['tve'] )                          // Thrive Architect
            || isset( $_GET['seedprod_page'] )                                            // SeedProd
            || isset( $_GET['bricks'] )                                                   // Bricks
            || isset( $_GET['breakdance'] );                                              // Breakdance
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    public static function frontend_enqueue_scripts(): void {
        if ( ! BDCT_Settings::is_tracked_role() || ! self::is_frontend_builder() ) {
            return;
        }
        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'bdct_nonce' ),
            'idleMs'        => BDCT_Settings::idle_ms(),
            'minSessionSec' => BDCT_Settings::min_session_sec(),
            'todaySec'      => BDCT_DB::get_today_sec( get_current_user_id() ),
            'postId'        => get_the_ID() ?: null,
            'postType'      => get_post_type() ?: null,
        ];
        wp_register_script( 'bdct-tracker', BDCT_PLUGIN_URL . 'assets/tracker.js', [], BDCT_VERSION, true );
        wp_enqueue_script( 'bdct-tracker' );
        wp_localize_script( 'bdct-tracker', 'bdctConfig', $config );
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
        add_submenu_page( 'bdct', __( 'Dashboard', 'bitlence-dev-code-tracker' ),      __( 'Dashboard', 'bitlence-dev-code-tracker' ),      'read',           'bdct',          [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'bdct', __( 'Sessions Log', 'bitlence-dev-code-tracker' ),   __( 'Sessions Log', 'bitlence-dev-code-tracker' ),   'read',           'bdct-sessions', [ __CLASS__, 'page_sessions'  ] );
        add_submenu_page( 'bdct', __( 'Team Overview', 'bitlence-dev-code-tracker' ),  __( 'Team Overview', 'bitlence-dev-code-tracker' ),  'manage_options', 'bdct-team',     [ __CLASS__, 'page_team'      ] );
        add_submenu_page( 'bdct', __( 'Settings', 'bitlence-dev-code-tracker' ),       __( 'Settings', 'bitlence-dev-code-tracker' ),       'manage_options', 'bdct-settings', [ __CLASS__, 'page_settings'  ] );
    }

    public static function register_widget(): void {
        wp_add_dashboard_widget(
            'bdct_today_widget',
            __( 'Dev Code Tracker — Today', 'bitlence-dev-code-tracker' ),
            [ __CLASS__, 'widget_today' ]
        );
    }

    public static function enqueue_scripts( string $hook ): void {
        $on_bdct_page = in_array( $hook, [
            'toplevel_page_bdct',
            'dev-code-tracker_page_bdct-sessions',
            'dev-code-tracker_page_bdct-team',
            'dev-code-tracker_page_bdct-settings',
        ], true );

        if ( $on_bdct_page || BDCT_Settings::is_tracked_role() ) {
            wp_enqueue_style( 'bdct-admin', BDCT_PLUGIN_URL . 'assets/admin.css', [], BDCT_VERSION );
        }

        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'bdct_nonce' ),
            'idleMs'        => BDCT_Settings::idle_ms(),
            'minSessionSec' => BDCT_Settings::min_session_sec(),
            'todaySec'      => BDCT_DB::get_today_sec( get_current_user_id() ),
        ];

        wp_register_script( 'bdct-tracker', BDCT_PLUGIN_URL . 'assets/tracker.js', [], BDCT_VERSION, true );

        if ( BDCT_Settings::is_tracked_role() ) {
            wp_enqueue_script( 'bdct-tracker' );
            wp_localize_script( 'bdct-tracker', 'bdctConfig', $config );
        } elseif ( $on_bdct_page ) {
            wp_add_inline_script( 'jquery', 'window.bdctConfig=' . wp_json_encode( $config ) . ';' );
        }

        if ( $hook === 'toplevel_page_bdct' ) {
            wp_enqueue_script( 'chartjs', BDCT_PLUGIN_URL . 'assets/chart.min.js', [], '4.5.1', true );
            wp_enqueue_script( 'bdct-dashboard', BDCT_PLUGIN_URL . 'assets/dashboard.js', [ 'chartjs' ], BDCT_VERSION, true );
            wp_localize_script( 'bdct-dashboard', 'bdctConfig', $config );
        }

        if ( $hook === 'dev-code-tracker_page_bdct-team' ) {
            wp_enqueue_script( 'bdct-team', BDCT_PLUGIN_URL . 'assets/team.js', [], BDCT_VERSION, true );
            wp_localize_script( 'bdct-team', 'bdctConfig', $config );
        }
    }

    public static function toolbar_item( WP_Admin_Bar $bar ): void {
        if ( ! BDCT_Settings::is_tracked_role() ) {
            return;
        }
        // On the frontend only show the node when a page builder is active.
        if ( ! is_admin() && ! self::is_frontend_builder() ) {
            return;
        }
        $bar->add_node( [
            'id'    => 'bdct-status',
            'title' => '&#9679; DCT<span id="bdct-toolbar-time"></span>',
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

    public static function page_team(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }
        include BDCT_PLUGIN_DIR . 'templates/team.php';
    }

    public static function page_sessions(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }

        // Only admins may view other users' sessions. Everyone else is always locked to their own —
        // this ignores bdct_user entirely for non-admins so it can't be tampered with via the URL.
        $is_team_viewer = current_user_can( 'manage_options' );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
        $from    = sanitize_text_field( wp_unslash( $_GET['bdct_from'] ?? '' ) );
        $to      = sanitize_text_field( wp_unslash( $_GET['bdct_to']   ?? '' ) );
        $from    = preg_match( $date_pattern, $from ) ? $from : '';
        $to      = preg_match( $date_pattern, $to )   ? $to   : '';
        $orderby = in_array( $_GET['orderby'] ?? '', [ 'started_at', 'duration_sec' ], true )
                       ? sanitize_key( $_GET['orderby'] )
                       : 'started_at';
        $order   = strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ?? 'DESC' ) ) ) === 'ASC' ? 'ASC' : 'DESC';

        // filter_user_id: 0 = all users (team viewers only, default view). Non-admins are always forced to "me".
        $filter_user_id = $is_team_viewer ? absint( wp_unslash( $_GET['bdct_user'] ?? 0 ) ) : get_current_user_id();

        $per_page     = 50;
        $current_page = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $offset       = ( $current_page - 1 ) * $per_page;
        $total        = BDCT_DB::count_sessions( $filter_user_id, $from, $to );
        $sessions     = BDCT_DB::get_sessions( $filter_user_id, $per_page, $offset, $from, $to, $orderby, $order );
        $tracked_users = $is_team_viewer ? BDCT_DB::get_tracked_users() : [];
        $export_args  = array_filter( [ 'page' => 'bdct-sessions', 'bdct_export' => 'csv', 'bdct_from' => $from, 'bdct_to' => $to, 'bdct_user' => $is_team_viewer ? ( $filter_user_id ?: null ) : null ] );
        $export_url   = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin.php' ) ), 'bdct_export_csv' );
        $total_pages  = (int) ceil( $total / $per_page );

        // Helper: build a sortable column header link.
        $sort_link = function ( string $label, string $col ) use ( $orderby, $order, $from, $to, $filter_user_id, $is_team_viewer ) : string {
            $new_order = ( $orderby === $col && $order === 'DESC' ) ? 'asc' : 'desc';
            $url       = add_query_arg( array_filter( [
                'page'      => 'bdct-sessions',
                'orderby'   => $col,
                'order'     => $new_order,
                'bdct_from' => $from,
                'bdct_to'   => $to,
                'bdct_user' => $is_team_viewer ? ( $filter_user_id ?: null ) : null,
            ] ), admin_url( 'admin.php' ) );
            $arrow = '';
            if ( $orderby === $col ) {
                $arrow = $order === 'ASC' ? ' &#9650;' : ' &#9660;';
            }
            return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $arrow . '</a>';
        };
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sessions Log', 'bitlence-dev-code-tracker' ); ?></h1>

            <!-- Date / user filter -->
            <form method="get" class="bdct-filter">
                <input type="hidden" name="page" value="bdct-sessions">
                <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
                <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
                <label for="bdct-from"><?php esc_html_e( 'From', 'bitlence-dev-code-tracker' ); ?></label>
                <input type="date" id="bdct-from" name="bdct_from" value="<?php echo esc_attr( $from ); ?>" class="regular-text">
                <label for="bdct-to"><?php esc_html_e( 'To', 'bitlence-dev-code-tracker' ); ?></label>
                <input type="date" id="bdct-to" name="bdct_to" value="<?php echo esc_attr( $to ); ?>" class="regular-text">
                <?php if ( $is_team_viewer ) : ?>
                <label for="bdct-user"><?php esc_html_e( 'User', 'bitlence-dev-code-tracker' ); ?></label>
                <select id="bdct-user" name="bdct_user">
                    <option value="0"><?php esc_html_e( 'All Users', 'bitlence-dev-code-tracker' ); ?></option>
                    <?php foreach ( $tracked_users as $u ) : ?>
                    <option value="<?php echo esc_attr( $u['user_id'] ); ?>" <?php selected( $filter_user_id, (int) $u['user_id'] ); ?>>
                        <?php echo esc_html( $u['display_name'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'bitlence-dev-code-tracker' ); ?></button>
                <?php if ( $from || $to || ( $is_team_viewer && $filter_user_id ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bdct-sessions' ) ); ?>" class="button button-secondary bdct-btn-reset">
                    <?php esc_html_e( 'Clear Filter', 'bitlence-dev-code-tracker' ); ?>
                </a>
                <?php endif; ?>
            </form>

            <div class="bdct-page-actions">
                <p class="description">
                    <?php
                    printf(
                        /* translators: %d: total number of recorded sessions */
                        esc_html__( '%d sessions total. Shows completed sessions — the active session saves when you leave the page.', 'bitlence-dev-code-tracker' ),
                        absint( $total )
                    );
                    ?>
                </p>
                <span style="display:flex;gap:8px;align-items:center">
                    <a href="<?php echo esc_url( add_query_arg( '' ) ); ?>" class="button button-secondary">
                        <?php esc_html_e( 'Refresh', 'bitlence-dev-code-tracker' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
                        <?php
                        printf(
                            /* translators: %d: number of sessions to export */
                            esc_html__( 'Export CSV (%d)', 'bitlence-dev-code-tracker' ),
                            absint( $total )
                        );
                        ?>
                    </a>
                </span>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo $sort_link( __( 'Started', 'bitlence-dev-code-tracker' ), 'started_at' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
                        <th><?php esc_html_e( 'User', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Page / Post', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Post ID', 'bitlence-dev-code-tracker' ); ?></th>
                        <th><?php echo $sort_link( __( 'Duration', 'bitlence-dev-code-tracker' ), 'duration_sec' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $sessions ) ) : ?>
                    <tr><td colspan="6" class="bdct-empty"><?php esc_html_e( 'No sessions recorded yet.', 'bitlence-dev-code-tracker' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $sessions as $s ) : $dur = self::format_duration( (int) $s['duration_sec'] ); ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'd-m-Y g:i A', strtotime( $s['started_at'] ) ) ); ?></td>
                        <td><?php echo esc_html( $s['user_name'] ?? '-' ); ?></td>
                        <td>
                            <?php
                            $bdct_label    = self::format_label( $s['page_label'], $s['admin_page'] ?? '' );
                            $bdct_edit_url = ! empty( $s['post_id'] ) ? get_edit_post_link( (int) $s['post_id'] ) : null;
                            if ( $bdct_edit_url ) {
                                echo '<a href="' . esc_url( $bdct_edit_url ) . '">' . esc_html( $bdct_label ) . '</a>';
                            } else {
                                echo esc_html( $bdct_label );
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $s['post_type'] ?? '-' ); ?></td>
                        <td><?php echo $s['post_id'] ? esc_html( $s['post_id'] ) : '-'; ?></td>
                        <td><?php echo esc_html( $dur ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
            <div class="bdct-pagination">
                <?php
                echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $current_page,
                    'total'   => $total_pages,
                ] );
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function format_label( string $page_label, string $admin_page ): string {
        static $map = [
            'bdct'              => 'Dev Code Tracker',
            'bdct-sessions'     => 'Sessions Log',
            'bdct-settings'     => 'DCT Settings',
            'edit'              => 'Posts List',
            'post'              => 'Post Editor',
            'upload'            => 'Media Library',
            'plugins'           => 'Plugins',
            'themes'            => 'Themes',
            'options-general'   => 'General Settings',
            'options-writing'   => 'Writing Settings',
            'options-reading'   => 'Reading Settings',
            'options-permalink' => 'Permalinks',
            'users'             => 'Users',
            'profile'           => 'Profile',
            'tools'             => 'Tools',
            'woocommerce'       => 'WooCommerce',
        ];
        // page_label is already a post title when it differs from admin_page slug.
        if ( $page_label && $page_label !== $admin_page && $page_label !== 'Unknown' ) {
            return $page_label;
        }
        return $map[ $admin_page ] ?? ( $page_label ?: $admin_page ?: 'Unknown' );
    }

    private static function format_duration( int $sec ): string {
        $h  = intdiv( $sec, 3600 );
        $m  = intdiv( $sec % 3600, 60 );
        $s2 = $sec % 60;
        return $h > 0
            ? sprintf( '%dh %dm %ds', $h, $m, $s2 )
            : sprintf( '%dm %ds', $m, $s2 );
    }

    public static function maybe_export_csv(): void {
        if ( empty( $_GET['bdct_export'] ) || 'csv' !== $_GET['bdct_export'] ) {
            return;
        }
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }
        check_admin_referer( 'bdct_export_csv' );

        $is_team_viewer = current_user_can( 'manage_options' );

        $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
        $from = sanitize_text_field( wp_unslash( $_GET['bdct_from'] ?? '' ) );
        $to   = sanitize_text_field( wp_unslash( $_GET['bdct_to']   ?? '' ) );
        $from = preg_match( $date_pattern, $from ) ? $from : '';
        $to   = preg_match( $date_pattern, $to )   ? $to   : '';

        $filter_user_id = $is_team_viewer ? absint( wp_unslash( $_GET['bdct_user'] ?? 0 ) ) : get_current_user_id();

        $sessions = BDCT_DB::get_sessions( $filter_user_id, 10000, 0, $from, $to, 'started_at', 'DESC' );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="dev-code-tracker-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $out    = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $header = [ 'Started', 'User', 'Page / Post', 'Type', 'Post ID', 'Duration (sec)', 'Duration' ];
        fputcsv( $out, $header );

        foreach ( $sessions as $s ) {
            $sec = (int) $s['duration_sec'];
            $row = [ wp_date( 'd-m-Y g:i A', strtotime( $s['started_at'] ) ), $s['user_name'] ?? '' ];
            array_push(
                $row,
                $s['page_label'],
                $s['post_type'] ?? '',
                $s['post_id'] ?: '',
                $sec,
                self::format_duration( $sec )
            );
            fputcsv( $out, $row );
        }

        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    public static function maybe_export_team_csv(): void {
        if ( empty( $_GET['bdct_export'] ) || 'team_csv' !== $_GET['bdct_export'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'bitlence-dev-code-tracker' ) );
        }
        check_admin_referer( 'bdct_export_team_csv' );

        $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
        $from = sanitize_text_field( wp_unslash( $_GET['bdct_from'] ?? '' ) );
        $to   = sanitize_text_field( wp_unslash( $_GET['bdct_to']   ?? '' ) );
        $from = preg_match( $date_pattern, $from ) ? $from : '';
        $to   = preg_match( $date_pattern, $to )   ? $to   : '';
        $has_range = ( $from !== '' || $to !== '' );

        $rows = BDCT_DB::get_team_summary( $from, $to );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="dev-code-tracker-team-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $out    = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $header = [ 'User', 'Today', 'This Week', 'All Time' ];
        if ( $has_range ) {
            $header[] = 'Selected Range';
        }
        fputcsv( $out, $header );

        foreach ( $rows as $r ) {
            $row = [
                $r['display_name'],
                self::format_duration( (int) $r['today_sec'] ),
                self::format_duration( (int) $r['week_sec'] ),
                self::format_duration( (int) $r['all_sec'] ),
            ];
            if ( $has_range ) {
                $row[] = self::format_duration( (int) ( $r['range_sec'] ?? 0 ) );
            }
            fputcsv( $out, $row );
        }

        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
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

            <hr>

            <div class="bdct-section bdct-section--danger" style="margin-top:24px">
                <h2><?php esc_html_e( 'Danger Zone', 'bitlence-dev-code-tracker' ); ?></h2>
                <p><?php esc_html_e( 'Permanently deletes all your tracked sessions and daily summaries. This cannot be undone.', 'bitlence-dev-code-tracker' ); ?></p>
                <button id="bdct-clear-btn" class="button button-link-delete">
                    <?php esc_html_e( 'Clear My Data', 'bitlence-dev-code-tracker' ) ; ?>
                </button>
            </div>
        </div>

        <script>
        (function () {
            var btn = document.getElementById( 'bdct-clear-btn' );
            if ( ! btn ) return;
            btn.addEventListener( 'click', function () {
                var cfg = window.bdctConfig || {};
                if ( ! cfg.ajaxUrl ) { alert( 'Configuration not loaded — please reload the page.' ); return; }
                if ( ! confirm( '<?php echo esc_js( __( 'Delete all your tracked data? This cannot be undone.', 'bitlence-dev-code-tracker' ) ); ?>' ) ) return;
                var fd = new FormData();
                fd.append( 'action', 'bdct_clear_user_data' );
                fd.append( 'nonce',  cfg.nonce );
                fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
                    .then( function ( r ) { return r.json(); } )
                    .then( function () { location.reload(); } );
            } );
        }());
        </script>
        <?php
    }

    public static function elementor_editor_footer(): void {
        if ( ! BDCT_Settings::is_tracked_role() ) {
            return;
        }
        global $wp_scripts;
        if ( ! empty( $wp_scripts->done ) && in_array( 'bdct-tracker', $wp_scripts->done, true ) ) {
            return;
        }
        // elementor/editor/footer fires during admin_action_elementor (inside admin_init),
        // which is BEFORE admin_enqueue_scripts. The script may not be registered yet,
        // so this method must be fully self-contained.
        if ( ! wp_script_is( 'bdct-tracker', 'registered' ) ) {
            wp_register_script( 'bdct-tracker', BDCT_PLUGIN_URL . 'assets/tracker.js', [], BDCT_VERSION, true );
        }
        $config = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'bdct_nonce' ),
            'idleMs'        => BDCT_Settings::idle_ms(),
            'minSessionSec' => BDCT_Settings::min_session_sec(),
            'todaySec'      => BDCT_DB::get_today_sec( get_current_user_id() ),
        ];
        wp_localize_script( 'bdct-tracker', 'bdctConfig', $config );
        wp_enqueue_script( 'bdct-tracker' );
        wp_scripts()->do_items( [ 'bdct-tracker' ], 1 );
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
            echo '<p class="bdct-empty" style="margin:8px 0">' . esc_html__( 'No activity yet today.', 'bitlence-dev-code-tracker' ) . '</p>';
        } else {
            $h = intdiv( $sec, 3600 );
            $m = intdiv( $sec % 3600, 60 );
            printf( '<p class="bdct-widget-total">%dh %dm</p>', absint( $h ), absint( $m ) );
        }
        printf(
            '<p class="bdct-widget-link"><a href="%s">%s</a></p>',
            esc_url( admin_url( 'admin.php?page=bdct' ) ),
            esc_html__( 'Full Dashboard →', 'bitlence-dev-code-tracker' )
        );
    }
}
