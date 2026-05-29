<?php
defined( 'ABSPATH' ) || exit;

/**
 * PTM_Settings
 * Manages global plugin settings stored in wp_options.
 * Accessed via Tournaments → Settings in the admin menu.
 */
class PTM_Settings {

    const OPTION_KEY = 'ptm_settings';

    // Default values for all settings
    const DEFAULTS = [
        'tournament_base_slug'  => 'tournament',   // URL: /tournament/{slug}/
        'scorer_base_slug'      => 'ptm-score',    // URL: /ptm-score/{token}/
        'table_base_slug'       => 'ptm-table',    // URL: /ptm-table/{slug}/{table}/
        'results_sub_slug'      => 'results',      // URL: /tournament/{slug}/results/
        'poll_interval_ms'      => 5000,            // Spectator polling interval
        'default_num_tables'    => 4,               // Default tables per tournament
        'default_race_to'       => 5,               // Default race-to (winners)
        'default_race_to_losers'=> 4,               // Default race-to (losers)
        'default_entrance_fee'  => '0.00',          // Default entrance fee
        'show_prize_pot_public' => 1,               // Show prize pot on public pages
        'club_name'             => '',              // Shown in page titles / headers
        'head_scripts'          => '',              // Raw HTML injected before </head> on public pages
        'footer_scripts'        => '',              // Raw HTML injected before </body> on public pages
        'notification_from_name'  => '',            // From name for match notification emails
        'notification_from_email' => '',            // From address for match notification emails
    ];

    /**
     * Returns all settings, merged with defaults.
     */
    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : [] );
    }

    /**
     * Returns a single setting value.
     */
    public static function get( string $key ) {
        $settings = self::get_all();
        return $settings[ $key ] ?? ( self::DEFAULTS[ $key ] ?? null );
    }

    /**
     * Saves settings from a form POST array.
     */
    public static function save( array $post ): void {
        $clean = [
            'tournament_base_slug'   => self::sanitize_slug( $post['tournament_base_slug'] ?? 'tournament', 'tournament' ),
            'scorer_base_slug'       => self::sanitize_slug( $post['scorer_base_slug'] ?? 'ptm-score', 'ptm-score' ),
            'table_base_slug'        => self::sanitize_slug( $post['table_base_slug'] ?? 'ptm-table', 'ptm-table' ),
            'results_sub_slug'       => self::sanitize_slug( $post['results_sub_slug'] ?? 'results', 'results' ),
            'poll_interval_ms'       => max( 2000, min( 30000, absint( $post['poll_interval_ms'] ?? 5000 ) ) ),
            'default_num_tables'     => max( 1, min( 20, absint( $post['default_num_tables'] ?? 4 ) ) ),
            'default_race_to'        => max( 1, min( 20, absint( $post['default_race_to'] ?? 5 ) ) ),
            'default_race_to_losers' => max( 1, min( 20, absint( $post['default_race_to_losers'] ?? 4 ) ) ),
            'default_entrance_fee'   => max( 0, (float) ( $post['default_entrance_fee'] ?? 0 ) ),
            'show_prize_pot_public'  => ! empty( $post['show_prize_pot_public'] ) ? 1 : 0,
            'club_name'              => sanitize_text_field( $post['club_name'] ?? '' ),
            'head_scripts'           => wp_kses_post( $post['head_scripts'] ?? '' ),
            'footer_scripts'         => wp_kses_post( $post['footer_scripts'] ?? '' ),
            'notification_from_name'  => sanitize_text_field( $post['notification_from_name'] ?? '' ),
            'notification_from_email' => sanitize_email( $post['notification_from_email'] ?? '' ),
        ];
        update_option( self::OPTION_KEY, $clean );
        // Rewrite rules must be flushed when slugs change
        flush_rewrite_rules();
    }

    private static function sanitize_slug( string $val, string $default ): string {
        $slug = sanitize_title( $val );
        return $slug ?: $default;
    }
}
