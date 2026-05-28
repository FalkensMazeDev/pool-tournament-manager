<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages custom WordPress roles and capabilities for Pool Tournament Manager.
 *
 * Roles:
 *   ptm_organizer  — Can manage tournaments, players, brackets, and scores
 *                    via the WP admin. Assigned by a site administrator.
 *
 * Table Scorers do NOT get a WP role — they access score entry via a
 * private tokenised URL with no login required.
 */
class PTM_Roles {

    const DB_CAP_OPTION = 'ptm_caps_version';
    const CAP_VERSION   = '1.0';

    // All capabilities granted to an organizer (and to admins)
    const ORGANIZER_CAPS = [
        'ptm_view_tournament_admin' => true,  // read-only dashboard access
        'ptm_manage_tournaments'    => true,  // create / edit / delete tournaments
        'ptm_manage_players'        => true,  // create / edit / delete player registry
        'ptm_enter_scores'          => true,  // enter scores via admin bracket view
        'ptm_manage_brackets'       => true,  // generate / reset brackets
    ];

    /** Called on plugins_loaded (no-op at runtime; roles exist in the DB). */
    public static function init() {}

    /** Registers the role and syncs admin caps. Run on plugin activation. */
    public static function add_roles() {
        remove_role( 'ptm_organizer' ); // always re-create so caps stay fresh

        add_role(
            'ptm_organizer',
            __( 'Tournament Organizer', 'ptm-tournaments' ),
            array_merge( [ 'read' => true ], self::ORGANIZER_CAPS )
        );

        self::sync_admin_caps( true );
        update_option( self::DB_CAP_OPTION, self::CAP_VERSION );
    }

    /** Removes the role and revokes admin caps. Run on plugin uninstall. */
    public static function remove_roles() {
        remove_role( 'ptm_organizer' );
        self::sync_admin_caps( false );
        delete_option( self::DB_CAP_OPTION );
    }

    // ── Capability checks ─────────────────────────────────────────────────

    public static function can_view_tournament_admin(): bool {
        return current_user_can( 'ptm_view_tournament_admin' )
            || current_user_can( 'manage_options' );
    }

    public static function can_manage_tournaments(): bool {
        return current_user_can( 'ptm_manage_tournaments' )
            || current_user_can( 'manage_options' );
    }

    public static function can_manage_players(): bool {
        return current_user_can( 'ptm_manage_players' )
            || current_user_can( 'manage_options' );
    }

    public static function can_enter_scores(): bool {
        return current_user_can( 'ptm_enter_scores' )
            || current_user_can( 'manage_options' );
    }

    public static function can_manage_brackets(): bool {
        return current_user_can( 'ptm_manage_brackets' )
            || current_user_can( 'manage_options' );
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private static function sync_admin_caps( bool $add ): void {
        $admin = get_role( 'administrator' );
        if ( ! $admin ) {
            return;
        }
        foreach ( self::ORGANIZER_CAPS as $cap => $value ) {
            $add ? $admin->add_cap( $cap ) : $admin->remove_cap( $cap );
        }
    }
}
