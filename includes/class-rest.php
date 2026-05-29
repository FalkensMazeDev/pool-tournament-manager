<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for Pool Tournament Manager.
 *
 * Public (no auth):
 *   GET  /wp-json/gdc/v1/tournament/{id}/bracket  — full bracket data
 *   GET  /wp-json/gdc/v1/tournament/{id}/updated  — last-updated timestamp (polling)
 *   GET  /wp-json/gdc/v1/match/{token}            — match data for scorer
 *   POST /wp-json/gdc/v1/match/{token}/score      — add/remove a game (scorer token auth)
 *
 * Organizer auth required:
 *   POST /wp-json/gdc/v1/tournament/{id}/generate  — generate bracket
 *   POST /wp-json/gdc/v1/tournament/{id}/randomize — randomize seeds
 *   POST /wp-json/gdc/v1/tournament/{id}/seeds     — save seed order
 */
class PTM_REST {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns = 'gdc/v1';

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/bracket', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_bracket' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/updated', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_last_updated' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/tables', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_table_status' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/match/(?P<token>[a-zA-Z0-9]+)/score', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'score_game' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'player_slot' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => in_array( (int) $v, [ 1, 2 ], true ),
                    'sanitize_callback' => 'absint',
                ],
                'action' => [
                    'default'           => 'add',
                    'validate_callback' => fn( $v ) => in_array( $v, [ 'add', 'remove' ], true ),
                ],
            ],
        ] );

        register_rest_route( $ns, '/match/(?P<token>[a-zA-Z0-9]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_match_by_token' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/table/(?P<table>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_table_match' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'generate_bracket' ],
            'permission_callback' => [ $this, 'check_organizer' ],
        ] );

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/randomize', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'randomize_seeds' ],
            'permission_callback' => [ $this, 'check_organizer' ],
        ] );

        register_rest_route( $ns, '/tournament/(?P<id>\d+)/seeds', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'save_seeds' ],
            'permission_callback' => [ $this, 'check_organizer' ],
            'args'                => [
                'player_ids' => [ 'required' => true, 'type' => 'array' ],
            ],
        ] );
    }

    // ── Callbacks ─────────────────────────────────────────────────────────────

    public function get_bracket( WP_REST_Request $request ): WP_REST_Response {
        $tournament_id = absint( $request['id'] );
        $tournament    = PTM_Tournament::get( $tournament_id );

        if ( ! $tournament ) {
            return new WP_REST_Response( [ 'error' => 'Tournament not found.' ], 404 );
        }

        // Private tournaments are viewable by organizers only
        if ( ! (int) $tournament['is_public'] && ! PTM_Roles::can_view_tournament_admin() ) {
            return new WP_REST_Response( [ 'error' => 'Forbidden.' ], 403 );
        }

        return new WP_REST_Response( [
            'tournament' => $tournament,
            'bracket'    => PTM_Bracket::get_bracket( $tournament_id ),
        ], 200 );
    }

    public function get_table_match( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $tournament_id = absint( $request['id'] );
        $table_number  = absint( $request['table'] );

        $tournament = PTM_Tournament::get( $tournament_id );
        if ( ! $tournament ) {
            return new WP_REST_Response( [ 'error' => 'Tournament not found.' ], 404 );
        }
        if ( ! (int) $tournament['is_public'] && ! PTM_Roles::can_view_tournament_admin() ) {
            return new WP_REST_Response( [ 'error' => 'Forbidden.' ], 403 );
        }

        $match = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*, p1.name AS player1_name, p2.name AS player2_name
                 FROM {$wpdb->prefix}ptm_matches m
                 LEFT JOIN {$wpdb->prefix}ptm_players p1 ON p1.id = m.player1_id
                 LEFT JOIN {$wpdb->prefix}ptm_players p2 ON p2.id = m.player2_id
                 WHERE m.tournament_id = %d
                   AND m.table_number  = %d
                   AND m.status IN ('in_progress', 'pending')
                 LIMIT 1",
                $tournament_id,
                $table_number
            ),
            ARRAY_A
        );

        return new WP_REST_Response( [
            'tournament_id'   => $tournament_id,
            'tournament_name' => $tournament['name'],
            'game_type'       => $tournament['game_type'],
            'table'           => $table_number,
            'match'           => $match ? [
                'id'             => (int) $match['id'],
                'token'          => $match['score_token'],
                'player1_name'   => $match['player1_name'] ?? 'TBD',
                'player2_name'   => $match['player2_name'] ?? 'TBD',
                'player1_id'     => (int) $match['player1_id'],
                'player2_id'     => (int) $match['player2_id'],
                'player1_score'  => (int) $match['player1_score'],
                'player2_score'  => (int) $match['player2_score'],
                'race_to_player1'=> (int) $match['race_to_player1'],
                'race_to_player2'=> (int) $match['race_to_player2'],
                'bracket_side'   => $match['bracket_side'],
                'round'          => (int) $match['round'],
                'status'         => $match['status'],
                'winner_id'      => $match['winner_id'] ? (int) $match['winner_id'] : null,
            ] : null,
        ], 200 );
    }

    public function get_last_updated( WP_REST_Request $request ): WP_REST_Response {
        $tournament_id = absint( $request['id'] );

        // Fill any free tables that have ready matches (cheap idempotent call)
        $tournament = PTM_Tournament::get( $tournament_id );
        if ( $tournament && $tournament['status'] === 'active' ) {
            PTM_Tables::assign( $tournament_id );
        }

        return new WP_REST_Response( [
            'tournament_id'     => $tournament_id,
            'tournament_status' => $tournament ? $tournament['status'] : null,
            'last_updated'      => PTM_Match::get_last_updated( $tournament_id ),
            'waiting'           => PTM_Tables::count_waiting( $tournament_id ),
        ], 200 );
    }

    public function get_table_status( WP_REST_Request $request ): WP_REST_Response {
        $tournament_id = absint( $request['id'] );
        $tournament    = PTM_Tournament::get( $tournament_id );

        if ( ! $tournament ) {
            return new WP_REST_Response( [ 'error' => 'Tournament not found.' ], 404 );
        }

        if ( ! (int) $tournament['is_public'] && ! PTM_Roles::can_view_tournament_admin() ) {
            return new WP_REST_Response( [ 'error' => 'Forbidden.' ], 403 );
        }

        $raw    = PTM_Tables::get_table_status( $tournament_id );
        $tables = [];
        foreach ( $raw as $num => $match ) {
            $tables[] = [
                'table'         => $num,
                'match'         => $match ? [
                    'id'            => (int) $match['id'],
                    'player1_name'  => $match['player1_name'] ?? 'TBD',
                    'player2_name'  => $match['player2_name'] ?? 'TBD',
                    'player1_score' => (int) $match['player1_score'],
                    'player2_score' => (int) $match['player2_score'],
                    'status'        => $match['status'],
                    'bracket_side'  => $match['bracket_side'],
                    'round'         => (int) $match['round'],
                ] : null,
            ];
        }

        return new WP_REST_Response( [
            'tables'  => $tables,
            'waiting' => PTM_Tables::count_waiting( $tournament_id ),
        ], 200 );
    }

    public function get_match_by_token( WP_REST_Request $request ): WP_REST_Response {
        $token = sanitize_text_field( $request['token'] );
        $match = PTM_Match::get_by_token( $token );

        if ( ! $match ) {
            return new WP_REST_Response( [ 'error' => 'Match not found.' ], 404 );
        }

        // Augment with player names for the scorer UI
        return new WP_REST_Response( $this->hydrate_match( $match ), 200 );
    }

    public function score_game( WP_REST_Request $request ): WP_REST_Response {
        $token = sanitize_text_field( $request['token'] );
        $match = PTM_Match::get_by_token( $token );

        if ( ! $match ) {
            return new WP_REST_Response( [ 'error' => 'Match not found.' ], 404 );
        }

        $action      = $request->get_param( 'action' ) ?: 'add';
        $player_slot = (int) $request->get_param( 'player_slot' );

        $result = $action === 'remove'
            ? PTM_Match::remove_game( (int) $match['id'], $player_slot )
            : PTM_Match::add_game( (int) $match['id'], $player_slot );

        if ( ! empty( $result['error'] ) ) {
            return new WP_REST_Response( [ 'error' => $result['error'] ], 400 );
        }

        // Re-fetch with player names attached
        $updated = $result['match'];
        if ( $updated ) {
            $updated = $this->hydrate_match( $updated );
        }

        return new WP_REST_Response( [
            'match'     => $updated,
            'completed' => $result['completed'] ?? false,
        ], 200 );
    }

    public function generate_bracket( WP_REST_Request $request ): WP_REST_Response {
        $tournament_id = absint( $request['id'] );
        $result        = PTM_Bracket::generate( $tournament_id );

        if ( is_array( $result ) && ! empty( $result['error'] ) ) {
            return new WP_REST_Response( [ 'error' => $result['error'] ], 400 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'bracket' => PTM_Bracket::get_bracket( $tournament_id ),
        ], 200 );
    }

    public function randomize_seeds( WP_REST_Request $request ): WP_REST_Response {
        $tournament_id = absint( $request['id'] );
        $result        = PTM_Player::randomize_seeds( $tournament_id );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'players' => PTM_Player::get_tournament_players( $tournament_id ),
        ], 200 );
    }

    public function save_seeds( WP_REST_Request $request ): WP_REST_Response {
        $tournament_id = absint( $request['id'] );
        $player_ids    = array_map( 'absint', (array) $request->get_param( 'player_ids' ) );
        $result        = PTM_Player::save_seeds( $tournament_id, $player_ids );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'players' => PTM_Player::get_tournament_players( $tournament_id ),
        ], 200 );
    }

    // ── Permission callbacks ──────────────────────────────────────────────────

    public function check_organizer(): bool {
        return PTM_Roles::can_manage_tournaments();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Joins player names onto a raw match array for API responses.
     */
    private function hydrate_match( array $match ): array {
        global $wpdb;

        if ( $match['player1_id'] ) {
            $match['player1_name'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}ptm_players WHERE id = %d", $match['player1_id'] )
            );
        }
        if ( $match['player2_id'] ) {
            $match['player2_name'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}ptm_players WHERE id = %d", $match['player2_id'] )
            );
        }
        if ( $match['winner_id'] ) {
            $match['winner_name'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}ptm_players WHERE id = %d", $match['winner_id'] )
            );
        }

        return $match;
    }
}
