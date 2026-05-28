<?php
defined( 'ABSPATH' ) || exit;

/**
 * PTM_Tables
 *
 * Manages physical table assignments for a tournament.
 *
 * Assignment strategy (greedy / always-on):
 *  1. When called, find every table that is currently FREE
 *     (no in_progress match assigned to it).
 *  2. For each free table, find the highest-priority READY match
 *     (both players assigned, status = pending, no table yet).
 *  3. Priority order: Winners > Losers > Finals, then by round ASC
 *     (earlier rounds first so the bracket progresses evenly),
 *     then by match_number ASC.
 *  4. Assign the match to that table and mark it in_progress.
 *
 * This is called:
 *  - After bracket generation (seed the first wave of matches).
 *  - After any match completes (free up that table, immediately fill it).
 */
class PTM_Tables {

    /**
     * Run the full assignment pass for a tournament.
     * Safe to call multiple times — idempotent.
     *
     * @param int $tournament_id
     * @return int  Number of new assignments made
     */
    public static function assign( int $tournament_id ): int {
        global $wpdb;

        $tournament = PTM_Tournament::get( $tournament_id );
        if ( ! $tournament ) return 0;

        $num_tables = max( 1, (int) $tournament['num_tables'] );
        $assigned   = 0;

        // Find which table numbers are currently occupied
        $occupied = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT table_number
                 FROM {$wpdb->prefix}ptm_matches
                 WHERE tournament_id = %d
                   AND status = 'in_progress'
                   AND table_number IS NOT NULL",
                $tournament_id
            )
        );
        $occupied = array_map( 'intval', $occupied );

        // Determine free tables (1..num_tables minus occupied)
        $free_tables = [];
        for ( $t = 1; $t <= $num_tables; $t++ ) {
            if ( ! in_array( $t, $occupied, true ) ) {
                $free_tables[] = $t;
            }
        }

        if ( empty( $free_tables ) ) {
            return 0; // All tables busy
        }

        // Fetch all ready matches in priority order
        $ready = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, bracket_side, round, match_number
                 FROM {$wpdb->prefix}ptm_matches
                 WHERE tournament_id  = %d
                   AND status         = 'pending'
                   AND table_number   IS NULL
                   AND player1_id     IS NOT NULL
                   AND player2_id     IS NOT NULL
                 ORDER BY
                     FIELD(bracket_side, 'winners', 'losers', 'finals'),
                     round ASC,
                     match_number ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        if ( empty( $ready ) ) {
            return 0; // Nothing waiting
        }

        // Assign one ready match per free table
        foreach ( $free_tables as $table_num ) {
            if ( empty( $ready ) ) break;

            $match = array_shift( $ready );

            $wpdb->update(
                $wpdb->prefix . 'ptm_matches',
                [
                    'table_number' => $table_num,
                    'status'       => 'in_progress',
                ],
                [ 'id' => (int) $match['id'] ],
                [ '%d', '%s' ],
                [ '%d' ]
            );

            $assigned++;
        }

        return $assigned;
    }

    /**
     * Free the table for a match that just completed and immediately
     * re-run assignment so a new match fills the gap.
     *
     * Called by PTM_Match::complete_match() after marking a match done.
     *
     * @param int $tournament_id
     * @param int $completed_match_id
     */
    public static function on_match_complete( int $tournament_id, int $completed_match_id ): void {
        // The completed match already has status='complete' in the DB by the
        // time we're called, so it won't count as occupying a table any more.
        // Just run a fresh assignment pass.
        self::assign( $tournament_id );
    }

    /**
     * Returns the current table status summary for a tournament.
     * Useful for the admin dashboard display.
     *
     * @param int $tournament_id
     * @return array  [ table_number => match_row|null, ... ]
     */
    public static function get_table_status( int $tournament_id ): array {
        global $wpdb;

        $tournament = PTM_Tournament::get( $tournament_id );
        if ( ! $tournament ) return [];

        $num_tables = max( 1, (int) $tournament['num_tables'] );

        // Fetch all in-progress matches with table assignments
        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*,
                        p1.name AS player1_name,
                        p2.name AS player2_name
                 FROM {$wpdb->prefix}ptm_matches m
                 LEFT JOIN {$wpdb->prefix}ptm_players p1 ON p1.id = m.player1_id
                 LEFT JOIN {$wpdb->prefix}ptm_players p2 ON p2.id = m.player2_id
                 WHERE m.tournament_id = %d
                   AND m.table_number IS NOT NULL
                   AND m.status IN ('in_progress', 'pending')
                 ORDER BY m.table_number ASC",
                $tournament_id
            ),
            ARRAY_A
        );

        // Build table_num → match map
        $by_table = [];
        foreach ( $matches as $m ) {
            $by_table[ (int) $m['table_number'] ] = $m;
        }

        // Return all tables, even empty ones
        $status = [];
        for ( $t = 1; $t <= $num_tables; $t++ ) {
            $status[ $t ] = isset( $by_table[ $t ] ) ? (object) $by_table[ $t ] : null;
        }

        return $status;
    }

    /**
     * Returns the number of matches currently waiting for a table.
     *
     * @param int $tournament_id
     * @return int
     */
    public static function count_waiting( int $tournament_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptm_matches
                 WHERE tournament_id = %d
                   AND status        = 'pending'
                   AND table_number  IS NULL
                   AND player1_id    IS NOT NULL
                   AND player2_id    IS NOT NULL",
                $tournament_id
            )
        );
    }
}
