<?php
/**
 * Plugin Name: Pool Tournament Manager
 * Plugin URI:  https://github.com/pool-tournament-manager
 * Description: Pool tournament management system. Supports 8-ball, 9-ball, and 10-ball pool tournaments with single and double elimination brackets, live scoring, and public spectator views.
 * Version:     1.0.0
 * Author:      Pool Tournament Manager
 * Text Domain: ptm-tournaments
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'PTM_VERSION',     '1.0.0' );
define( 'PTM_PLUGIN_FILE', __FILE__ );
define( 'PTM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PTM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'PTM_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// ── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register( function ( $class ) {
    $map = [
        'PTM_Install'    => 'includes/class-install.php',
        'PTM_Roles'      => 'includes/class-roles.php',
        'PTM_Player'     => 'includes/class-player.php',
        'PTM_Tournament' => 'includes/class-tournament.php',
        'PTM_Match'      => 'includes/class-match.php',
        'PTM_Bracket'    => 'includes/class-bracket.php',
        'PTM_REST'       => 'includes/class-rest.php',
        'PTM_Tables'     => 'includes/class-tables.php',
        'PTM_QR'         => 'includes/class-qr.php',
        'PTM_Settings'   => 'includes/class-settings.php',
        'PTM_Admin'      => 'admin/class-admin.php',
        'PTM_Public'     => 'public/class-public.php',
    ];

    if ( isset( $map[ $class ] ) ) {
        require_once PTM_PLUGIN_DIR . $map[ $class ];
    }
} );

// ── Activation / Deactivation ─────────────────────────────────────────────
register_activation_hook( __FILE__, [ 'PTM_Install', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PTM_Install', 'deactivate' ] );

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'ptm_boot' );

function ptm_boot() {
    // Run any pending DB upgrades
    PTM_Install::maybe_upgrade();

    // Front-end (shortcodes, scorer URL, public assets)
    new PTM_Public();

    // REST API (polling + scorer token endpoints)
    new PTM_REST();

    // Admin UI — only load in the dashboard
    if ( is_admin() ) {
        new PTM_Admin();
    }
}
