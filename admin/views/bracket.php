<?php if ( ! defined( 'ABSPATH' ) ) exit;
$is_double  = $tournament->bracket_type === 'double_elim';
$sides      = $is_double ? [ 'winners', 'losers', 'finals' ] : [ 'winners' ];
$num_tables = (int) $tournament->num_tables;
$waiting    = $tournament->status === 'active' ? PTM_Tables::count_waiting( $tournament_id ) : 0;
$table_status = $tournament->status === 'active' ? PTM_Tables::get_table_status( $tournament_id ) : [];
?>
<div class="wrap ptm-admin">

    <h1>
        <?php printf( __( 'Bracket: %s', 'ptm-tournaments' ), esc_html( $tournament->name ) ); ?>
        <span class="ptm-status ptm-status--<?php echo esc_attr( $tournament->status ); ?>">
            <?php echo esc_html( ucfirst( $tournament->status ) ); ?>
        </span>
    </h1>

    <div class="ptm-breadcrumb">
        <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments' ); ?>"><?php _e( '← All Tournaments', 'ptm-tournaments' ); ?></a>
        &nbsp;·&nbsp; <?php echo esc_html( strtoupper( $tournament->game_type ) ); ?>
        &nbsp;·&nbsp; <?php echo $is_double ? __( 'Double Elimination', 'ptm-tournaments' ) : __( 'Single Elimination', 'ptm-tournaments' ); ?>
        &nbsp;·&nbsp; <?php printf( __( '%d Tables', 'ptm-tournaments' ), $num_tables ); ?>
    </div>

    <!-- Table Status Dashboard -->
    <?php if ( $tournament->status === 'active' ) : ?>
    <div class="ptm-table-dashboard">
        <div class="ptm-table-dashboard-header">
            <h3><?php _e( 'Table Status', 'ptm-tournaments' ); ?></h3>
            <?php if ( $waiting > 0 ) : ?>
                <span class="ptm-waiting-badge"><?php printf( __( '%d match(es) waiting for a table', 'ptm-tournaments' ), $waiting ); ?></span>
            <?php else : ?>
                <span class="ptm-waiting-clear"><?php _e( '✓ All ready matches are assigned', 'ptm-tournaments' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="ptm-table-grid">
            <?php for ( $t = 1; $t <= $num_tables; $t++ ) :
                $tm = $table_status[ $t ] ?? null;
            ?>
            <div class="ptm-table-card <?php echo $tm ? 'ptm-table-busy' : 'ptm-table-free'; ?>">
                <div class="ptm-table-num">Table <?php echo $t; ?></div>
                <?php if ( $tm ) : ?>
                    <div class="ptm-table-players">
                        <span><?php echo esc_html( $tm->player1_name ?? 'TBD' ); ?></span>
                        <em>vs</em>
                        <span><?php echo esc_html( $tm->player2_name ?? 'TBD' ); ?></span>
                    </div>
                    <div class="ptm-table-score"><?php echo (int)$tm->player1_score; ?> – <?php echo (int)$tm->player2_score; ?></div>
                    <div class="ptm-table-side">
                        <?php echo ucfirst( $tm->bracket_side ); ?> · R<?php echo $tm->round; ?>
                    </div>
                    <?php if ( $tm->score_token ) :
                        $scorer_url = home_url( '/' . PTM_Settings::get( 'scorer_base_slug' ) . '/' . $tm->score_token );
                    ?>
                    <div class="ptm-table-qr-wrap">
                        <button type="button" class="ptm-qr-toggle ptm-table-qr-btn" data-url="<?php echo esc_attr( $scorer_url ); ?>" title="Click to enlarge QR code">
                            <?php echo PTM_QR::svg( $scorer_url, 80 ); ?>
                        </button>
                        <div class="ptm-qr-popover" style="display:none"><?php echo PTM_QR::svg( $scorer_url, 220 ); ?></div>
                        <a href="<?php echo esc_url( $scorer_url ); ?>" target="_blank" class="ptm-token-link">📱 Scorer</a>
                    </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="ptm-table-free-label"><?php _e( 'Free', 'ptm-tournaments' ); ?></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="ptm-bracket-toolbar">
        <button type="button" id="ptm-refresh-bracket" class="button">↻ <?php _e( 'Refresh', 'ptm-tournaments' ); ?></button>
        <span id="ptm-last-updated" class="ptm-last-updated"></span>
        <?php if ( $tournament->status === 'active' ) : ?>
        <a href="<?php echo esc_url( PTM_Tournament::get_url( (array) $tournament ) ); ?>"
           target="_blank" class="button" style="margin-left:10px;">
            👁 <?php _e( 'Public View', 'ptm-tournaments' ); ?>
        </a>
        <?php endif; ?>
        <?php if ( in_array( $tournament->status, [ 'active', 'complete' ], true ) ) : ?>
        <button type="button" id="ptm-finalize-results" class="button button-secondary" style="margin-left:10px;"
                data-tournament="<?php echo $tournament_id; ?>">
            🏆 <?php _e( 'Finalize Results', 'ptm-tournaments' ); ?>
        </button>
        <?php if ( $tournament->status === 'complete' ) : ?>
        <a href="<?php echo esc_url( PTM_Tournament::get_url( (array) $tournament, 'results' ) ); ?>"
           class="button" style="margin-left:6px;">
            📋 <?php _e( 'View Results', 'ptm-tournaments' ); ?>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bracket Tabs (double elim) -->
    <?php if ( $is_double ) : ?>
    <div class="ptm-bracket-tabs">
        <button class="ptm-tab active" data-tab="winners"><?php _e( 'Winners Bracket', 'ptm-tournaments' ); ?></button>
        <button class="ptm-tab" data-tab="losers"><?php _e( 'Losers Bracket', 'ptm-tournaments' ); ?></button>
        <button class="ptm-tab" data-tab="finals"><?php _e( 'Grand Finals', 'ptm-tournaments' ); ?></button>
    </div>
    <?php endif; ?>

    <!-- Bracket sections -->
    <?php foreach ( $sides as $side ) :
        $rounds = isset( $bracket[ $side ] ) ? $bracket[ $side ] : [];
    ?>
    <div class="ptm-bracket-section ptm-tab-content" id="ptm-bracket-<?php echo $side; ?>"
         <?php echo ( $is_double && $side !== 'winners' ) ? 'style="display:none"' : ''; ?>>

        <div class="ptm-bracket-rounds">
            <?php foreach ( $rounds as $round_num => $matches ) : ?>
            <div class="ptm-round">
                <div class="ptm-round-label">
                    <?php
                    if ( $side === 'finals' ) {
                        echo $round_num === 1 ? __( 'Grand Finals', 'ptm-tournaments' ) : __( 'Bracket Reset', 'ptm-tournaments' );
                    } else {
                        printf( __( 'Round %d', 'ptm-tournaments' ), $round_num );
                    }
                    ?>
                </div>
                <div class="ptm-round-matches">
                    <?php foreach ( $matches as $match ) :
                        $has_players = $match->player1_id && $match->player2_id;
                        $is_complete = $match->status === 'complete';
                        $table_num   = $match->table_number ? (int) $match->table_number : null;
                    ?>
                    <div class="ptm-match ptm-match--<?php echo esc_attr( $match->status ); ?>"
                         id="ptm-match-<?php echo $match->id; ?>"
                         data-match-id="<?php echo $match->id; ?>">

                        <?php if ( $table_num ) : ?>
                            <div class="ptm-match-table-badge">Table <?php echo $table_num; ?></div>
                        <?php endif; ?>

                        <!-- Player 1 -->
                        <div class="ptm-match-player <?php echo $is_complete && $match->winner_id == $match->player1_id ? 'ptm-winner' : ''; ?> <?php echo $is_complete && $match->player1_id && $match->winner_id != $match->player1_id ? 'ptm-loser' : ''; ?>">
                            <span class="ptm-player-name">
                                <?php echo $match->player1_name ? esc_html( $match->player1_name ) : '<em class="ptm-tbd">TBD</em>'; ?>
                            </span>
                            <span class="ptm-player-score"><?php echo (int) $match->player1_score; ?></span>
                            <?php if ( $has_players && ! $is_complete && $tournament->status === 'active' ) : ?>
                            <div class="ptm-score-controls">
                                <button type="button" class="ptm-score-btn ptm-score-minus" data-match="<?php echo $match->id; ?>" data-slot="1" data-action="remove">−</button>
                                <button type="button" class="ptm-score-btn ptm-score-plus"  data-match="<?php echo $match->id; ?>" data-slot="1" data-action="add">+</button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="ptm-match-vs">
                            <span class="ptm-race-to">
                                <?php if ( $match->race_to_player1 != $match->race_to_player2 ) : ?>
                                    R<?php echo (int)$match->race_to_player1; ?> / R<?php echo (int)$match->race_to_player2; ?>
                                <?php else : ?>
                                    Race to <?php echo (int)$match->race_to_player1; ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <!-- Player 2 -->
                        <div class="ptm-match-player <?php echo $is_complete && $match->winner_id == $match->player2_id ? 'ptm-winner' : ''; ?> <?php echo $is_complete && $match->player2_id && $match->winner_id != $match->player2_id ? 'ptm-loser' : ''; ?>">
                            <span class="ptm-player-name">
                                <?php echo $match->player2_name ? esc_html( $match->player2_name ) : '<em class="ptm-tbd">TBD</em>'; ?>
                            </span>
                            <span class="ptm-player-score"><?php echo (int) $match->player2_score; ?></span>
                            <?php if ( $has_players && ! $is_complete && $tournament->status === 'active' ) : ?>
                            <div class="ptm-score-controls">
                                <button type="button" class="ptm-score-btn ptm-score-minus" data-match="<?php echo $match->id; ?>" data-slot="2" data-action="remove">−</button>
                                <button type="button" class="ptm-score-btn ptm-score-plus"  data-match="<?php echo $match->id; ?>" data-slot="2" data-action="add">+</button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Table scorer link + QR -->
                        <?php if ( $has_players && ! $is_complete && $match->score_token ) :
                            $scorer_url = home_url( '/' . PTM_Settings::get( 'scorer_base_slug' ) . '/' . $match->score_token );
                        ?>
                        <div class="ptm-match-scorer-link">
                            <a href="<?php echo esc_url( $scorer_url ); ?>" target="_blank" class="ptm-token-link">📱 Table Scorer</a>
                            <button type="button" class="ptm-qr-toggle" data-url="<?php echo esc_attr( $scorer_url ); ?>" title="Show QR code">⊞ QR</button>
                            <div class="ptm-qr-popover" style="display:none">
                                <?php echo PTM_QR::svg( $scorer_url, 140 ); ?>
                                <p class="ptm-qr-label">Scan to open scorer</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ( $is_complete ) : ?>
                            <div class="ptm-match-complete-badge">
                                ✓ Complete
                                <?php if ( $has_players ) : ?>
                                <button type="button" class="ptm-edit-score-btn"
                                        data-match="<?php echo $match->id; ?>"
                                        data-p1="<?php echo (int)$match->player1_score; ?>"
                                        data-p2="<?php echo (int)$match->player2_score; ?>"
                                        data-p1name="<?php echo esc_attr( $match->player1_name ?? 'P1' ); ?>"
                                        data-p2name="<?php echo esc_attr( $match->player2_name ?? 'P2' ); ?>"
                                        title="Correct this score">✏️</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <input type="hidden" id="ptm-tournament-id" value="<?php echo $tournament_id; ?>">

    <!-- Score Correction Modal -->
    <div id="ptm-score-modal" class="ptm-modal-overlay" style="display:none">
        <div class="ptm-modal-box">
            <h3><?php _e( 'Correct Match Score', 'ptm-tournaments' ); ?></h3>
            <p class="ptm-modal-warning">⚠️ <?php _e( 'Changing the score may re-route players in the bracket. Only correct obvious entry errors.', 'ptm-tournaments' ); ?></p>
            <div class="ptm-modal-scores">
                <div class="ptm-modal-player">
                    <label id="ptm-modal-p1-label">Player 1</label>
                    <input type="number" id="ptm-modal-p1-score" min="0" max="99" class="ptm-modal-score-input">
                </div>
                <div class="ptm-modal-vs">vs</div>
                <div class="ptm-modal-player">
                    <label id="ptm-modal-p2-label">Player 2</label>
                    <input type="number" id="ptm-modal-p2-score" min="0" max="99" class="ptm-modal-score-input">
                </div>
            </div>
            <input type="hidden" id="ptm-modal-match-id">
            <div class="ptm-modal-actions">
                <button type="button" id="ptm-modal-save" class="button button-primary"><?php _e( 'Save Correction', 'ptm-tournaments' ); ?></button>
                <button type="button" id="ptm-modal-cancel" class="button"><?php _e( 'Cancel', 'ptm-tournaments' ); ?></button>
            </div>
        </div>
    </div>
</div>
