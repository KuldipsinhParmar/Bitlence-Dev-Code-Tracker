<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap" id="dct-dashboard">
    <h1>Dev Code Tracker</h1>

    <!-- Stat cards -->
    <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap">
        <?php
        foreach ( [
            [ 'Today',    'dct-today',   '—' ],
            [ 'This Week','dct-week',    '—' ],
            [ 'All Time', 'dct-alltime', '—' ],
            [ '🔥 Streak', 'dct-streak', '0 days' ],
        ] as [ $label, $id, $placeholder ] ) :
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 24px;min-width:140px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.05)">
            <div style="font-size:0.8em;color:#777;text-transform:uppercase;letter-spacing:.05em"><?php echo esc_html( $label ); ?></div>
            <div id="<?php echo esc_attr( $id ); ?>" style="font-size:1.8em;font-weight:700;margin-top:4px"><?php echo esc_html( $placeholder ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 30-day bar chart -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
        <h2 style="margin-top:0;font-size:1em;color:#1d2327">Last 30 Days</h2>
        <p id="dct-chart-msg" style="display:none;text-align:center;color:#888;margin:20px 0"></p>
        <canvas id="dct-chart" height="70"></canvas>
    </div>

    <!-- Per-page breakdown -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
        <h2 style="margin-top:0;font-size:1em;color:#1d2327">By Page / Post</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Page / Post Title</th>
                    <th>Type</th>
                    <th>Post ID</th>
                    <th>Time</th>
                    <th>Sessions</th>
                </tr>
            </thead>
            <tbody id="dct-perpage-body">
                <tr><td colspan="5" style="text-align:center;color:#888">Loading…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Recent sessions -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
        <h2 style="margin-top:0;font-size:1em;color:#1d2327">Recent Sessions</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Started</th>
                    <th>Admin Page</th>
                    <th>Post ID</th>
                    <th>Post Type</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody id="dct-recent-body">
                <tr><td colspan="5" style="text-align:center;color:#888">Loading…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Danger zone -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin-bottom:20px">
        <h2 style="margin-top:0;font-size:1em;color:#b32d2e">Danger Zone</h2>
        <p style="color:#555;margin-top:0">Permanently deletes all your tracked sessions, daily summaries, and project labels.</p>
        <button id="dct-clear-btn" class="button button-link-delete">Clear My Data</button>
    </div>
</div>

<script>
( function () {
    document.getElementById( 'dct-clear-btn' ).addEventListener( 'click', function () {
        // Read dctConfig at click time — footer scripts may not have run yet at parse time.
        var cfg = window.dctConfig || {};
        if ( ! cfg.ajaxUrl ) { alert( 'Configuration not loaded — please reload the page.' ); return; }
        if ( ! confirm( 'Delete all your tracked data? This cannot be undone.' ) ) return;
        var fd = new FormData();
        fd.append( 'action', 'dct_clear_user_data' );
        fd.append( 'nonce',  cfg.nonce );
        fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
            .then( function ( r ) { return r.json(); } )
            .then( function () { location.reload(); } );
    } );
} () );
</script>
