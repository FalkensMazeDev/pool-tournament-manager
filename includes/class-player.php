<?php
defined( 'ABSPATH' ) || exit;

/**
 * CRUD operations for the permanent player registry (ptm_players)
 * and tournament roster management (ptm_tournament_players).
 */
class PTM_Player {

    // ── Registry ─────────────────────────────────────────────────────────────

    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $table           = $wpdb->prefix . 'ptm_players';
        $search          = isset( $args['search'] ) ? (string) $args['search'] : '';
        $allowed_orderby = [ 'name', 'created_at', 'id' ];
        $requested       = isset( $args['orderby'] ) ? $args['orderby'] : 'name';
        $orderby         = in_array( $requested, $allowed_orderby, true ) ? $requested : 'name';
        $order           = ( isset( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ) ? 'DESC' : 'ASC';
        $limit           = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 500;

        if ( $search ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT * FROM $table WHERE name LIKE %s ORDER BY $orderby $order LIMIT %d",
                    '%' . $wpdb->esc_like( $search ) . '%',
                    $limit
                ),
                ARRAY_A
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM $table ORDER BY $orderby $order LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ptm_players WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function insert( array $data ): int|false {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptm_players',
            [
                'name'  => sanitize_text_field( $data['name'] ),
                'email' => sanitize_email( $data['email'] ?? '' ) ?: null,
                'phone' => sanitize_text_field( $data['phone'] ?? '' ) ?: null,
            ],
            [ '%s', '%s', '%s' ]
        );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $fields = [];
        $formats = [];

        if ( isset( $data['name'] ) ) {
            $fields['name']  = sanitize_text_field( $data['name'] );
            $formats[]       = '%s';
        }
        if ( array_key_exists( 'email', $data ) ) {
            $fields['email'] = sanitize_email( $data['email'] ) ?: null;
            $formats[]       = '%s';
        }
        if ( array_key_exists( 'phone', $data ) ) {
            $fields['phone'] = sanitize_text_field( $data['phone'] ) ?: null;
            $formats[]       = '%s';
        }

        if ( empty( $fields ) ) {
            return false;
        }

        return (bool) $wpdb->update(
            $wpdb->prefix . 'ptm_players',
            $fields,
            [ 'id' => $id ],
            $formats,
            [ '%d' ]
        );
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'ptm_players',
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    // ── Tournament roster ─────────────────────────────────────────────────────

    /**
     * Add a player to a tournament roster.
     */
    public static function add_to_tournament( int $tournament_id, int $player_id, array $opts = [] ): int|false {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptm_tournament_players',
            [
                'tournament_id' => $tournament_id,
                'player_id'     => $player_id,
                'skill_level'   => isset( $opts['skill_level'] ) ? (int) $opts['skill_level'] : null,
            ],
            [ '%d', '%d', '%d' ]
        );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function remove_from_tournament( int $tournament_id, int $player_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'ptm_tournament_players',
            [ 'tournament_id' => $tournament_id, 'player_id' => $player_id ],
            [ '%d', '%d' ]
        );
    }

    /**
     * Return all players on a tournament roster, joined with registry info,
     * ordered by seed (nulls last).
     */
    public static function get_tournament_roster( int $tournament_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tp.*, p.name, p.email, p.phone
                 FROM {$wpdb->prefix}ptm_tournament_players tp
                 JOIN {$wpdb->prefix}ptm_players p ON p.id = tp.player_id
                 WHERE tp.tournament_id = %d
                 ORDER BY ISNULL(tp.seed), tp.seed ASC",
                $tournament_id
            ),
            ARRAY_A
        );
    }

    /**
     * Randomise seeds for all players in a tournament and save.
     * Returns the shuffled roster.
     */
    public static function randomise_seeds( int $tournament_id ): array {
        global $wpdb;
        $roster = self::get_tournament_roster( $tournament_id );
        if ( empty( $roster ) ) {
            return [];
        }

        $ids = array_column( $roster, 'player_id' );
        shuffle( $ids );

        foreach ( $ids as $seed => $player_id ) {
            $wpdb->update(
                $wpdb->prefix . 'ptm_tournament_players',
                [ 'seed' => $seed + 1 ],
                [ 'tournament_id' => $tournament_id, 'player_id' => $player_id ],
                [ '%d' ],
                [ '%d', '%d' ]
            );
        }

        return self::get_tournament_roster( $tournament_id );
    }

    /**
     * Save a manually-ordered seed list.
     * $ordered_player_ids — array of player_ids in desired seed order (index 0 = seed 1).
     */
    public static function save_seeds( int $tournament_id, array $ordered_player_ids ): bool {
        global $wpdb;
        foreach ( $ordered_player_ids as $seed => $player_id ) {
            $wpdb->update(
                $wpdb->prefix . 'ptm_tournament_players',
                [ 'seed' => $seed + 1 ],
                [ 'tournament_id' => $tournament_id, 'player_id' => (int) $player_id ],
                [ '%d' ],
                [ '%d', '%d' ]
            );
        }
        return true;
    }

    /**
     * Update skill level for a player within a tournament.
     */
    public static function update_skill_level( int $tournament_id, int $player_id, int $skill_level ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'ptm_tournament_players',
            [ 'skill_level' => $skill_level ],
            [ 'tournament_id' => $tournament_id, 'player_id' => $player_id ],
            [ '%d' ],
            [ '%d', '%d' ]
        );
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public static function get_stats( int $player_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ps.*, t.name AS tournament_name, t.game_type, t.tournament_date
                 FROM {$wpdb->prefix}ptm_player_stats ps
                 JOIN {$wpdb->prefix}ptm_tournaments t ON t.id = ps.tournament_id
                 WHERE ps.player_id = %d
                 ORDER BY t.tournament_date DESC",
                $player_id
            ),
            ARRAY_A
        );
    }

    /**
     * Ensure a stats row exists for this player/tournament combo, then increment.
     */
    public static function record_match_result(
        int $player_id,
        int $tournament_id,
        bool $won,
        int $games_won,
        int $games_lost
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ptm_player_stats';

        // Upsert — insert or update on duplicate key
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $table
                    (player_id, tournament_id, matches_played, matches_won, matches_lost, games_won, games_lost)
                 VALUES (%d, %d, 1, %d, %d, %d, %d)
                 ON DUPLICATE KEY UPDATE
                    matches_played = matches_played + 1,
                    matches_won    = matches_won    + %d,
                    matches_lost   = matches_lost   + %d,
                    games_won      = games_won      + %d,
                    games_lost     = games_lost     + %d",
                $player_id,
                $tournament_id,
                $won ? 1 : 0,
                $won ? 0 : 1,
                $games_won,
                $games_lost,
                // ON DUPLICATE KEY values
                $won ? 1 : 0,
                $won ? 0 : 1,
                $games_won,
                $games_lost
            )
        );
    }


    // ── Aliases for backwards compatibility with admin/REST callers ───────────

    /**
     * Alias: get_tournament_players → get_tournament_roster
     */
    public static function get_tournament_players( int $tournament_id ): array {
        return self::get_tournament_roster( $tournament_id );
    }

    /**
     * Alias: randomize_seeds — shuffles and re-saves seed order for all players.
     */
    public static function randomize_seeds( int $tournament_id ): bool|\WP_Error {
        global $wpdb;
        $player_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT player_id FROM {$wpdb->prefix}ptm_tournament_players
                 WHERE tournament_id = %d",
                $tournament_id
            )
        );
        if ( empty( $player_ids ) ) {
            return new \WP_Error( 'no_players', __( 'No players in this tournament.', 'ptm-tournaments' ) );
        }
        shuffle( $player_ids );
        return self::save_seeds( $tournament_id, $player_ids );
    }

    /**
     * Alias: create — inserts a new player; mirrors add() semantics.
     */
    public static function create( array $data ): int|\WP_Error {
        $id = self::insert( $data );
        if ( false === $id ) {
            return new \WP_Error( 'db_error', __( 'Could not insert player.', 'ptm-tournaments' ) );
        }
        return $id;
    }

}
