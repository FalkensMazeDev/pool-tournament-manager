<?php if ( ! defined( 'ABSPATH' ) ) exit;
// $tournament (object) and $roster (array of objects) are passed by the controller
$is_active = $tournament->status !== 'draft';
?>
<div class="wrap ptm-admin">

    <h1>
        <?php printf( __( 'Roster: %s', 'ptm-tournaments' ), esc_html( $tournament->name ) ); ?>
    </h1>

    <div class="ptm-breadcrumb">
        <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments' ); ?>"><?php _e( '← All Tournaments', 'ptm-tournaments' ); ?></a>
        &nbsp;·&nbsp;
        <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=edit&tournament_id=' . $tournament_id ); ?>"><?php _e( 'Edit Tournament', 'ptm-tournaments' ); ?></a>
    </div>

    <div class="ptm-roster-layout">

        <!-- Add player sidebar -->
        <?php if ( ! $is_active ) : ?>
        <div class="ptm-roster-sidebar">
            <div class="postbox">
                <div class="postbox-header"><h2><?php _e( 'Add Player', 'ptm-tournaments' ); ?></h2></div>
                <div class="inside">
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                        <?php wp_nonce_field( 'ptm_roster' ); ?>
                        <input type="hidden" name="action"        value="ptm_add_tournament_player">
                        <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                        <input type="hidden" name="player_id"     id="selected-player-id" value="">

                        <p>
                            <label><?php _e( 'Search Existing Player', 'ptm-tournaments' ); ?></label>
                            <input type="text" id="ptm-player-search" class="regular-text"
                                   placeholder="<?php _e( 'Type a name...', 'ptm-tournaments' ); ?>" autocomplete="off">
                            <div id="ptm-player-suggestions" class="ptm-autocomplete"></div>
                        </p>

                        <p class="description" style="text-align:center; margin: 5px 0">— or —</p>

                        <p>
                            <label><?php _e( 'Add New Player', 'ptm-tournaments' ); ?></label>
                            <input type="text" name="new_player_name" id="new-player-name" class="regular-text"
                                   placeholder="<?php _e( 'Full name', 'ptm-tournaments' ); ?>">
                        </p>

                        <?php if ( $tournament->handicap_enabled ) : ?>
                        <p>
                            <label><?php _e( 'Skill Level', 'ptm-tournaments' ); ?></label>
                            <input type="number" name="skill_level" min="1" max="9" class="small-text"
                                   placeholder="1–9">
                            <span class="description"><?php _e( '1 = lowest, 9 = highest', 'ptm-tournaments' ); ?></span>
                        </p>
                        <?php endif; ?>

                        <button type="submit" class="button button-primary" style="width:100%;">
                            <?php _e( 'Add to Tournament', 'ptm-tournaments' ); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Player list -->
        <div class="ptm-roster-main">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php printf( __( 'Players (%d)', 'ptm-tournaments' ), count( $roster ) ); ?></h2>
                    <?php if ( ! $is_active && count( $roster ) >= 2 ) : ?>
                        <div class="postbox-header-actions">
                            <button type="button" id="ptm-randomize" class="button">
                                🔀 <?php _e( 'Randomize Seeds', 'ptm-tournaments' ); ?>
                            </button>
                            <button type="button" id="ptm-save-seeds" class="button button-secondary" style="display:none">
                                <?php _e( 'Save Order', 'ptm-tournaments' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="inside">

                    <?php if ( empty( $roster ) ) : ?>
                        <p><?php _e( 'No players added yet.', 'ptm-tournaments' ); ?></p>
                    <?php else : ?>

                        <?php if ( ! $is_active ) : ?>
                            <p class="description">
                                <?php _e( 'Drag rows to manually adjust seeding order. Seed 1 is at the top.', 'ptm-tournaments' ); ?>
                            </p>
                        <?php endif; ?>

                        <table class="wp-list-table widefat fixed ptm-table" id="ptm-roster-table">
                            <thead>
                                <tr>
                                    <?php if ( ! $is_active ) : ?><th style="width:30px"></th><?php endif; ?>
                                    <th style="width:50px"><?php _e( 'Seed', 'ptm-tournaments' ); ?></th>
                                    <th><?php _e( 'Player', 'ptm-tournaments' ); ?></th>
                                    <?php if ( $tournament->handicap_enabled ) : ?>
                                        <th style="width:100px"><?php _e( 'Skill Level', 'ptm-tournaments' ); ?></th>
                                    <?php endif; ?>
                                    <?php if ( ! $is_active ) : ?>
                                        <th style="width:80px"><?php _e( 'Remove', 'ptm-tournaments' ); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="ptm-sortable-roster">
                                <?php foreach ( $roster as $i => $rp ) : ?>
                                <tr data-player-id="<?php echo $rp->player_id; ?>">
                                    <?php if ( ! $is_active ) : ?>
                                        <td class="ptm-drag-handle">☰</td>
                                    <?php endif; ?>
                                    <td class="ptm-seed-num"><?php echo $rp->seed ?: ( $i + 1 ); ?></td>
                                    <td><?php echo esc_html( $rp->name ); ?></td>
                                    <?php if ( $tournament->handicap_enabled ) : ?>
                                        <td><?php echo $rp->skill_level ?: '—'; ?></td>
                                    <?php endif; ?>
                                    <?php if ( ! $is_active ) : ?>
                                        <td>
                                            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                                                <?php wp_nonce_field( 'ptm_roster' ); ?>
                                                <input type="hidden" name="action"        value="ptm_remove_tournament_player">
                                                <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                                                <input type="hidden" name="player_id"     value="<?php echo $rp->player_id; ?>">
                                                <button type="submit" class="button button-small button-link-delete"
                                                        onclick="return confirm('<?php _e( 'Remove this player?', 'ptm-tournaments' ); ?>')">
                                                    &times;
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php endif; ?>

                    <?php if ( ! $is_active && count( $roster ) >= 2 ) : ?>
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <form method="post" action="<?php echo rest_url( 'gdc/v1/tournament/' . $tournament_id . '/generate' ); ?>"
                                  id="ptm-generate-form">
                                <button type="button" id="ptm-generate-bracket" class="button button-primary button-large">
                                    🏆 <?php _e( 'Generate Bracket', 'ptm-tournaments' ); ?>
                                </button>
                                <p class="description" style="margin-top: 8px;">
                                    <?php printf(
                                        __( 'This will create the bracket for %d players and activate the tournament. This cannot be undone.', 'ptm-tournaments' ),
                                        count( $roster )
                                    ); ?>
                                </p>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div><!-- .ptm-roster-layout -->

    <?php
    // Hidden data for JS
    $hidden_input = '<input type="hidden" id="ptm-tournament-id" value="' . $tournament_id . '">';
    echo $hidden_input;
    ?>

</div>
