<?php
defined( 'ABSPATH' ) || exit;

class BDCT_Settings {

    const OPTION_GROUP = 'bdct_settings';
    const PAGE         = 'bdct-settings';

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function register(): void {
        register_setting( self::OPTION_GROUP, 'bdct_idle_timeout',    [ 'type' => 'integer', 'default' => 5,   'sanitize_callback' => 'absint' ] );
        register_setting( self::OPTION_GROUP, 'bdct_min_session_sec', [ 'type' => 'integer', 'default' => 30,  'sanitize_callback' => 'absint' ] );
        register_setting( self::OPTION_GROUP, 'bdct_track_roles',     [ 'type' => 'array',   'default' => [ 'administrator', 'editor' ], 'sanitize_callback' => [ __CLASS__, 'sanitize_roles' ] ] );

        add_settings_section( 'bdct_main', __( 'Tracking', 'bitlence-dev-code-tracker' ), '__return_false', self::PAGE );

        add_settings_field( 'bdct_idle_timeout',    __( 'Idle Timeout (minutes)', 'bitlence-dev-code-tracker' ),   [ __CLASS__, 'field_number' ], self::PAGE, 'bdct_main', [ 'option' => 'bdct_idle_timeout',    'min' => 1 ] );
        add_settings_field( 'bdct_min_session_sec', __( 'Min Session Length (sec)', 'bitlence-dev-code-tracker' ), [ __CLASS__, 'field_number' ], self::PAGE, 'bdct_main', [ 'option' => 'bdct_min_session_sec', 'min' => 0 ] );
        add_settings_field( 'bdct_track_roles',     __( 'Track These Roles', 'bitlence-dev-code-tracker' ),        [ __CLASS__, 'field_roles' ],  self::PAGE, 'bdct_main' );
    }

    private const DEFAULTS = [
        'bdct_idle_timeout'    => 5,
        'bdct_min_session_sec' => 30,
    ];

    public static function field_number( array $args ): void {
        $default = self::DEFAULTS[ $args['option'] ] ?? 0;
        $val     = (int) get_option( $args['option'], $default );
        echo '<input type="number" name="' . esc_attr( $args['option'] ) . '" value="' . esc_attr( (string) $val ) . '"'
            . ( isset( $args['min'] ) ? ' min="' . esc_attr( (string) $args['min'] ) . '"' : '' )
            . ' class="small-text">';
    }

    public static function field_roles(): void {
        $saved = (array) get_option( 'bdct_track_roles', [ 'administrator', 'editor' ] );
        foreach ( wp_roles()->get_names() as $slug => $name ) {
            echo '<label><input type="checkbox" name="bdct_track_roles[]" value="' . esc_attr( $slug ) . '"'
                . checked( in_array( $slug, $saved, true ), true, false )
                . '> ' . esc_html( translate_user_role( $name ) ) . '</label>&nbsp; ';
        }
    }

    public static function sanitize_roles( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $valid = array_keys( wp_roles()->get_names() );
        return array_values( array_intersect( array_map( 'sanitize_key', $value ), $valid ) );
    }

    public static function idle_ms(): int {
        return (int) get_option( 'bdct_idle_timeout', 5 ) * 60 * 1000;
    }

    public static function min_session_sec(): int {
        return (int) get_option( 'bdct_min_session_sec', 30 );
    }

    public static function is_tracked_role(): bool {
        $user  = wp_get_current_user();
        $roles = (array) get_option( 'bdct_track_roles', [ 'administrator', 'editor' ] );
        return (bool) array_intersect( (array) $user->roles, $roles );
    }
}
