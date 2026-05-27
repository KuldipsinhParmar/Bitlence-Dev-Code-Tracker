<?php
defined( 'ABSPATH' ) || exit;

class DCT_Settings {

    const OPTION_GROUP = 'dct_settings';
    const PAGE         = 'dct-settings';

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
    }

    public static function register(): void {
        register_setting( self::OPTION_GROUP, 'dct_idle_timeout',    [ 'type' => 'integer', 'default' => 5,   'sanitize_callback' => 'absint' ] );
        register_setting( self::OPTION_GROUP, 'dct_min_session_sec', [ 'type' => 'integer', 'default' => 60, 'sanitize_callback' => 'absint' ] );
        register_setting( self::OPTION_GROUP, 'dct_track_roles',     [ 'type' => 'array',   'default' => [ 'administrator', 'editor' ], 'sanitize_callback' => [ __CLASS__, 'sanitize_roles' ] ] );

        add_settings_section( 'dct_main', 'Tracking', '__return_false', self::PAGE );

        add_settings_field( 'dct_idle_timeout',    'Idle Timeout (minutes)',   [ __CLASS__, 'field_number' ], self::PAGE, 'dct_main', [ 'option' => 'dct_idle_timeout',    'min' => 1 ] );
        add_settings_field( 'dct_min_session_sec', 'Min Session Length (sec)', [ __CLASS__, 'field_number' ], self::PAGE, 'dct_main', [ 'option' => 'dct_min_session_sec', 'min' => 0 ] );
        add_settings_field( 'dct_track_roles',     'Track These Roles',        [ __CLASS__, 'field_roles' ],  self::PAGE, 'dct_main' );
    }

    private const DEFAULTS = [
        'dct_idle_timeout'    => 5,
        'dct_min_session_sec' => 60,
    ];

    public static function field_number( array $args ): void {
        $default = self::DEFAULTS[ $args['option'] ] ?? 0;
        $val     = (int) get_option( $args['option'], $default );
        $min     = isset( $args['min'] ) ? "min=\"{$args['min']}\"" : '';
        echo "<input type=\"number\" name=\"{$args['option']}\" value=\"{$val}\" {$min} class=\"small-text\">";
    }

    public static function field_roles(): void {
        $saved = (array) get_option( 'dct_track_roles', [ 'administrator', 'editor' ] );
        foreach ( wp_roles()->get_names() as $slug => $name ) {
            $checked = in_array( $slug, $saved, true ) ? 'checked' : '';
            $label   = esc_html( translate_user_role( $name ) );
            echo "<label><input type=\"checkbox\" name=\"dct_track_roles[]\" value=\"{$slug}\" {$checked}> {$label}</label>&nbsp; ";
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
        return (int) get_option( 'dct_idle_timeout', 5 ) * 60 * 1000;
    }

    public static function min_session_sec(): int {
        return (int) get_option( 'dct_min_session_sec', 60 );
    }

    public static function is_tracked_role(): bool {
        $user  = wp_get_current_user();
        $roles = (array) get_option( 'dct_track_roles', [ 'administrator', 'editor' ] );
        return (bool) array_intersect( (array) $user->roles, $roles );
    }
}
