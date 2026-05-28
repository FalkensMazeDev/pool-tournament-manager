<?php
/**
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin tables, options, and roles.
 *
 * This file is only executed when a site admin clicks "Delete" on the
 * Plugins screen — NOT on deactivation.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Load just what we need — no full WP bootstrap
require_once plugin_dir_path( __FILE__ ) . 'includes/class-install.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-roles.php';

// Drop all custom tables
PTM_Install::drop_tables();

// Remove custom roles and admin capabilities
PTM_Roles::remove_roles();

// Remove plugin options
delete_option( 'ptm_db_version' );
delete_option( 'ptm_caps_version' );
