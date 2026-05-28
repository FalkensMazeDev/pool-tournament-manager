<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="ptm-tournament-list">

    <?php if ( empty( $tournaments ) ) : ?>
        <p class="ptm-no-tournaments"><?php _e( 'No upcoming tournaments at this time.', 'ptm-tournaments' ); ?></p>
    <?php else : ?>

        <?php foreach ( $tournaments as $t ) :
            $player_count = PTM_Tournament::get_player_count( $t->id );
        ?>
        <div class="ptm-tournament-card ptm-tournament-card--<?php echo esc_attr( $t->status ); ?>">
            <div class="ptm-tournament-card-inner">
                <div class="ptm-tournament-card-main">
                    <h3 class="ptm-tournament-card-name"><?php echo esc_html( $t->name ); ?></h3>
                    <div class="ptm-tournament-card-details">
                        <span class="ptm-detail-badge"><?php echo esc_html( strtoupper( $t->game_type ) ); ?></span>
                        <span class="ptm-detail-badge"><?php echo $t->bracket_type === 'double_elim' ? __( 'Double Elim', 'ptm-tournaments' ) : __( 'Single Elim', 'ptm-tournaments' ); ?></span>
                        <?php if ( $player_count ) : ?>
                            <span class="ptm-detail-badge"><?php printf( __( '%d Players', 'ptm-tournaments' ), $player_count ); ?></span>
                        <?php endif; ?>
                        <?php if ( $t->tournament_date ) : ?>
                            <span class="ptm-detail-date"><?php echo esc_html( date( 'F j, Y', strtotime( $t->tournament_date ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ptm-tournament-card-status">
                    <?php if ( $t->status === 'active' ) : ?>
                        <span class="ptm-pub-live-badge">🔴 LIVE</span>
                    <?php elseif ( $t->status === 'draft' ) : ?>
                        <span class="ptm-pub-upcoming-badge"><?php _e( 'Upcoming', 'ptm-tournaments' ); ?></span>
                    <?php else : ?>
                        <span class="ptm-pub-complete-badge"><?php _e( 'Complete', 'ptm-tournaments' ); ?></span>
                    <?php endif; ?>
                    <?php if ( $t->status !== 'draft' ) : ?>
                        <a href="<?php echo add_query_arg( [ 'ptm_tournament' => $t->id ], get_permalink() ); ?>"
                           class="ptm-view-bracket-btn">
                            <?php _e( 'View Bracket →', 'ptm-tournaments' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
