<?php
/**
 * Tournament results page.
 * Variables: $tournament (object), $tournament_id (int), $results (array), $payouts (array)
 * Override: place ptm-tournaments/results.php in your child theme.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$player_count = PTM_Tournament::get_player_count( $tournament_id );
// Use the pre-calculated pot from calculate_payouts (accounts for director_fee and money_added).
// Fall back to entrance_fee * players if no payout rules are defined.
$total_pot = 0;
if ( ! empty( $payouts ) ) {
    $total_pot = (float) $payouts[0]['total_pot'];
} elseif ( (float) $tournament->entrance_fee > 0 ) {
    $director_fee = (float) ( $tournament->director_fee ?? 0 );
    $money_added  = (float) ( $tournament->money_added ?? 0 );
    $total_pot    = max( 0, $tournament->entrance_fee - $director_fee ) * $player_count + $money_added;
}

// Group results by finish position for tied places
$grouped = [];
foreach ( $results as $row ) {
    $pos = (int) $row['finish_position'];
    $grouped[ $pos ][] = $row;
}

// Build a payout lookup keyed by position range
$payout_by_pos = [];
foreach ( $payouts as $rule ) {
    for ( $p = (int) $rule['position_from']; $p <= (int) $rule['position_to']; $p++ ) {
        $payout_by_pos[ $p ] = $rule;
    }
}
?>
<div class="ptm-results">

    <div class="ptm-results-header">
        <h2 class="ptm-results-title"><?php echo esc_html( $tournament->name ); ?></h2>
        <div class="ptm-results-meta">
            <?php echo esc_html( strtoupper( $tournament->game_type ) ); ?>
            &nbsp;·&nbsp;
            <?php echo $tournament->bracket_type === 'double_elim'
                ? __( 'Double Elimination', 'ptm-tournaments' )
                : __( 'Single Elimination', 'ptm-tournaments' ); ?>
            <?php if ( $tournament->tournament_date ) : ?>
                &nbsp;·&nbsp; <?php echo esc_html( date( 'F j, Y', strtotime( $tournament->tournament_date ) ) ); ?>
            <?php endif; ?>
        </div>

        <?php if ( $total_pot > 0 ) : ?>
        <div class="ptm-results-pot">
            <span class="ptm-pot-label"><?php _e( 'Prize Pot', 'ptm-tournaments' ); ?></span>
            <span class="ptm-pot-amount">$<?php echo number_format( $total_pot, 2 ); ?></span>
            <span class="ptm-pot-breakdown">
                <?php printf(
                    __( '%d players × $%s entry', 'ptm-tournaments' ),
                    $player_count,
                    number_format( $fee, 2 )
                ); ?>
            </span>
        </div>
        <?php endif; ?>

        <div class="ptm-results-nav">
            <a href="<?php echo esc_url( PTM_Tournament::get_url( (array) $tournament ) ); ?>" class="ptm-results-bracket-link">
                ← <?php _e( 'View Bracket', 'ptm-tournaments' ); ?>
            </a>
        </div>
    </div>

    <?php
    // If no results yet but tournament is complete, auto-finalize
    if ( empty( $results ) && $tournament->status === 'complete' ) {
        PTM_Match::finalize_finish_positions( $tournament_id );
        $results = PTM_Tournament::get_results( $tournament_id );
    }
    ?>

    <?php if ( empty( $results ) ) : ?>
        <p class="ptm-results-empty">
            <?php if ( $tournament->status !== 'complete' ) : ?>
                <?php _e( 'Results will be available once the tournament is complete.', 'ptm-tournaments' ); ?>
            <?php else : ?>
                <?php _e( 'Results are being calculated. Please click the Finalize Results button in the admin bracket view.', 'ptm-tournaments' ); ?>
            <?php endif; ?>
        </p>
    <?php else : ?>

    <div class="ptm-results-table-wrap">
        <table class="ptm-results-table">
            <thead>
                <tr>
                    <th class="ptm-col-place"><?php _e( 'Place', 'ptm-tournaments' ); ?></th>
                    <th class="ptm-col-player"><?php _e( 'Player', 'ptm-tournaments' ); ?></th>
                    <th class="ptm-col-record"><?php _e( 'W–L', 'ptm-tournaments' ); ?></th>
                    <?php if ( $total_pot > 0 ) : ?>
                    <th class="ptm-col-payout"><?php _e( 'Prize', 'ptm-tournaments' ); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $rendered_positions = [];
                foreach ( $grouped as $pos => $players ) :
                    if ( in_array( $pos, $rendered_positions, true ) ) continue;
                    $rendered_positions[] = $pos;

                    $is_first  = $pos === 1;
                    $is_second = $pos === 2;
                    $is_third  = $pos === 3;
                    $medal     = $is_first ? '🏆' : ( $is_second ? '🥈' : ( $is_third ? '🥉' : '' ) );

                    // Determine place label
                    $count = count( $players );
                    if ( $count > 1 ) {
                        $place_label = $pos . '–' . ( $pos + $count - 1 ) . __( 'th', 'ptm-tournaments' );
                    } else {
                        $suffixes = [ 1 => 'st', 2 => 'nd', 3 => 'rd' ];
                        $suffix   = $suffixes[ $pos ] ?? 'th';
                        $place_label = $pos . $suffix;
                    }

                    // Payout for this position — amount is pre-calculated by calculate_payouts()
                    $prize_amount = 0;
                    if ( isset( $payout_by_pos[ $pos ] ) ) {
                        $prize_amount = (float) $payout_by_pos[ $pos ]['amount'];
                    }

                    foreach ( $players as $i => $player ) :
                        $row_class = $is_first ? 'ptm-row-first' : ( $is_second ? 'ptm-row-second' : ( $is_third ? 'ptm-row-third' : '' ) );
                ?>
                <tr class="<?php echo esc_attr( $row_class ); ?>">
                    <?php if ( $i === 0 ) : // Only show place cell for first player in group ?>
                    <td class="ptm-col-place" rowspan="<?php echo $count; ?>">
                        <?php if ( $medal ) : ?>
                            <span class="ptm-medal"><?php echo $medal; ?></span>
                        <?php endif; ?>
                        <span class="ptm-place-num"><?php echo esc_html( $place_label ); ?></span>
                    </td>
                    <?php endif; ?>
                    <td class="ptm-col-player"><?php echo esc_html( $player['name'] ); ?></td>
                    <td class="ptm-col-record"><?php echo (int)$player['matches_won']; ?>–<?php echo (int)$player['matches_lost']; ?></td>
                    <?php if ( $total_pot > 0 ) : ?>
                    <td class="ptm-col-payout">
                        <?php echo $prize_amount > 0 ? '<strong>$' . number_format( $prize_amount, 2 ) . '</strong>' : '—'; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php
                    endforeach;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>

    <?php if ( ! empty( $payouts ) && $total_pot > 0 ) :
        // Calculate total payout (sum of all per-player prizes × players at that level)
        $total_paid = 0;
        foreach ( $payouts as $rule ) {
            $positions_in_rule = (int) $rule['position_to'] - (int) $rule['position_from'] + 1;
            $total_paid += (float) $rule['amount'] * $positions_in_rule;
        }
    ?>
    <div class="ptm-payout-summary">
        <h3><?php _e( 'Payout Breakdown', 'ptm-tournaments' ); ?></h3>
        <p class="ptm-payout-note"><?php _e( 'Each player receives the listed amount. Percentages are of the total prize pot.', 'ptm-tournaments' ); ?></p>
        <div class="ptm-payout-bars">
            <?php foreach ( $payouts as $rule ) :
                $bar_pct           = min( 100, (float) $rule['pct'] );
                $positions_in_rule = (int) $rule['position_to'] - (int) $rule['position_from'] + 1;
                $per_player_label  = $positions_in_rule > 1 ? ' <em>(' . __( 'each', 'ptm-tournaments' ) . ')</em>' : '';
            ?>
            <div class="ptm-payout-bar-row">
                <span class="ptm-payout-bar-label"><?php echo esc_html( $rule['position_label'] ); ?></span>
                <div class="ptm-payout-bar-track">
                    <div class="ptm-payout-bar-fill" style="width:<?php echo $bar_pct; ?>%"></div>
                </div>
                <span class="ptm-payout-bar-pct"><?php echo (float) $rule['pct']; ?>%</span>
                <span class="ptm-payout-bar-amt">
                    $<?php echo number_format( (float) $rule['amount'], 2 ); ?><?php echo $per_player_label; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ( $total_paid < $total_pot ) : ?>
        <p class="ptm-payout-remainder">
            <?php printf(
                __( 'House retains: $%s', 'ptm-tournaments' ),
                number_format( $total_pot - $total_paid, 2 )
            ); ?>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>
