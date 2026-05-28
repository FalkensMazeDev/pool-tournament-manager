<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTM_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_post_ptm_save_tournament',   [ $this, 'handle_save_tournament' ] );
        add_action( 'admin_post_ptm_save_settings',      [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_ptm_delete_tournament', [ $this, 'handle_delete_tournament' ] );
        add_action( 'admin_post_ptm_save_player',       [ $this, 'handle_save_player' ] );
        add_action( 'admin_post_ptm_delete_player',     [ $this, 'handle_delete_player' ] );
        add_action( 'admin_post_ptm_add_tournament_player',    [ $this, 'handle_add_tournament_player' ] );
        add_action( 'admin_post_ptm_remove_tournament_player', [ $this, 'handle_remove_tournament_player' ] );
        add_action( 'wp_ajax_ptm_player_search',        [ $this, 'ajax_player_search' ] );
        add_action( 'wp_ajax_ptm_admin_score',          [ $this, 'ajax_admin_score' ] );
        add_action( 'wp_ajax_ptm_correct_score',         [ $this, 'ajax_correct_score' ] );
        add_action( 'wp_ajax_ptm_finalize_results',      [ $this, 'ajax_finalize_results' ] );
    }

    // ----------------------------------------------------------------
    // Menus
    // ----------------------------------------------------------------

    public function register_menus() {
        add_menu_page(
            __( 'Pool Tournament Manager', 'ptm-tournaments' ),
            __( 'Tournaments', 'ptm-tournaments' ),
            'ptm_view_tournament_admin',
            'ptm-tournaments',
            [ $this, 'page_tournaments' ],
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'ptm-tournaments',
            __( 'All Tournaments', 'ptm-tournaments' ),
            __( 'All Tournaments', 'ptm-tournaments' ),
            'ptm_view_tournament_admin',
            'ptm-tournaments',
            [ $this, 'page_tournaments' ]
        );

        add_submenu_page(
            'ptm-tournaments',
            __( 'Add Tournament', 'ptm-tournaments' ),
            __( 'Add Tournament', 'ptm-tournaments' ),
            'ptm_manage_tournaments',
            'ptm-tournament-new',
            [ $this, 'page_tournament_edit' ]
        );

        add_submenu_page(
            'ptm-tournaments',
            __( 'Player Registry', 'ptm-tournaments' ),
            __( 'Player Registry', 'ptm-tournaments' ),
            'ptm_manage_players',
            'ptm-players',
            [ $this, 'page_players' ]
        );

        add_submenu_page(
            'ptm-tournaments',
            __( 'How It Works', 'ptm-tournaments' ),
            __( '📖 How It Works', 'ptm-tournaments' ),
            'ptm_view_tournament_admin',
            'ptm-docs',
            [ $this, 'page_docs' ]
        );

        add_submenu_page(
            'ptm-tournaments',
            __( 'Settings', 'ptm-tournaments' ),
            __( '⚙️ Settings', 'ptm-tournaments' ),
            'manage_options',
            'ptm-tournament-settings',
            [ $this, 'page_settings' ]
        );
    }

    // ----------------------------------------------------------------
    // Scripts & Styles
    // ----------------------------------------------------------------

    public function enqueue_scripts( $hook ) {
        $ptm_pages = [
            'toplevel_page_ptm-tournaments',
            'tournaments_page_ptm-tournament-new',
            'tournaments_page_ptm-players',
        ];

        if ( ! in_array( $hook, $ptm_pages, true ) && strpos( $hook, 'gdc' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ptm-admin',
            PTM_PLUGIN_URL . 'admin/css/admin.css',
            [],
            PTM_VERSION
        );

        wp_enqueue_script(
            'ptm-admin',
            PTM_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            PTM_VERSION,
            true
        );

        wp_localize_script( 'ptm-admin', 'PTM', [
            'nonce'    => wp_create_nonce( 'ptm_admin' ),  // for admin-ajax endpoints
            'restNonce'=> wp_create_nonce( 'wp_rest' ),    // for REST API endpoints
            'restUrl'  => rest_url( 'gdc/v1/' ),
            'adminUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    // ----------------------------------------------------------------
    // Page: Tournament List
    // ----------------------------------------------------------------

    public function page_tournaments() {
        if ( ! PTM_Roles::can_manage_tournaments() && ! current_user_can( 'ptm_view_tournament_admin' ) ) {
            wp_die( __( 'You do not have permission to view this page.', 'ptm-tournaments' ) );
        }

        // Sub-actions: edit, roster, bracket
        $action        = isset( $_GET['action'] )        ? sanitize_key( $_GET['action'] )        : 'list';
        $tournament_id = isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] )       : 0;

        if ( $action === 'edit' && $tournament_id ) {
            $this->page_tournament_edit( $tournament_id );
            return;
        }

        if ( $action === 'roster' && $tournament_id ) {
            $tournament = (object) PTM_Tournament::get( $tournament_id );
            $roster     = array_map( fn( $r ) => (object) $r, PTM_Player::get_tournament_players( $tournament_id ) );
            require_once PTM_PLUGIN_DIR . 'admin/views/roster.php';
            return;
        }

        if ( $action === 'bracket' && $tournament_id ) {
            $tournament = (object) PTM_Tournament::get( $tournament_id );
            $bracket    = PTM_Bracket::get_bracket( $tournament_id );
            // Cast each match row to object for the view
            foreach ( $bracket as $side => $rounds ) {
                foreach ( $rounds as $round => $matches ) {
                    $bracket[ $side ][ $round ] = array_map( fn( $m ) => (object) $m, $matches );
                }
            }
            require_once PTM_PLUGIN_DIR . 'admin/views/bracket.php';
            return;
        }

        $tournaments = array_map( fn( $t ) => (object) $t, PTM_Tournament::get_all( [ 'limit' => 100, 'order' => 'DESC' ] ) );
        require_once PTM_PLUGIN_DIR . 'admin/views/tournament-list.php';
    }

    // ----------------------------------------------------------------
    // Page: Tournament Edit / New
    // ----------------------------------------------------------------

    public function page_tournament_edit( $tournament_id = 0 ) {
        if ( ! PTM_Roles::can_manage_tournaments() ) {
            wp_die( __( 'You do not have permission to manage tournaments.', 'ptm-tournaments' ) );
        }

        if ( ! $tournament_id && isset( $_GET['tournament_id'] ) ) {
            $tournament_id = absint( $_GET['tournament_id'] );
        }

        $tournament_raw = $tournament_id ? PTM_Tournament::get( $tournament_id ) : null;
        $tournament     = $tournament_raw ? (object) $tournament_raw : null;
        $handicap_rules = $tournament_id ? PTM_Tournament::get_handicap_rules( $tournament_id ) : [];

        require_once PTM_PLUGIN_DIR . 'admin/views/tournament-edit.php';
    }

    // ----------------------------------------------------------------
    // Page: Player Registry
    // ----------------------------------------------------------------

    public function page_players() {
        if ( ! PTM_Roles::can_manage_players() ) {
            wp_die( __( 'You do not have permission to manage players.', 'ptm-tournaments' ) );
        }

        $action    = isset( $_GET['action'] )    ? sanitize_key( $_GET['action'] ) : 'list';
        $player_id = isset( $_GET['player_id'] ) ? absint( $_GET['player_id'] )    : 0;
        $player_raw = $player_id ? PTM_Player::get( $player_id ) : null;
        $player     = $player_raw ? (object) $player_raw : null;

        if ( $action === 'stats' && $player_id ) {
            $stats = array_map( fn( $s ) => (object) $s, PTM_Player::get_stats( $player_id ) );
            require_once PTM_PLUGIN_DIR . 'admin/views/player-stats.php';
            return;
        }

        $players = array_map( fn( $p ) => (object) $p, PTM_Player::get_all() );
        require_once PTM_PLUGIN_DIR . 'admin/views/players.php';
    }

    // ----------------------------------------------------------------
    // Page: Settings
    // ----------------------------------------------------------------

    public function page_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to manage settings.', 'ptm-tournaments' ) );
        }
        require_once PTM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function handle_save_settings() {
        check_admin_referer( 'ptm_save_settings' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        PTM_Settings::save( $_POST );

        wp_redirect( add_query_arg( [ 'page' => 'ptm-tournament-settings', 'saved' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ----------------------------------------------------------------
    // Page: Documentation
    // ----------------------------------------------------------------

    public function page_docs() {
        require_once PTM_PLUGIN_DIR . 'admin/views/docs.php';
    }

    // ----------------------------------------------------------------
    // Form Handlers
    // ----------------------------------------------------------------

    public function handle_save_tournament() {
        check_admin_referer( 'ptm_save_tournament' );

        if ( ! PTM_Roles::can_manage_tournaments() ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        $tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;

        $data = [
            'name'             => $_POST['name']             ?? '',
            'game_type'        => $_POST['game_type']        ?? '8ball',
            'bracket_type'     => $_POST['bracket_type']     ?? 'single_elim',
            'race_to_winners'  => $_POST['race_to_winners']  ?? 5,
            'race_to_losers'   => $_POST['race_to_losers']   ?? 4,
            'handicap_enabled' => ! empty( $_POST['handicap_enabled'] ) ? 1 : 0,
            'is_public'        => ! empty( $_POST['is_public'] )        ? 1 : 0,
            'num_tables'       => $_POST['num_tables'] ?? 4,
            'entrance_fee'     => $_POST['entrance_fee'] ?? 0,
            'slug'             => $_POST['slug'] ?? '',
            'tournament_date'  => $_POST['tournament_date']  ?? '',
        ];

        if ( $tournament_id ) {
            $result = PTM_Tournament::update( $tournament_id, $data );
        } else {
            $result = PTM_Tournament::create( $data );
            if ( ! is_wp_error( $result ) ) {
                $tournament_id = $result;
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( [ 'error' => urlencode( $result->get_error_message() ) ], wp_get_referer() ) );
            exit;
        }

        // Save payout rules
        $payout_rules = [];
        if ( ! empty( $_POST['payout_rules'] ) && is_array( $_POST['payout_rules'] ) ) {
            foreach ( $_POST['payout_rules'] as $rule ) {
                if ( isset( $rule['position_label'], $rule['position_from'], $rule['position_to'], $rule['pct'] ) ) {
                    $payout_rules[] = [
                        'position_label' => sanitize_text_field( $rule['position_label'] ),
                        'position_from'  => absint( $rule['position_from'] ),
                        'position_to'    => absint( $rule['position_to'] ),
                        'pct'            => (float) $rule['pct'],
                    ];
                }
            }
        }
        PTM_Tournament::save_payout_rules( $tournament_id, $payout_rules );

        // Save handicap rules if provided
        if ( ! empty( $_POST['handicap_rules'] ) && is_array( $_POST['handicap_rules'] ) ) {
            $rules = [];
            foreach ( $_POST['handicap_rules'] as $rule ) {
                if ( isset( $rule['skill_level_higher'], $rule['skill_level_lower'], $rule['race_to_higher'], $rule['race_to_lower'] ) ) {
                    $rules[] = [
                        'skill_level_higher' => absint( $rule['skill_level_higher'] ),
                        'skill_level_lower'  => absint( $rule['skill_level_lower'] ),
                        'race_to_higher'     => absint( $rule['race_to_higher'] ),
                        'race_to_lower'      => absint( $rule['race_to_lower'] ),
                    ];
                }
            }
            PTM_Tournament::save_handicap_rules( $tournament_id, $rules );
        }

        wp_redirect( add_query_arg( [
            'page'          => 'ptm-tournaments',
            'action'        => 'edit',
            'tournament_id' => $tournament_id,
            'saved'         => 1,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_delete_tournament() {
        check_admin_referer( 'ptm_delete_tournament' );

        if ( ! PTM_Roles::can_manage_tournaments() ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        $tournament_id = absint( $_POST['tournament_id'] ?? 0 );

        if ( ! $tournament_id ) {
            wp_die( __( 'Invalid tournament ID.', 'ptm-tournaments' ) );
        }

        $deleted = PTM_Tournament::delete( $tournament_id );

        if ( $deleted ) {
            wp_redirect( add_query_arg( [ 'page' => 'ptm-tournaments', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( [ 'page' => 'ptm-tournaments', 'delete_error' => 1 ], admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    public function handle_save_player() {
        check_admin_referer( 'ptm_save_player' );

        if ( ! PTM_Roles::can_manage_players() ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        $player_id = isset( $_POST['player_id'] ) ? absint( $_POST['player_id'] ) : 0;

        $data = [
            'name'  => $_POST['name']  ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
        ];

        if ( $player_id ) {
            PTM_Player::update( $player_id, $data );
        } else {
            PTM_Player::create( $data );
        }

        wp_redirect( add_query_arg( [ 'page' => 'ptm-players', 'saved' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_delete_player() {
        check_admin_referer( 'ptm_delete_player' );

        if ( ! PTM_Roles::can_manage_players() ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        $player_id = absint( $_POST['player_id'] ?? 0 );
        PTM_Player::delete( $player_id );

        wp_redirect( add_query_arg( [ 'page' => 'ptm-players', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_add_tournament_player() {
        check_admin_referer( 'ptm_roster' );

        if ( ! PTM_Roles::can_manage_tournaments() ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        $tournament_id = absint( $_POST['tournament_id'] ?? 0 );
        $player_id     = absint( $_POST['player_id']     ?? 0 );
        $skill_level   = isset( $_POST['skill_level'] ) && $_POST['skill_level'] !== '' ? absint( $_POST['skill_level'] ) : null;

        // Create new player on the fly if name is provided and no player_id
        if ( ! $player_id && ! empty( $_POST['new_player_name'] ) ) {
            $new_id = PTM_Player::create( [ 'name' => sanitize_text_field( $_POST['new_player_name'] ) ] );
            if ( ! is_wp_error( $new_id ) ) {
                $player_id = $new_id;
            }
        }

        if ( $player_id ) {
            PTM_Player::add_to_tournament( $tournament_id, $player_id, [ 'skill_level' => $skill_level ] );
        }

        wp_redirect( add_query_arg( [
            'page'          => 'ptm-tournaments',
            'action'        => 'roster',
            'tournament_id' => $tournament_id,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_remove_tournament_player() {
        check_admin_referer( 'ptm_roster' );

        if ( ! PTM_Roles::can_manage_tournaments() ) {
            wp_die( __( 'Permission denied.', 'ptm-tournaments' ) );
        }

        $tournament_id = absint( $_POST['tournament_id'] ?? 0 );
        $player_id     = absint( $_POST['player_id']     ?? 0 );

        PTM_Player::remove_from_tournament( $tournament_id, $player_id );

        wp_redirect( add_query_arg( [
            'page'          => 'ptm-tournaments',
            'action'        => 'roster',
            'tournament_id' => $tournament_id,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ----------------------------------------------------------------
    // AJAX
    // ----------------------------------------------------------------

    public function ajax_player_search() {
        check_ajax_referer( 'ptm_admin', 'nonce' );

        $search  = sanitize_text_field( $_GET['q'] ?? '' );
        $players = PTM_Player::get_all( [ 'search' => $search, 'limit' => 20 ] );

        $results = array_map( function( $p ) {
            // get_all() returns ARRAY_A rows
            return [ 'id' => (int) $p['id'], 'text' => $p['name'] ];
        }, $players );

        wp_send_json_success( $results );
    }

    public function ajax_correct_score() {
        check_ajax_referer( 'ptm_admin', 'nonce' );

        if ( ! PTM_Roles::can_enter_scores() ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
            return;
        }

        $match_id = absint( $_POST['match_id'] ?? 0 );
        $p1_score = absint( $_POST['p1_score'] ?? 0 );
        $p2_score = absint( $_POST['p2_score'] ?? 0 );

        if ( ! $match_id ) {
            wp_send_json_error( [ 'message' => 'Invalid match ID.' ] );
            return;
        }

        $result = PTM_Match::correct_score( $match_id, $p1_score, $p2_score );

        if ( ! empty( $result['error'] ) ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
            return;
        }

        wp_send_json_success( [ 'match' => PTM_Match::get( $match_id ) ] );
    }

    public function ajax_finalize_results() {
        check_ajax_referer( 'ptm_admin', 'nonce' );

        if ( ! PTM_Roles::can_manage_tournaments() ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
            return;
        }

        $tournament_id = absint( $_POST['tournament_id'] ?? 0 );
        if ( ! $tournament_id ) {
            wp_send_json_error( [ 'message' => 'Invalid tournament ID.' ] );
            return;
        }

        // Re-run finish position assignment from scratch
        PTM_Match::finalize_finish_positions( $tournament_id );

        // Mark complete if not already
        PTM_Tournament::set_status( $tournament_id, 'complete' );

        $results = PTM_Tournament::get_results( $tournament_id );

        // Debug: if still empty, return diagnostic info
        if ( empty( $results ) ) {
            global $wpdb;
            $stats_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ptm_player_stats WHERE tournament_id = %d",
                    $tournament_id
                )
            );
            $positions_set = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ptm_player_stats
                     WHERE tournament_id = %d AND finish_position IS NOT NULL",
                    $tournament_id
                )
            );
            $final_match = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status, winner_id, next_match_id, player1_id, player2_id
                     FROM {$wpdb->prefix}ptm_matches
                     WHERE tournament_id = %d AND next_match_id IS NULL
                     ORDER BY id DESC LIMIT 1",
                    $tournament_id
                ),
                ARRAY_A
            );
            wp_send_json_success( [
                'message'       => 'Finalized — but no results found. Debug info below.',
                'results'       => [],
                'debug'         => [
                    'stats_rows_total'    => $stats_count,
                    'positions_set'       => $positions_set,
                    'final_match'         => $final_match,
                    'last_db_error'       => $wpdb->last_error,
                ],
            ] );
            return;
        }

        wp_send_json_success( [
            'message' => 'Results finalized successfully.',
            'results' => $results,
        ] );
    }

    public function ajax_admin_score() {
        check_ajax_referer( 'ptm_admin', 'nonce' );

        if ( ! PTM_Roles::can_enter_scores() ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
            return;
        }

        $match_id    = absint( $_POST['match_id']    ?? 0 );
        $player_slot = absint( $_POST['player_slot'] ?? 0 );
        $action      = sanitize_key( $_POST['score_action'] ?? 'add' );

        if ( ! $match_id || ! in_array( $player_slot, [ 1, 2 ], true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid request.' ] );
            return;
        }

        $result = $action === 'remove'
            ? PTM_Match::remove_game( $match_id, $player_slot )
            : PTM_Match::add_game( $match_id, $player_slot );

        if ( ! empty( $result['error'] ) ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
            return;
        }

        wp_send_json_success( $result );
    }
}
