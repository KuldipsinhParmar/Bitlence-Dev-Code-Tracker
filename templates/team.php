<?php
defined( 'ABSPATH' ) || exit;
$bdct_team_export_base = wp_nonce_url( admin_url( 'admin.php?page=bdct-team&bdct_export=team_csv' ), 'bdct_export_team_csv' );
?>
<div class="wrap" id="bdct-team-page">
    <h1><?php esc_html_e( 'Team Overview', 'bitlence-dev-code-tracker' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Time logged by every tracked user on this site.', 'bitlence-dev-code-tracker' ); ?></p>

    <div class="bdct-filter">
        <label for="bdct-team-from"><?php esc_html_e( 'From', 'bitlence-dev-code-tracker' ); ?></label>
        <input type="date" id="bdct-team-from" class="regular-text">
        <label for="bdct-team-to"><?php esc_html_e( 'To', 'bitlence-dev-code-tracker' ); ?></label>
        <input type="date" id="bdct-team-to" class="regular-text">
        <button type="button" id="bdct-team-apply" class="button button-primary"><?php esc_html_e( 'Apply', 'bitlence-dev-code-tracker' ); ?></button>
        <button type="button" id="bdct-team-clear" class="button button-secondary bdct-btn-reset"><?php esc_html_e( 'Clear', 'bitlence-dev-code-tracker' ); ?></button>
        <a id="bdct-team-export" class="button button-secondary" data-base-href="<?php echo esc_attr( $bdct_team_export_base ); ?>" href="<?php echo esc_attr( $bdct_team_export_base ); ?>">
            <?php esc_html_e( 'Export CSV', 'bitlence-dev-code-tracker' ); ?>
        </a>
    </div>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'User', 'bitlence-dev-code-tracker' ); ?></th>
                <th><?php esc_html_e( 'Today', 'bitlence-dev-code-tracker' ); ?></th>
                <th><?php esc_html_e( 'This Week', 'bitlence-dev-code-tracker' ); ?></th>
                <th><?php esc_html_e( 'All Time', 'bitlence-dev-code-tracker' ); ?></th>
                <th id="bdct-team-range-th" style="display:none"><?php esc_html_e( 'Selected Range', 'bitlence-dev-code-tracker' ); ?></th>
            </tr>
        </thead>
        <tbody id="bdct-team-body">
            <tr><td colspan="4" class="bdct-empty"><?php esc_html_e( 'Loading…', 'bitlence-dev-code-tracker' ); ?></td></tr>
        </tbody>
    </table>
</div>
