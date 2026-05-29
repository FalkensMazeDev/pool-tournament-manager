<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bracket generation engine.
 *
 * Supports single elimination and double elimination.
 * Handles any player count by rounding up to the next power of 2 and
 * inserting BYE slots (top seeds receive the byes).
 *
 * Usage:
 *   PTM_Bracket::generate( $tournament_id );
 */
class PTM_Bracket {

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Generate the full bracket for a tournament.
     * Deletes any existing matches first (only allowed for draft tournaments).
     *
     * @return array [ 'success' => bool, 'message' => string, 'match_count' => int ]
     */
    public static function generate( int $tournament_id ): array {
        global $wpdb;

        $tournament = PTM_Tournament::get( $tournament_id );
        if ( ! $tournament ) {
            return [ 'success' => false, 'message' => 'Tournament not found.', 'match_count' => 0 ];
        }

        $roster = PTM_Player::get_tournament_roster( $tournament_id );
        if ( count( $roster ) < 2 ) {
            return [ 'success' => false, 'message' => 'At least 2 players required.', 'match_count' => 0 ];
        }

        // Verify all players have seeds assigned
        foreach ( $roster as $entry ) {
            if ( is_null( $entry['seed'] ) ) {
                return [
                    'success' => false,
                    'message' => 'All players must be seeded before generating the bracket. Use Randomise Seeds.',
                    'match_count' => 0,
                ];
            }
        }

        // Delete existing matches for this tournament
        $wpdb->delete( $wpdb->prefix . 'ptm_matches', [ 'tournament_id' => $tournament_id ], [ '%d' ] );

        // Sort roster by seed
        usort( $roster, fn( $a, $b ) => (int) $a['seed'] - (int) $b['seed'] );

        $match_count = $tournament['bracket_type'] === 'double_elim'
            ? self::generate_double_elim( $tournament, $roster )
            : self::generate_single_elim( $tournament, $roster );

        // Set tournament to active
        PTM_Tournament::set_status( $tournament_id, 'active' );

        // Seed initial table assignments — fill all tables immediately
        PTM_Tables::assign( $tournament_id );

        return [
            'success'     => true,
            'message'     => "Bracket generated with $match_count matches.",
            'match_count' => $match_count,
        ];
    }

    // ── Single elimination ────────────────────────────────────────────────────

    private static function generate_single_elim( array $tournament, array $roster ): int {
        $bracket_size = self::next_power_of_2( count( $roster ) );
        $slots        = self::build_seeded_slots( $roster, $bracket_size );
        $total_rounds = (int) log( $bracket_size, 2 );
        $total        = 0;

        // matches_in_round: R1 = bracket_size/2, R2 = bracket_size/4, ... Finals = 1
        // We must insert from the FINAL round down to round 1 so that
        // next_match_id is available when we insert earlier rounds.
        $match_ids = [];
        for ( $round = $total_rounds; $round >= 1; $round-- ) {
            $count = (int) ( $bracket_size / pow( 2, $round ) );
            for ( $m = 1; $m <= $count; $m++ ) {
                $next_match_id = null;
                if ( $round < $total_rounds ) {
                    // Winner goes to match ceil(m/2) in the next round
                    $next_match_id = $match_ids[ $round + 1 ][ (int) ceil( $m / 2 ) ] ?? null;
                }

                $id = self::insert_match( [
                    'tournament_id' => (int) $tournament['id'],
                    'bracket_side'  => 'winners',
                    'round'         => $round,
                    'match_number'  => $m,
                    'race_to_p1'    => (int) $tournament['race_to_winners'],
                    'race_to_p2'    => (int) $tournament['race_to_winners'],
                    'next_match_id' => $next_match_id,
                ] );

                $match_ids[ $round ][ $m ] = $id;
                $total++;
            }
        }

        // Place seeded players into round-1 match slots, handle byes
        self::place_players_single( $slots, $match_ids[1], (int) $tournament['id'], $tournament );

        return $total;
    }

    // ── Double elimination ────────────────────────────────────────────────────
    //
    // Standard double-elim LB structure (8-player example):
    //   WB R1: 4 matches  → 4 losers go to LB R1
    //   LB R1: 2 matches  (pair WB-R1 losers)
    //   LB R2: 2 matches  (elim: LB-R1 winners vs WB-R2 losers)  ← DROP
    //   LB R3: 1 match    (elim: LB-R2 winners only)
    //   LB R4: 1 match    (elim: LB-R3 winner vs WB-R3 loser)    ← DROP (if WB has ≥3 rounds)
    //   Grand Finals
    //
    // Alternating pattern after LB R1:
    //   Odd LB rounds = pure elim (LB survivors play each other)
    //   Even LB rounds = drop rounds (LB survivor vs incoming WB loser)
    //
    private static function generate_double_elim( array $tournament, array $roster ): int {
        global $wpdb;

        $bracket_size = self::next_power_of_2( count( $roster ) );
        $slots        = self::build_seeded_slots( $roster, $bracket_size );
        $wb_rounds    = (int) log( $bracket_size, 2 );
        $total        = 0;

        // ── Winners bracket ───────────────────────────────────────────────────
        // Insert finals-first so next_match_id links resolve correctly.
        $wb_ids = [];
        for ( $round = $wb_rounds; $round >= 1; $round-- ) {
            $count = (int) ( $bracket_size / pow( 2, $round ) );
            for ( $m = 1; $m <= $count; $m++ ) {
                $next_match_id = $round < $wb_rounds
                    ? ( $wb_ids[ $round + 1 ][ (int) ceil( $m / 2 ) ] ?? null )
                    : null; // WB final has no WB next match — will be set to GF later

                $id = self::insert_match( [
                    'tournament_id' => (int) $tournament['id'],
                    'bracket_side'  => 'winners',
                    'round'         => $round,
                    'match_number'  => $m,
                    'race_to_p1'    => (int) $tournament['race_to_winners'],
                    'race_to_p2'    => (int) $tournament['race_to_winners'],
                    'next_match_id' => $next_match_id,
                ] );

                $wb_ids[ $round ][ $m ] = $id;
                $total++;
            }
        }

        // ── Build LB round schedule ───────────────────────────────────────────
        // lb_schedule: array of [ 'type' => 'init'|'drop'|'elim', 'matches' => N, 'wb_round' => X ]
        // We compute this forward so we know each round's size before generating
        // matches backward (finals-first for correct next_match_id links).
        $lb_schedule = [];
        $lb_round    = 1;

        for ( $wb_r = 1; $wb_r <= $wb_rounds - 1; $wb_r++ ) {
            $wb_losers = (int) ( $bracket_size / pow( 2, $wb_r ) ); // losers from this WB round

            if ( $wb_r === 1 ) {
                // LB R1: pair up WB R1 losers (N/2 players → N/4 matches)
                $matches                   = $wb_losers / 2;
                $lb_schedule[ $lb_round ]  = [ 'type' => 'init', 'matches' => $matches, 'wb_round' => 1 ];
                $survivors                 = $matches;
                $lb_round++;
                // No elim after init — next round is the WB R2 drop round
            }

            // Drop round: pair each LB survivor with a WB loser (1-to-1)
            if ( $wb_r >= 2 ) {
                $matches                   = $wb_losers; // one LB survivor per WB loser
                $lb_schedule[ $lb_round ]  = [ 'type' => 'drop', 'matches' => $matches, 'wb_round' => $wb_r ];
                $survivors                 = $matches;
                $lb_round++;

                // Pure elim round after each drop: halve the survivors
                if ( $survivors > 1 ) {
                    $elim_matches              = $survivors / 2;
                    $lb_schedule[ $lb_round ]  = [ 'type' => 'elim', 'matches' => $elim_matches ];
                    $survivors                 = $elim_matches;
                    $lb_round++;
                }
            }
        }

        $lb_total_rounds = $lb_round - 1;

        // ── Losers bracket (insert highest round first) ───────────────────────
        $lb_ids = [];
        for ( $round = $lb_total_rounds; $round >= 1; $round-- ) {
            $sched  = $lb_schedule[ $round ];
            $count  = $sched['matches'];
            $type   = $sched['type'];

            for ( $m = 1; $m <= $count; $m++ ) {
                // Determine next_match_id
                $next_match_id = null;
                if ( $round < $lb_total_rounds ) {
                    $next_sched   = $lb_schedule[ $round + 1 ];
                    $next_count   = $next_sched['matches'];
                    $next_type    = $next_sched['type'];

                    if ( $type === 'init' || $type === 'elim' ) {
                        // Survivors advance; if next round is a drop round there are
                        // equal slots (one per survivor), so next match = $m.
                        // If next round is another elim, pairs fold: match = ceil(m/2).
                        if ( $next_type === 'drop' ) {
                            $next_m = $m; // 1-to-1 pairing with WB drop
                        } else {
                            $next_m = (int) ceil( $m / 2 );
                        }
                    } else {
                        // drop round — winner advances to next elim, pairs fold
                        $next_m = (int) ceil( $m / 2 );
                    }

                    $next_match_id = $lb_ids[ $round + 1 ][ $next_m ] ?? null;
                }

                $id = self::insert_match( [
                    'tournament_id' => (int) $tournament['id'],
                    'bracket_side'  => 'losers',
                    'round'         => $round,
                    'match_number'  => $m,
                    'race_to_p1'    => (int) $tournament['race_to_losers'],
                    'race_to_p2'    => (int) $tournament['race_to_losers'],
                    'next_match_id' => $next_match_id,
                ] );

                $lb_ids[ $round ][ $m ] = $id;
                $total++;
            }
        }

        // ── Grand Finals ──────────────────────────────────────────────────────
        $gf_reset = self::insert_match( [
            'tournament_id'       => (int) $tournament['id'],
            'bracket_side'        => 'finals',
            'round'               => 2,
            'match_number'        => 1,
            'race_to_p1'          => (int) $tournament['race_to_winners'],
            'race_to_p2'          => (int) $tournament['race_to_winners'],
            'next_match_id'       => null,
            'loser_next_match_id' => null,
        ] );
        $total++;

        $gf_main = self::insert_match( [
            'tournament_id'       => (int) $tournament['id'],
            'bracket_side'        => 'finals',
            'round'               => 1,
            'match_number'        => 1,
            'race_to_p1'          => (int) $tournament['race_to_winners'],
            'race_to_p2'          => (int) $tournament['race_to_winners'],
            'next_match_id'       => $gf_reset,  // if LB champ wins, both go to reset
            'loser_next_match_id' => null,        // loser of GF main is 2nd place — eliminated
        ] );
        $total++;

        // ── Wire WB final → GF (winner advances to GF, loser is out) ─────────
        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ 'next_match_id' => $gf_main, 'loser_next_match_id' => null ],
            [ 'id' => $wb_ids[ $wb_rounds ][1] ],
            [ '%d', '%d' ], [ '%d' ]
        );

        // ── Wire LB final → GF ────────────────────────────────────────────────
        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ 'next_match_id' => $gf_main ],
            [ 'id' => $lb_ids[ $lb_total_rounds ][1] ],
            [ '%d' ], [ '%d' ]
        );

        // ── Wire WB losers → their LB drop round ─────────────────────────────
        // For each WB round that produces losers, find the matching LB drop round.
        foreach ( $lb_schedule as $lb_round => $sched ) {
            if ( $sched['type'] !== 'init' && $sched['type'] !== 'drop' ) continue;
            if ( $sched['type'] === 'elim' ) continue;

            $wb_r        = $sched['wb_round'];
            $wb_count    = count( $wb_ids[ $wb_r ] ?? [] );
            $lb_count    = $sched['matches'];

            if ( $sched['type'] === 'init' ) {
                // LB R1: pair consecutive WB-R1 losers into LB matches
                // WB M1+M2 losers → LB M1, WB M3+M4 losers → LB M2, etc.
                for ( $m = 1; $m <= $wb_count; $m++ ) {
                    $lb_m = (int) ceil( $m / 2 );
                    if ( isset( $wb_ids[ $wb_r ][ $m ], $lb_ids[ $lb_round ][ $lb_m ] ) ) {
                        $wpdb->update(
                            $wpdb->prefix . 'ptm_matches',
                            [ 'loser_next_match_id' => $lb_ids[ $lb_round ][ $lb_m ] ],
                            [ 'id' => $wb_ids[ $wb_r ][ $m ] ],
                            [ '%d' ], [ '%d' ]
                        );
                    }
                }
            } else {
                // Drop round: 1-to-1 mapping WB loser → LB match slot
                // WB M1 loser → LB M1, WB M2 loser → LB M2, etc.
                for ( $m = 1; $m <= $wb_count; $m++ ) {
                    if ( isset( $wb_ids[ $wb_r ][ $m ], $lb_ids[ $lb_round ][ $m ] ) ) {
                        $wpdb->update(
                            $wpdb->prefix . 'ptm_matches',
                            [ 'loser_next_match_id' => $lb_ids[ $lb_round ][ $m ] ],
                            [ 'id' => $wb_ids[ $wb_r ][ $m ] ],
                            [ '%d' ], [ '%d' ]
                        );
                    }
                }
            }
        }

        // ── Place players into WB round 1 ─────────────────────────────────────
        self::place_players_single( $slots, $wb_ids[1], (int) $tournament['id'], $tournament );

        return $total;
    }

    // ── Player placement ──────────────────────────────────────────────────────

    private static function place_players_single( array $slots, array $round1_matches, int $tournament_id, array $tournament ): void {
        global $wpdb;
        $match_list = array_values( $round1_matches );

        foreach ( $match_list as $idx => $match_id ) {
            $p1_slot = $slots[ $idx * 2 ]     ?? null;
            $p2_slot = $slots[ $idx * 2 + 1 ] ?? null;

            $p1_id = $p1_slot ? (int) $p1_slot['player_id'] : null;
            $p2_id = $p2_slot ? (int) $p2_slot['player_id'] : null;

            // Handle BYE — if one player is null, the other auto-advances
            if ( ! $p2_id && $p1_id ) {
                // Auto-advance p1 — mark match complete with p1 as winner
                $wpdb->update(
                    $wpdb->prefix . 'ptm_matches',
                    [ 'player1_id' => $p1_id, 'status' => 'complete', 'winner_id' => $p1_id ],
                    [ 'id' => $match_id ],
                    [ '%d', '%s', '%d' ], [ '%d' ]
                );
                // Advance immediately
                $match = PTM_Match::get( $match_id );
                if ( $match && $match['next_match_id'] ) {
                    self::place_player_in_next( (int) $match['next_match_id'], $p1_id );
                }
                continue;
            }

            // Resolve handicap race-to if needed
            $p1_skill = $p1_slot ? ( $p1_slot['skill_level'] ?? null ) : null;
            $p2_skill = $p2_slot ? ( $p2_slot['skill_level'] ?? null ) : null;
            $race     = PTM_Tournament::resolve_race_to( $tournament_id, $p1_skill ? (int) $p1_skill : null, $p2_skill ? (int) $p2_skill : null );

            $wpdb->update(
                $wpdb->prefix . 'ptm_matches',
                [
                    'player1_id'     => $p1_id,
                    'player2_id'     => $p2_id,
                    'race_to_player1' => $race['race_to_a'],
                    'race_to_player2' => $race['race_to_b'],
                ],
                [ 'id' => $match_id ],
                [ '%d', '%d', '%d', '%d' ], [ '%d' ]
            );
        }
    }

    private static function place_player_in_next( int $match_id, int $player_id ): void {
        global $wpdb;
        $match = PTM_Match::get( $match_id );
        if ( ! $match ) return;
        $slot = is_null( $match['player1_id'] ) ? 'player1_id' : 'player2_id';
        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ $slot => $player_id ],
            [ 'id' => $match_id ],
            [ '%d' ], [ '%d' ]
        );
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    private static function insert_match( array $data ): int {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ptm_matches',
            [
                'tournament_id'       => $data['tournament_id'],
                'bracket_side'        => $data['bracket_side'],
                'round'               => $data['round'],
                'match_number'        => $data['match_number'],
                'race_to_player1'     => $data['race_to_p1'],
                'race_to_player2'     => $data['race_to_p2'],
                'next_match_id'       => $data['next_match_id'] ?? null,
                'loser_next_match_id' => $data['loser_next_match_id'] ?? null,
                'score_token'         => PTM_Match::generate_token(),
            ],
            [ '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ]
        );
        return (int) $wpdb->insert_id;
    }

    // ── Seeding helpers ───────────────────────────────────────────────────────

    /**
     * Build a slot array of size $bracket_size with BYE nulls distributed
     * so that top seeds avoid early bye matches where possible.
     */
    private static function build_seeded_slots( array $roster, int $bracket_size ): array {
        $slots    = array_fill( 0, $bracket_size, null );
        $bye_count = $bracket_size - count( $roster );

        // Standard seeding pattern: 1 vs last, 2 vs second-last, etc.
        // Fill slots using the standard bracket positions
        $positions = self::bracket_positions( $bracket_size );

        foreach ( $roster as $idx => $player ) {
            $slots[ $positions[ $idx ] ] = $player;
        }

        return $slots;
    }

    /**
     * Returns the canonical bracket slot positions for a given bracket size.
     * Ensures seed 1 is always top-left, seed 2 bottom-right, etc.
     */
    private static function bracket_positions( int $size ): array {
        if ( $size === 2 ) return [ 0, 1 ];
        $half  = $size / 2;
        $upper = self::bracket_positions( $half );
        $lower = array_map( fn( $p ) => $p + $half, self::bracket_positions( $half ) );
        $result = [];
        foreach ( $upper as $i => $pos ) {
            $result[] = $pos;
            $result[] = $lower[ $i ];
        }
        return $result;
    }

    /**
     * Round up to the nearest power of 2.
     */
    public static function next_power_of_2( int $n ): int {
        if ( $n <= 1 ) return 1;
        return (int) pow( 2, ceil( log( $n, 2 ) ) );
    }

    // ── Public read methods ───────────────────────────────────────────────────

    /**
     * Returns the full bracket for a tournament, keyed by bracket_side → round → matches[].
     * Each match row is a plain array with player names joined in.
     * Used by the admin bracket view, REST API, and public shortcode.
     *
     * @param int $tournament_id
     * @return array<string, array<int, array[]>>
     */
    public static function get_bracket( int $tournament_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*,
                        p1.name AS player1_name,
                        p2.name AS player2_name,
                        pw.name AS winner_name
                 FROM {$wpdb->prefix}ptm_matches m
                 LEFT JOIN {$wpdb->prefix}ptm_players p1 ON p1.id = m.player1_id
                 LEFT JOIN {$wpdb->prefix}ptm_players p2 ON p2.id = m.player2_id
                 LEFT JOIN {$wpdb->prefix}ptm_players pw ON pw.id = m.winner_id
                 WHERE m.tournament_id = %d
                 ORDER BY m.bracket_side ASC, m.round ASC, m.match_number ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        // Sort into [ side ][ round ][ matches ]
        $bracket = [];
        foreach ( $rows as $row ) {
            $side  = $row['bracket_side'];
            $round = (int) $row['round'];
            $bracket[ $side ][ $round ][] = $row;
        }

        return $bracket;
    }

}
