<?php
defined( 'ABSPATH' ) || exit;

class PTM_Tournament {

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'ptm_tournaments';
        $where  = [];
        $values = [];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( isset( $args['is_public'] ) ) {
            $where[]  = 'is_public = %d';
            $values[] = (int) $args['is_public'];
        }

        $sql = "SELECT * FROM $table";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY ISNULL(tournament_date) ASC, tournament_date DESC, created_at DESC';

        return $values
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A )
            : $wpdb->get_results( $sql, ARRAY_A );
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ptm_tournaments WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function insert( array $data ): int|false {
        global $wpdb;
        $clean  = self::sanitize( $data );
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptm_tournaments',
            $clean,
            self::formats( $clean )
        );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $clean = self::sanitize( $data );
        return (bool) $wpdb->update(
            $wpdb->prefix . 'ptm_tournaments',
            $clean,
            [ 'id' => $id ],
            self::formats( $clean ),
            [ '%d' ]
        );
    }

    public static function delete( int $id ): bool {
        global $wpdb;

        if ( ! $id ) return false;

        // Delete all related data in dependency order before removing the tournament
        $wpdb->delete( $wpdb->prefix . 'ptm_player_stats',       [ 'tournament_id' => $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'ptm_matches',            [ 'tournament_id' => $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'ptm_handicap_rules',     [ 'tournament_id' => $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'ptm_tournament_players', [ 'tournament_id' => $id ], [ '%d' ] );

        // Delete the tournament record itself — no status restriction
        $result = $wpdb->delete(
            $wpdb->prefix . 'ptm_tournaments',
            [ 'id' => $id ],
            [ '%d' ]
        );

        return $result !== false && $result > 0;
    }

    public static function set_status( int $id, string $status ): bool {
        global $wpdb;
        $allowed = [ 'draft', 'active', 'complete' ];
        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }
        return (bool) $wpdb->update(
            $wpdb->prefix . 'ptm_tournaments',
            [ 'status' => $status ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    // ── Handicap rules ────────────────────────────────────────────────────────

    public static function get_handicap_rules( int $tournament_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptm_handicap_rules
                 WHERE tournament_id = %d
                 ORDER BY skill_level_higher DESC, skill_level_lower DESC",
                $tournament_id
            ),
            ARRAY_A
        );
    }

    /**
     * Replace all handicap rules for a tournament in one call.
     * $rules — array of [ higher, lower, race_higher, race_lower ]
     */
    public static function save_handicap_rules( int $tournament_id, array $rules ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ptm_handicap_rules';

        // Delete existing
        $wpdb->delete( $table, [ 'tournament_id' => $tournament_id ], [ '%d' ] );

        foreach ( $rules as $rule ) {
            $wpdb->insert(
                $table,
                [
                    'tournament_id'      => $tournament_id,
                    'skill_level_higher' => (int) $rule['skill_level_higher'],
                    'skill_level_lower'  => (int) $rule['skill_level_lower'],
                    'race_to_higher'     => (int) $rule['race_to_higher'],
                    'race_to_lower'      => (int) $rule['race_to_lower'],
                ],
                [ '%d', '%d', '%d', '%d', '%d' ]
            );
        }
        return true;
    }

    /**
     * Look up the race-to values for a specific matchup.
     * Returns [ race_to_higher, race_to_lower ] or defaults if no rule found.
     */
    public static function resolve_race_to( int $tournament_id, ?int $skill_a, ?int $skill_b ): array {
        global $wpdb;

        $tournament = self::get( $tournament_id );
        $default    = [
            'race_to_a' => (int) $tournament['race_to_winners'],
            'race_to_b' => (int) $tournament['race_to_winners'],
        ];

        if ( ! $tournament['handicap_enabled'] || is_null( $skill_a ) || is_null( $skill_b ) ) {
            return $default;
        }

        // Normalise so higher skill is always queried as "higher"
        $higher = max( $skill_a, $skill_b );
        $lower  = min( $skill_a, $skill_b );

        $rule = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptm_handicap_rules
                 WHERE tournament_id = %d
                   AND skill_level_higher = %d
                   AND skill_level_lower  = %d",
                $tournament_id, $higher, $lower
            ),
            ARRAY_A
        );

        if ( ! $rule ) {
            return $default;
        }

        // Return race_to values mapped back to player_a and player_b
        return [
            'race_to_a' => $skill_a >= $skill_b ? (int) $rule['race_to_higher'] : (int) $rule['race_to_lower'],
            'race_to_b' => $skill_b >= $skill_a ? (int) $rule['race_to_higher'] : (int) $rule['race_to_lower'],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function sanitize( array $data ): array {
        $clean = [];
        $allowed_game_types    = [ '8ball', '9ball', '10ball' ];
        $allowed_bracket_types = [ 'single_elim', 'double_elim' ];

        if ( isset( $data['name'] ) )
            $clean['name'] = sanitize_text_field( $data['name'] );
        if ( isset( $data['game_type'] ) && in_array( $data['game_type'], $allowed_game_types, true ) )
            $clean['game_type'] = $data['game_type'];
        if ( isset( $data['bracket_type'] ) && in_array( $data['bracket_type'], $allowed_bracket_types, true ) )
            $clean['bracket_type'] = $data['bracket_type'];
        if ( isset( $data['race_to_winners'] ) )
            $clean['race_to_winners'] = absint( $data['race_to_winners'] );
        if ( isset( $data['race_to_losers'] ) )
            $clean['race_to_losers'] = absint( $data['race_to_losers'] );
        if ( isset( $data['num_tables'] ) )
            $clean['num_tables'] = max( 1, min( 20, absint( $data['num_tables'] ) ) );
        if ( isset( $data['entrance_fee'] ) )
            $clean['entrance_fee'] = max( 0, (float) $data['entrance_fee'] );
        if ( isset( $data['director_fee'] ) )
            $clean['director_fee'] = max( 0, (float) $data['director_fee'] );
        if ( isset( $data['money_added'] ) )
            $clean['money_added'] = max( 0, (float) $data['money_added'] );
        if ( isset( $data['slug'] ) && $data['slug'] !== '' )
            $clean['slug'] = sanitize_title( $data['slug'] );
        if ( isset( $data['handicap_enabled'] ) )
            $clean['handicap_enabled'] = (int) (bool) $data['handicap_enabled'];
        if ( isset( $data['is_public'] ) )
            $clean['is_public'] = (int) (bool) $data['is_public'];
        if ( isset( $data['tournament_date'] ) )
            $clean['tournament_date'] = sanitize_text_field( $data['tournament_date'] ) ?: null;
        if ( isset( $data['notes'] ) )
            $clean['notes'] = sanitize_textarea_field( $data['notes'] );
        if ( isset( $data['created_by'] ) )
            $clean['created_by'] = absint( $data['created_by'] );

        return $clean;
    }

    private static function formats( array $data = [] ): array {
        $map = [
            'name'             => '%s',
            'game_type'        => '%s',
            'bracket_type'     => '%s',
            'race_to_winners'  => '%d',
            'race_to_losers'   => '%d',
            'num_tables'       => '%d',
            'entrance_fee'     => '%f',
            'director_fee'     => '%f',
            'money_added'      => '%f',
            'slug'             => '%s',
            'handicap_enabled' => '%d',
            'is_public'        => '%d',
            'tournament_date'  => '%s',
            'notes'            => '%s',
            'created_by'       => '%d',
        ];
        if ( empty( $data ) ) {
            return array_values( $map );
        }
        $formats = [];
        foreach ( $data as $key => $_ ) {
            $formats[] = $map[ $key ] ?? '%s';
        }
        return $formats;
    }


    // ── Payout rules ──────────────────────────────────────────────────────────

    /**
     * Returns all payout rules for a tournament, ordered by position.
     */
    public static function get_payout_rules( int $tournament_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptm_payout_rules
                 WHERE tournament_id = %d ORDER BY position_from ASC",
                $tournament_id
            ),
            ARRAY_A
        );
    }

    /**
     * Saves (replaces) all payout rules for a tournament.
     * $rules: array of [ position_label, position_from, position_to, pct ]
     */
    public static function save_payout_rules( int $tournament_id, array $rules ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ptm_payout_rules', [ 'tournament_id' => $tournament_id ], [ '%d' ] );
        foreach ( $rules as $rule ) {
            if ( empty( $rule['position_label'] ) ) continue;
            $wpdb->insert(
                $wpdb->prefix . 'ptm_payout_rules',
                [
                    'tournament_id'  => $tournament_id,
                    'position_label' => sanitize_text_field( $rule['position_label'] ),
                    'position_from'  => max( 1, absint( $rule['position_from'] ) ),
                    'position_to'    => max( 1, absint( $rule['position_to'] ) ),
                    'pct'            => max( 0, min( 100, (float) $rule['pct'] ) ),
                ],
                [ '%d', '%s', '%d', '%d', '%f' ]
            );
        }
    }

    /**
     * Calculates payout dollar amounts from a tournament's entrance fee,
     * player count, and payout rules.
     * Returns array of rule rows with an added 'amount' key.
     */
    public static function calculate_payouts( int $tournament_id ): array {
        $tournament   = self::get( $tournament_id );
        $player_count = self::get_player_count( $tournament_id );
        $rules        = self::get_payout_rules( $tournament_id );

        $entrance_fee  = (float) $tournament['entrance_fee'];
        $director_fee  = (float) ( $tournament['director_fee'] ?? 0 );
        $money_added   = (float) ( $tournament['money_added'] ?? 0 );
        $net_per_player = max( 0, $entrance_fee - $director_fee );
        $total_pot      = $net_per_player * $player_count + $money_added;

        foreach ( $rules as &$rule ) {
            $rule['amount']      = round( $total_pot * ( (float) $rule['pct'] / 100 ), 2 );
            $rule['total_pot']   = $total_pot;
            $rule['money_added'] = $money_added;
            $rule['director_fee_total'] = $director_fee * $player_count;
        }
        unset( $rule );

        return $rules;
    }

    // ── Slug helpers ──────────────────────────────────────────────────────────

    /**
     * Looks up a tournament by its URL slug.
     */
    public static function get_by_slug( string $slug ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptm_tournaments WHERE slug = %s LIMIT 1",
                sanitize_title( $slug )
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Generates a unique slug from the tournament name.
     * Called automatically on insert if no slug provided.
     */
    public static function generate_slug( string $name, int $exclude_id = 0 ): string {
        global $wpdb;
        $base = sanitize_title( $name );
        $slug = $base;
        $i    = 1;
        while ( true ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ptm_tournaments
                     WHERE slug = %s AND id != %d LIMIT 1",
                    $slug,
                    $exclude_id
                )
            );
            if ( ! $exists ) break;
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    /**
     * Returns the canonical public URL for a tournament.
     */
    public static function get_url( array $tournament, string $sub_page = '' ): string {
        $slug = $tournament['slug'] ?: $tournament['id'];
        $base = home_url( '/tournament/' . $slug . '/' );
        return $sub_page ? $base . $sub_page . '/' : $base;
    }

    /**
     * Returns (and lazily creates) the random token used in table-view URLs
     * for a given tournament, making those URLs non-guessable.
     */
    public static function get_table_token( int $tournament_id ): string {
        $key   = 'ptm_table_token_' . $tournament_id;
        $token = get_option( $key, '' );
        if ( ! $token ) {
            $token = wp_generate_password( 10, false );
            update_option( $key, $token, false );
        }
        return $token;
    }

    /**
     * Returns finish results for a completed tournament, ordered by position.
     * Each row: player_id, name, finish_position, matches_won, matches_lost, games_won, games_lost.
     */
    public static function get_results( int $tournament_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ps.*, p.name
                 FROM {$wpdb->prefix}ptm_player_stats ps
                 JOIN {$wpdb->prefix}ptm_players p ON p.id = ps.player_id
                 WHERE ps.tournament_id   = %d
                   AND ps.finish_position IS NOT NULL
                 ORDER BY ps.finish_position ASC",
                $tournament_id
            ),
            ARRAY_A
        );
    }

    // ── Aliases & convenience methods ────────────────────────────────────────

    /**
     * Alias: create → insert (used by admin form handler).
     */
    public static function create( array $data ): int|\WP_Error {
        // Auto-generate slug from name if not provided
        if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
            $data['slug'] = self::generate_slug( $data['name'] );
        }
        $id = self::insert( $data );
        if ( false === $id ) {
            return new \WP_Error( 'db_error', __( 'Could not create tournament.', 'ptm-tournaments' ) );
        }
        return $id;
    }

    /**
     * Returns the number of players on a tournament roster.
     */
    public static function get_player_count( int $tournament_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptm_tournament_players WHERE tournament_id = %d",
                $tournament_id
            )
        );
    }

    /**
     * Returns match counts grouped by status for a tournament.
     * Returns an object with properties: total, pending, in_progress, complete.
     */
    public static function get_match_counts( int $tournament_id ): object {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) AS cnt
                 FROM {$wpdb->prefix}ptm_matches
                 WHERE tournament_id = %d
                 GROUP BY status",
                $tournament_id
            )
        );
        $counts = (object) [ 'total' => 0, 'pending' => 0, 'in_progress' => 0, 'complete' => 0 ];
        foreach ( $rows as $row ) {
            $key = $row->status;
            $counts->$key = (int) $row->cnt;
            $counts->total += (int) $row->cnt;
        }
        return $counts;
    }

}
