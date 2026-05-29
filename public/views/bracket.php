<?php if ( ! defined( 'ABSPATH' ) ) exit;
$is_double = $tournament->bracket_type === 'double_elim';
$sides     = $is_double ? [ 'winners', 'losers', 'finals' ] : [ 'winners' ];
?>
<div class="ptm-bracket-public" data-tournament="<?php echo $tournament->id; ?>">

    <div class="ptm-bracket-public-header">
        <h2 class="ptm-bracket-public-title"><?php echo esc_html( $tournament->name ); ?></h2>
        <div class="ptm-bracket-public-meta">
            <?php echo esc_html( strtoupper( $tournament->game_type ) ); ?>
            &nbsp;·&nbsp;
            <?php echo $is_double ? __( 'Double Elimination', 'ptm-tournaments' ) : __( 'Single Elimination', 'ptm-tournaments' ); ?>
            <?php if ( $tournament->tournament_date ) : ?>
                &nbsp;·&nbsp; <?php echo esc_html( date( 'F j, Y', strtotime( $tournament->tournament_date ) ) ); ?>
            <?php endif; ?>
        </div>
        <?php if ( $tournament->status === 'active' ) : ?>
            <div class="ptm-live-badge">🔴 LIVE</div>
        <?php elseif ( $tournament->status === 'complete' ) : ?>
            <div class="ptm-complete-badge">✓ Complete</div>
            <a href="<?php echo esc_url( PTM_Tournament::get_url( (array) $tournament, 'results' ) ); ?>" class="ptm-results-link">
                🏆 <?php _e( 'View Final Results & Payouts', 'ptm-tournaments' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ( $tournament->status === 'active' ) : ?>
    <div class="ptm-pub-tables" id="ptm-pub-tables-<?php echo $tournament->id; ?>" data-tournament="<?php echo $tournament->id; ?>">
        <div class="ptm-pub-tables-header"><?php _e( 'Tables', 'ptm-tournaments' ); ?></div>
        <div class="ptm-pub-tables-grid" id="ptm-pub-tables-grid-<?php echo $tournament->id; ?>">
            <div class="ptm-pub-tables-loading"><?php _e( 'Loading table status…', 'ptm-tournaments' ); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $is_double ) : ?>
    <div class="ptm-bracket-public-tabs">
        <button class="ptm-pub-tab active" data-tab="winners"><?php _e( 'Winners', 'ptm-tournaments' ); ?></button>
        <button class="ptm-pub-tab" data-tab="losers"><?php _e( 'Losers', 'ptm-tournaments' ); ?></button>
        <button class="ptm-pub-tab" data-tab="finals"><?php _e( 'Finals', 'ptm-tournaments' ); ?></button>
    </div>
    <?php endif; ?>

    <?php foreach ( $sides as $side ) :
        $rounds = isset( $bracket[ $side ] ) ? $bracket[ $side ] : [];
    ?>
    <div class="ptm-bracket-pub-section ptm-pub-tab-content" id="ptm-pub-<?php echo $side; ?>"
         <?php echo ( $is_double && $side !== 'winners' ) ? 'style="display:none"' : ''; ?>>

        <div class="ptm-bracket-pub-rounds">
            <?php foreach ( $rounds as $round_num => $matches ) : ?>
            <div class="ptm-pub-round">
                <div class="ptm-pub-round-label">
                    <?php
                    if ( $side === 'finals' ) {
                        echo $round_num === 1 ? __( 'Grand Finals', 'ptm-tournaments' ) : __( 'Bracket Reset', 'ptm-tournaments' );
                    } else {
                        printf( __( 'Round %d', 'ptm-tournaments' ), $round_num );
                    }
                    ?>
                </div>
                <div class="ptm-pub-round-matches">
                    <?php foreach ( $matches as $match ) : ?>
                    <div class="ptm-pub-match ptm-pub-match--<?php echo esc_attr( $match->status ); ?>" data-match-id="<?php echo (int) $match->id; ?>">

                        <div class="ptm-pub-player <?php echo $match->status === 'complete' && $match->winner_id == $match->player1_id ? 'ptm-pub-winner' : ''; ?>
                                                   <?php echo $match->status === 'complete' && $match->winner_id != $match->player1_id && $match->player1_id ? 'ptm-pub-loser' : ''; ?>">
                            <span class="ptm-pub-name">
                                <?php echo $match->player1_name ? esc_html( $match->player1_name ) : '<em>TBD</em>'; ?>
                            </span>
                            <span class="ptm-pub-score"><?php echo $match->status !== 'pending' ? $match->player1_score : ''; ?></span>
                        </div>

                        <div class="ptm-pub-player <?php echo $match->status === 'complete' && $match->winner_id == $match->player2_id ? 'ptm-pub-winner' : ''; ?>
                                                   <?php echo $match->status === 'complete' && $match->winner_id != $match->player2_id && $match->player2_id ? 'ptm-pub-loser' : ''; ?>">
                            <span class="ptm-pub-name">
                                <?php echo $match->player2_name ? esc_html( $match->player2_name ) : '<em>TBD</em>'; ?>
                            </span>
                            <span class="ptm-pub-score"><?php echo $match->status !== 'pending' ? $match->player2_score : ''; ?></span>
                        </div>

                        <?php if ( $match->status === 'in_progress' ) : ?>
                            <div class="ptm-pub-live-dot">● LIVE</div>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
    <?php endforeach; ?>

    <div class="ptm-bracket-public-footer">
        <span class="ptm-pub-updated" id="ptm-pub-updated-<?php echo $tournament->id; ?>"></span>
    </div>

</div>
