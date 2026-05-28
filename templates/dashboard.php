<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap" id="bdct-dashboard">
    <h1><?php esc_html_e( 'Dev Code Tracker', 'bitlence-dev-code-tracker' ); ?></h1>

    <!-- Stat cards -->
    <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap">
        <?php
        foreach ( [
            [ __( 'Today',     'bitlence-dev-code-tracker' ), 'bdct-today',   '—' ],
            [ __( 'This Week', 'bitlence-dev-code-tracker' ), 'bdct-week',    '—' ],
            [ __( 'All Time',  'bitlence-dev-code-tracker' ), 'bdct-alltime', '—' ],
            [ '🔥 ' . __( 'Streak', 'bitlence-dev-code-tracker' ), 'bdct-streak', '0 days' ],
        ] as [ $bdct_label, $bdct_card_id, $bdct_default ] ) :
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 24px;min-width:140px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.05)">
            <div style="font-size:0.8em;color:#777;text-transform:uppercase;letter-spacing:.05em"><?php echo esc_html( $bdct_label ); ?></div>
            <div id="<?php echo esc_attr( $bdct_card_id ); ?>" style="font-size:1.8em;font-weight:700;margin-top:4px"><?php echo esc_html( $bdct_default ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 30-day bar chart -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
        <h2 style="margin-top:0;font-size:1em;color:#1d2327"><?php esc_html_e( 'Last 30 Days', 'bitlence-dev-code-tracker' ); ?></h2>
        <p id="bdct-chart-msg" style="display:none;text-align:center;color:#888;margin:20px 0"></p>
        <canvas id="bdct-chart" height="70"></canvas>
    </div>

    <!-- Per-page breakdown -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
        <h2 style="margin-top:0;font-size:1em;color:#1d2327"><?php esc_html_e( 'By Page / Post', 'bitlence-dev-code-tracker' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Page / Post Title', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Post ID', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Time', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Sessions', 'bitlence-dev-code-tracker' ); ?></th>
                </tr>
            </thead>
            <tbody id="bdct-perpage-body">
                <tr><td colspan="5" style="text-align:center;color:#888"><?php esc_html_e( 'Loading…', 'bitlence-dev-code-tracker' ); ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Recent sessions -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
        <h2 style="margin-top:0;font-size:1em;color:#1d2327"><?php esc_html_e( 'Recent Sessions', 'bitlence-dev-code-tracker' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Started', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Admin Page', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Post ID', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Post Type', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Duration', 'bitlence-dev-code-tracker' ); ?></th>
                </tr>
            </thead>
            <tbody id="bdct-recent-body">
                <tr><td colspan="5" style="text-align:center;color:#888"><?php esc_html_e( 'Loading…', 'bitlence-dev-code-tracker' ); ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Danger zone -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px">
        <h2 style="margin-top:0;font-size:1em;color:#b32d2e"><?php esc_html_e( 'Danger Zone', 'bitlence-dev-code-tracker' ); ?></h2>
        <p style="color:#555;margin-top:0"><?php esc_html_e( 'Permanently deletes all your tracked sessions, daily summaries, and project labels.', 'bitlence-dev-code-tracker' ); ?></p>
        <button id="bdct-clear-btn" class="button button-link-delete"><?php esc_html_e( 'Clear My Data', 'bitlence-dev-code-tracker' ); ?></button>
    </div>
</div>

<script>
( function () {
    document.getElementById( 'bdct-clear-btn' ).addEventListener( 'click', function () {
        // Read bdctConfig at click time — footer scripts may not have run yet at parse time.
        var cfg = window.bdctConfig || {};
        if ( ! cfg.ajaxUrl ) { alert( 'Configuration not loaded — please reload the page.' ); return; }
        if ( ! confirm( 'Delete all your tracked data? This cannot be undone.' ) ) return;
        var fd = new FormData();
        fd.append( 'action', 'bdct_clear_user_data' );
        fd.append( 'nonce',  cfg.nonce );
        fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
            .then( function ( r ) { return r.json(); } )
            .then( function () { location.reload(); } );
    } );
} () );
</script>
