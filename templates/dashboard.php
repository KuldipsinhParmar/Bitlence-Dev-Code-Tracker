<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap" id="bdct-dashboard">
    <h1><?php esc_html_e( 'Dev Code Tracker', 'bitlence-dev-code-tracker' ); ?></h1>

    <!-- Stat cards -->
    <div class="bdct-cards">
        <?php
        foreach ( [
            [ __( 'Today',     'bitlence-dev-code-tracker' ), 'bdct-today',   '—',     false ],
            [ __( 'This Week', 'bitlence-dev-code-tracker' ), 'bdct-week',    '—',     false ],
            [ __( 'All Time',  'bitlence-dev-code-tracker' ), 'bdct-alltime', '—',     false ],
            [ '🔥 ' . __( 'Streak', 'bitlence-dev-code-tracker' ), 'bdct-streak', '0 days', true ],
        ] as [ $bdct_label, $bdct_card_id, $bdct_default, $bdct_has_risk ] ) :
        ?>
        <div class="bdct-card">
            <div class="bdct-card-label"><?php echo esc_html( $bdct_label ); ?></div>
            <div id="<?php echo esc_attr( $bdct_card_id ); ?>" class="bdct-card-value"><?php echo esc_html( $bdct_default ); ?></div>
            <?php if ( $bdct_has_risk ) : ?>
            <span id="bdct-streak-risk" class="bdct-streak-risk"></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 30-day bar chart -->
    <div class="bdct-section">
        <h2><?php esc_html_e( 'Last 30 Days', 'bitlence-dev-code-tracker' ); ?></h2>
        <p id="bdct-chart-msg"></p>
        <canvas id="bdct-chart" height="70"></canvas>
    </div>

    <!-- Per-page breakdown -->
    <div class="bdct-section">
        <h2><?php esc_html_e( 'By Page / Post', 'bitlence-dev-code-tracker' ); ?></h2>

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
                <tr><td colspan="5" class="bdct-empty"><?php esc_html_e( 'Loading…', 'bitlence-dev-code-tracker' ); ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Recent sessions -->
    <div class="bdct-section">
        <h2><?php esc_html_e( 'Last 10 Sessions', 'bitlence-dev-code-tracker' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Started', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Page / Post', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Post ID', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Post Type', 'bitlence-dev-code-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Duration', 'bitlence-dev-code-tracker' ); ?></th>
                </tr>
            </thead>
            <tbody id="bdct-recent-body">
                <tr><td colspan="5" class="bdct-empty"><?php esc_html_e( 'Loading…', 'bitlence-dev-code-tracker' ); ?></td></tr>
            </tbody>
        </table>
    </div>

</div>
