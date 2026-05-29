<?php
defined( 'ABSPATH' ) || exit;

/**
 * Match CRUD and score entry logic.
 * Handles per-game score updates, match completion, and bracket advancement.
 */
class PTM_Match {

    // ── Fetch ─────────────────────────────────────────────────────────────────

    public static function get( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ptm_matches WHERE id = %d", $id ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function get_by_token( string $token ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptm_matches WHERE score_token = %s",
                $token
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function get_tournament_matches( int $tournament_id ): array {
        global $wpdb;
        return $wpdb->get_results(
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
                 ORDER BY m.bracket_side, m.round, m.match_number",
                $tournament_id
            ),
            ARRAY_A
        );
    }

    // ── Score entry ───────────────────────────────────────────────────────────

    /**
     * Increment one player's score by 1 game.
     *
     * @param int    $match_id
     * @param int    $player_slot  1 or 2
     * @return array  [ 'match' => updated match row, 'completed' => bool, 'error' => string|null ]
     */
    public static function increment_score( int $match_id, int $player_slot ): array {
        global $wpdb;

        $match = self::get( $match_id );

        if ( ! $match ) {
            return [ 'match' => null, 'completed' => false, 'error' => 'Match not found.' ];
        }
        if ( $match['status'] === 'complete' ) {
            return [ 'match' => $match, 'completed' => true, 'error' => 'Match is already complete.' ];
        }
        if ( ! in_array( $player_slot, [ 1, 2 ], true ) ) {
            return [ 'match' => $match, 'completed' => false, 'error' => 'Invalid player slot.' ];
        }

        // Mark in_progress if still pending
        if ( $match['status'] === 'pending' ) {
            $wpdb->update(
                $wpdb->prefix . 'ptm_matches',
                [ 'status' => 'in_progress' ],
                [ 'id' => $match_id ],
                [ '%s' ], [ '%d' ]
            );
        }

        $score_col  = "player{$player_slot}_score";
        $race_col   = "race_to_player{$player_slot}";
        $new_score  = (int) $match[ $score_col ] + 1;

        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ $score_col => $new_score ],
            [ 'id' => $match_id ],
            [ '%d' ], [ '%d' ]
        );

        $match[ $score_col ] = $new_score;

        // Check for match completion
        if ( $new_score >= (int) $match[ $race_col ] ) {
            $winner_id = (int) $match[ "player{$player_slot}_id" ];
            $loser_id  = (int) $match[ $player_slot === 1 ? 'player2_id' : 'player1_id' ];
            self::complete_match( $match, $winner_id, $loser_id );
            $match = self::get( $match_id ); // fresh copy
            return [ 'match' => $match, 'completed' => true, 'error' => null ];
        }

        $match = self::get( $match_id );
        return [ 'match' => $match, 'completed' => false, 'error' => null ];
    }

    /**
     * Decrement a score by 1 (correction / undo).
     */
    public static function decrement_score( int $match_id, int $player_slot ): array {
        global $wpdb;

        $match = self::get( $match_id );
        if ( ! $match ) {
            return [ 'match' => null, 'error' => 'Match not found.' ];
        }
        if ( $match['status'] === 'complete' ) {
            return [ 'match' => $match, 'error' => 'Cannot edit a completed match. Use reopen.' ];
        }

        $score_col = "player{$player_slot}_score";
        $new_score = max( 0, (int) $match[ $score_col ] - 1 );

        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ $score_col => $new_score ],
            [ 'id' => $match_id ],
            [ '%d' ], [ '%d' ]
        );

        return [ 'match' => self::get( $match_id ), 'error' => null ];
    }

    /**
     * Reopen a completed match (organizer correction).
     * Clears winner, resets status, and reverses bracket advancement.
     */
    /**
     * Corrects scores on a completed match and re-determines the winner.
     * Rolls back bracket advancement for the old winner/loser and
     * re-advances based on the corrected scores.
     *
     * @param int $match_id
     * @param int $p1_score  New player 1 score
     * @param int $p2_score  New player 2 score
     * @return array [ 'success' => bool, 'error' => string|null ]
     */
    public static function correct_score( int $match_id, int $p1_score, int $p2_score ): array {
        global $wpdb;

        $match = self::get( $match_id );
        if ( ! $match ) {
            return [ 'success' => false, 'error' => 'Match not found.' ];
        }
        if ( ! $match['player1_id'] || ! $match['player2_id'] ) {
            return [ 'success' => false, 'error' => 'Match does not have two players.' ];
        }

        $was_complete   = $match['status'] === 'complete';
        $old_winner_id  = (int) $match['winner_id'];
        $old_loser_id   = $old_winner_id === (int) $match['player1_id']
            ? (int) $match['player2_id']
            : (int) $match['player1_id'];

        // Determine new winner from corrected scores
        $p1_race = (int) $match['race_to_player1'];
        $p2_race = (int) $match['race_to_player2'];
        $p1_wins = $p1_score >= $p1_race;
        $p2_wins = $p2_score >= $p2_race;

        if ( ! $p1_wins && ! $p2_wins ) {
            // Neither player at race-to yet — reopen as in_progress
            $wpdb->update(
                $wpdb->prefix . 'ptm_matches',
                [ 'status' => 'in_progress', 'winner_id' => null,
                  'player1_score' => $p1_score, 'player2_score' => $p2_score ],
                [ 'id' => $match_id ],
                [ '%s', null, '%d', '%d' ], [ '%d' ]
            );

            // If was complete, roll back the players it had advanced
            if ( $was_complete ) {
                self::rollback_advancement( $match, $old_winner_id, $old_loser_id );
            }

            return [ 'success' => true, 'error' => null, 'completed' => false ];
        }

        $new_winner_id = $p1_wins ? (int) $match['player1_id'] : (int) $match['player2_id'];
        $new_loser_id  = $p1_wins ? (int) $match['player2_id'] : (int) $match['player1_id'];

        // Update scores and winner
        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ 'status' => 'complete', 'winner_id' => $new_winner_id,
              'player1_score' => $p1_score, 'player2_score' => $p2_score ],
            [ 'id' => $match_id ],
            [ '%s', '%d', '%d', '%d' ], [ '%d' ]
        );

        // If the winner changed, we need to roll back old advancement and re-advance
        if ( $was_complete && $new_winner_id !== $old_winner_id ) {
            self::rollback_advancement( $match, $old_winner_id, $old_loser_id );
            // Re-advance with corrected winner/loser
            if ( $match['next_match_id'] ) {
                self::place_player( (int) $match['next_match_id'], $new_winner_id );
            }
            if ( $match['loser_next_match_id'] ) {
                self::place_player( (int) $match['loser_next_match_id'], $new_loser_id );
            }
        } elseif ( ! $was_complete ) {
            // Was in_progress, now completing — advance normally
            if ( $match['next_match_id'] ) {
                self::place_player( (int) $match['next_match_id'], $new_winner_id );
            }
            if ( $match['loser_next_match_id'] ) {
                self::place_player( (int) $match['loser_next_match_id'], $new_loser_id );
            }
        }

        return [ 'success' => true, 'error' => null, 'completed' => true ];
    }

    /**
     * Rolls back a previously advanced winner/loser from downstream matches.
     * Removes those players from the next match slots and resets downstream
     * matches to pending if they haven't started yet.
     */
    private static function rollback_advancement( array $match, int $winner_id, int $loser_id ): void {
        global $wpdb;

        // Remove winner from next_match
        if ( $match['next_match_id'] ) {
            $next = self::get( (int) $match['next_match_id'] );
            if ( $next && $next['status'] !== 'complete' ) {
                $col = (int) $next['player1_id'] === $winner_id ? 'player1_id' : 'player2_id';
                $wpdb->update( $wpdb->prefix . 'ptm_matches', [ $col => null, 'status' => 'pending' ], [ 'id' => $next['id'] ], [ null, '%s' ], [ '%d' ] );
            }
        }

        // Remove loser from loser_next_match
        if ( $match['loser_next_match_id'] ) {
            $next = self::get( (int) $match['loser_next_match_id'] );
            if ( $next && $next['status'] !== 'complete' ) {
                $col = (int) $next['player1_id'] === $loser_id ? 'player1_id' : 'player2_id';
                $wpdb->update( $wpdb->prefix . 'ptm_matches', [ $col => null, 'status' => 'pending' ], [ 'id' => $next['id'] ], [ null, '%s' ], [ '%d' ] );
            }
        }
    }

    public static function reopen( int $match_id ): array {
        return self::correct_score( $match_id, 0, 0 );
    }

    // ── Completion & advancement ──────────────────────────────────────────────

    private static function complete_match( array $match, int $winner_id, int $loser_id ): void {
        global $wpdb;

        // Mark complete and record winner
        $wpdb->update(
            $wpdb->prefix . 'ptm_matches',
            [ 'status' => 'complete', 'winner_id' => $winner_id ],
            [ 'id' => $match['id'] ],
            [ '%s', '%d' ], [ '%d' ]
        );

        // Grand Finals main match (double elim): only go to bracket reset if the
        // losers-bracket player won. If the winners-bracket player wins, it's over.
        if ( $match['bracket_side'] === 'finals' && (int) $match['round'] === 1 && $match['next_match_id'] ) {
            $wb_final_winner = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT winner_id FROM {$wpdb->prefix}ptm_matches
                  WHERE next_match_id = %d AND bracket_side = 'winners'",
                $match['id']
            ) );

            if ( $winner_id !== $wb_final_winner ) {
                // LB player won — send both players to the bracket reset
                self::place_player( (int) $match['next_match_id'], $winner_id );
                self::place_player( (int) $match['next_match_id'], $loser_id );
            }
            // WB player won — no reset, fall through to maybe_complete_tournament
        } else {
            // Advance winner to next match
            if ( $match['next_match_id'] ) {
                self::place_player( (int) $match['next_match_id'], $winner_id );
            }

            // Route loser in double elimination
            if ( $match['loser_next_match_id'] ) {
                self::place_player( (int) $match['loser_next_match_id'], $loser_id );
            }
        }

        // Record stats for both players
        $p1_id = (int) $match['player1_id'];
        $p2_id = (int) $match['player2_id'];
        if ( $p1_id ) {
            PTM_Player::record_match_result(
                $p1_id,
                (int) $match['tournament_id'],
                $p1_id === $winner_id,
                (int) $match['player1_score'],
                (int) $match['player2_score']
            );
        }
        if ( $p2_id ) {
            PTM_Player::record_match_result(
                $p2_id,
                (int) $match['tournament_id'],
                $p2_id === $winner_id,
                (int) $match['player2_score'],
                (int) $match['player1_score']
            );
        }

        // Check if the whole tournament is now complete
        self::maybe_complete_tournament( (int) $match['tournament_id'] );

        // Free this match's table and immediately assign the next waiting match
        PTM_Tables::on_match_complete( (int) $match['tournament_id'], (int) $match['id'] );
    }

    /**
     * Place a player into the next available slot of a future match.
     */
    private static function place_player( int $match_id, int $player_id ): void {
        global $wpdb;
        $next = self::get( $match_id );
        if ( ! $next ) return;

        if ( is_null( $next['player1_id'] ) ) {
            $wpdb->update(
                $wpdb->prefix . 'ptm_matches',
                [ 'player1_id' => $player_id ],
                [ 'id' => $match_id ],
                [ '%d' ], [ '%d' ]
            );
        } elseif ( is_null( $next['player2_id'] ) ) {
            $wpdb->update(
                $wpdb->prefix . 'ptm_matches',
                [ 'player2_id' => $player_id ],
                [ 'id' => $match_id ],
                [ '%d' ], [ '%d' ]
            );
        }
    }

    private static function maybe_complete_tournament( int $tournament_id ): void {
        global $wpdb;

        // Are there any matches still in play?
        $incomplete = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptm_matches
                 WHERE tournament_id = %d
                   AND status != 'complete'
                   AND player1_id IS NOT NULL
                   AND player2_id IS NOT NULL",
                $tournament_id
            )
        );

        if ( $incomplete > 0 ) return;

        // Mark complete and run the full finish-position assignment
        PTM_Tournament::set_status( $tournament_id, 'complete' );
        self::finalize_finish_positions( $tournament_id );
    }


    private static function set_finish_position( int $player_id, int $tournament_id, int $position ): void {
        global $wpdb;
        // Upsert: create stats row if missing, then set finish_position
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}ptm_player_stats
                    (player_id, tournament_id, finish_position)
                 VALUES (%d, %d, %d)
                 ON DUPLICATE KEY UPDATE finish_position = %d",
                $player_id, $tournament_id, $position, $position
            )
        );
    }

    /**
     * Forcefully assigns finish positions for all players in a tournament.
     * Called by the Finalize Results admin button.
     * Works unconditionally — does NOT check for incomplete matches.
     * Clears all existing finish positions first, then re-assigns from scratch.
     */
    public static function finalize_finish_positions( int $tournament_id ): void {
        global $wpdb;

        // Clear existing finish positions so we get a clean assignment
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptm_player_stats
                 SET finish_position = NULL
                 WHERE tournament_id = %d",
                $tournament_id
            )
        );

        // Find the final match: last complete match with no next_match_id
        // For double-elim, treat the GF reset (finals R2) as the final if played,
        // otherwise use GF main (finals R1)
        $final = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptm_matches
                 WHERE tournament_id = %d
                   AND status        = 'complete'
                   AND next_match_id IS NULL
                   AND player1_id    IS NOT NULL
                   AND player2_id    IS NOT NULL
                 ORDER BY id DESC
                 LIMIT 1",
                $tournament_id
            ),
            ARRAY_A
        );

        if ( ! $final || ! $final['winner_id'] ) {
            // No final match found — try any complete match with highest round
            $final = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ptm_matches
                     WHERE tournament_id = %d
                       AND status        = 'complete'
                       AND player1_id    IS NOT NULL
                       AND player2_id    IS NOT NULL
                     ORDER BY
                         FIELD(bracket_side, 'finals', 'winners', 'losers'),
                         round DESC
                     LIMIT 1",
                    $tournament_id
                ),
                ARRAY_A
            );
        }

        if ( ! $final || ! $final['winner_id'] ) return;

        $champion_id = (int) $final['winner_id'];
        $runner_up   = $champion_id === (int) $final['player1_id']
            ? (int) $final['player2_id']
            : (int) $final['player1_id'];

        // 1st and 2nd place
        self::set_finish_position( $champion_id, $tournament_id, 1 );
        if ( $runner_up ) {
            self::set_finish_position( $runner_up, $tournament_id, 2 );
        }

        // All other eliminated players — group by when they were knocked out
        // A player is "finally eliminated" in the last complete match they lost
        // where they had no loser_next_match_id (nowhere else to go)
        $eliminated = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.bracket_side, m.round,
                        CASE WHEN m.winner_id = m.player1_id THEN m.player2_id
                             ELSE m.player1_id END AS loser_id
                 FROM {$wpdb->prefix}ptm_matches m
                 WHERE m.tournament_id        = %d
                   AND m.status              = 'complete'
                   AND m.loser_next_match_id  IS NULL
                   AND m.player1_id          IS NOT NULL
                   AND m.player2_id          IS NOT NULL
                   AND m.id                  != %d
                 ORDER BY m.bracket_side DESC, m.round DESC",
                $tournament_id,
                (int) $final['id']
            ),
            ARRAY_A
        );

        // Group by round+side (players knocked out same round share a position)
        $groups   = [];
        foreach ( $eliminated as $row ) {
            $key = $row['bracket_side'] . '_' . $row['round'];
            $groups[ $key ][] = (int) $row['loser_id'];
        }

        // Assign positions 3rd and below
        $assigned = 2;
        foreach ( $groups as $losers ) {
            $count    = count( $losers );
            $position = $assigned + 1;
            foreach ( $losers as $pid ) {
                if ( $pid ) self::set_finish_position( $pid, $tournament_id, $position );
            }
            $assigned += $count;
        }

        // Record money won based on payout rules
        $payouts = PTM_Tournament::calculate_payouts( $tournament_id );
        if ( ! empty( $payouts ) ) {
            // Reset money_won for this tournament first
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ptm_player_stats SET money_won = 0.00 WHERE tournament_id = %d",
                    $tournament_id
                )
            );

            // Get all finish positions for this tournament
            $positions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT player_id, finish_position FROM {$wpdb->prefix}ptm_player_stats
                     WHERE tournament_id = %d AND finish_position IS NOT NULL",
                    $tournament_id
                ),
                ARRAY_A
            );

            foreach ( $positions as $row ) {
                $pos = (int) $row['finish_position'];
                foreach ( $payouts as $rule ) {
                    if ( $pos >= (int) $rule['position_from'] && $pos <= (int) $rule['position_to'] ) {
                        $wpdb->update(
                            $wpdb->prefix . 'ptm_player_stats',
                            [ 'money_won' => $rule['amount'] ],
                            [ 'player_id' => (int) $row['player_id'], 'tournament_id' => $tournament_id ],
                            [ '%f' ],
                            [ '%d', '%d' ]
                        );
                        break;
                    }
                }
            }
        }
    }

    // ── Convenience aliases (used by REST + admin AJAX) ───────────────────────

    /**
     * Alias: increment a player's score by one game.
     * Returns same shape as increment_score().
     */
    public static function add_game( int $match_id, int $player_slot ): array {
        return self::increment_score( $match_id, $player_slot );
    }

    /**
     * Alias: decrement a player's score by one game.
     */
    public static function remove_game( int $match_id, int $player_slot ): array {
        return self::decrement_score( $match_id, $player_slot );
    }

    /**
     * Returns the most-recent updated_at timestamp for any match in a
     * tournament. Used by the polling endpoint to cheaply detect changes.
     */
    public static function get_last_updated( int $tournament_id ): ?string {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(updated_at) FROM {$wpdb->prefix}ptm_matches WHERE tournament_id = %d",
                $tournament_id
            )
        );
    }

    // ── Token generation ──────────────────────────────────────────────────────

    public static function generate_token(): string {
        return bin2hex( random_bytes( 24 ) );
    }
}
