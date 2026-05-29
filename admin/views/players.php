<?php if ( ! defined( 'ABSPATH' ) ) exit;
$all_meta = PTM_Player::get_all_meta();
?>
<div class="wrap ptm-admin">

    <h1 class="wp-heading-inline"><?php _e( 'Player Registry', 'ptm-tournaments' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'Player saved.', 'ptm-tournaments' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'Player deleted.', 'ptm-tournaments' ); ?></p></div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Add Player button row -->
    <div class="ptm-players-add-btn-row">
        <button type="button" class="button button-primary" id="ptm-toggle-player-form">
            + <?php _e( 'Add Player', 'ptm-tournaments' ); ?>
        </button>
    </div>

    <!-- Add / Edit Player Form (hidden by default) -->
    <div class="ptm-player-form-box-wrap" id="ptm-player-form-wrap">
        <div class="ptm-players-form-box postbox">
            <div class="postbox-header">
                <h2 id="ptm-player-form-title"><?php _e( 'Add Player', 'ptm-tournaments' ); ?></h2>
            </div>
            <div class="inside">
                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="ptm-player-form">
                    <?php wp_nonce_field( 'ptm_save_player' ); ?>
                    <input type="hidden" name="action"    value="ptm_save_player">
                    <input type="hidden" name="player_id" id="edit-player-id" value="">

                    <div class="ptm-player-form-grid">
                        <div class="ptm-player-form-col">
                            <p>
                                <label for="player-name"><?php _e( 'Name', 'ptm-tournaments' ); ?> <span class="required">*</span></label>
                                <input type="text" id="player-name" name="name" class="regular-text" required>
                            </p>
                            <p>
                                <label for="player-email"><?php _e( 'Email', 'ptm-tournaments' ); ?></label>
                                <input type="email" id="player-email" name="email" class="regular-text">
                            </p>
                            <p>
                                <label for="player-phone"><?php _e( 'Phone', 'ptm-tournaments' ); ?></label>
                                <input type="tel" id="player-phone" name="phone" class="regular-text">
                            </p>
                        </div>
                        <div class="ptm-player-form-col">
                            <p style="font-weight:600;margin-bottom:4px"><?php _e( 'League / Rating Info', 'ptm-tournaments' ); ?></p>
                            <p>
                                <label for="player-apa-number"><?php _e( 'APA Number', 'ptm-tournaments' ); ?></label>
                                <input type="text" id="player-apa-number" name="apa_number" class="regular-text">
                            </p>
                            <p>
                                <label for="player-apa-sl"><?php _e( 'APA Skill Level', 'ptm-tournaments' ); ?></label>
                                <input type="number" id="player-apa-sl" name="apa_skill_level" class="small-text" min="1" max="9">
                            </p>
                            <p>
                                <label for="player-fargo-id"><?php _e( 'Fargo ID', 'ptm-tournaments' ); ?></label>
                                <input type="text" id="player-fargo-id" name="fargo_id" class="regular-text">
                            </p>
                            <p>
                                <label for="player-fargo-rating"><?php _e( 'Fargo Rating', 'ptm-tournaments' ); ?></label>
                                <input type="number" id="player-fargo-rating" name="fargo_rating" class="small-text" min="0" max="1000">
                            </p>
                        </div>
                        <div class="ptm-player-form-col">
                            <p style="font-weight:600;margin-bottom:4px"><?php _e( 'Custom Fields', 'ptm-tournaments' ); ?></p>
                            <div id="ptm-meta-fields"></div>
                            <button type="button" class="button" id="ptm-add-meta-field" style="margin-bottom:10px">
                                <?php _e( '+ Add Field', 'ptm-tournaments' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="ptm-player-form-row">
                        <label>
                            <input type="checkbox" name="do_not_notify" id="player-do-not-notify" value="1">
                            <?php _e( 'Do not notify (opt out of match email notifications)', 'ptm-tournaments' ); ?>
                        </label>
                    </div>

                    <div class="ptm-player-form-actions">
                        <button type="submit" class="button button-primary" id="ptm-player-submit">
                            <?php _e( 'Add Player', 'ptm-tournaments' ); ?>
                        </button>
                        <button type="button" class="button" id="ptm-player-cancel">
                            <?php _e( 'Cancel', 'ptm-tournaments' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Player list -->
    <div class="ptm-players-main">
        <?php if ( empty( $players ) ) : ?>
            <div class="ptm-empty-state">
                <span class="dashicons dashicons-groups"></span>
                <p><?php _e( 'No players in the registry yet.', 'ptm-tournaments' ); ?></p>
            </div>
        <?php else : ?>

        <!-- Toolbar: search + count -->
        <div class="ptm-players-toolbar">
            <input type="search" id="ptm-player-filter" placeholder="<?php esc_attr_e( 'Search players…', 'ptm-tournaments' ); ?>" class="regular-text">
            <span class="ptm-players-count" id="ptm-players-count"></span>
        </div>

        <table class="wp-list-table widefat fixed striped ptm-table" id="ptm-players-table">
            <thead>
                <tr>
                    <th><?php _e( 'Name', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Email', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Phone', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'APA SL', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Fargo', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Actions', 'ptm-tournaments' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $players as $p ) :
                    $p_meta = $all_meta[ $p->id ] ?? [];
                ?>
                <tr data-name="<?php echo esc_attr( strtolower( $p->name ) ); ?>">
                    <td><strong><?php echo esc_html( $p->name ); ?></strong></td>
                    <td><?php echo $p->email ? esc_html( $p->email ) : '—'; ?></td>
                    <td><?php echo $p->phone ? esc_html( $p->phone ) : '—'; ?></td>
                    <td><?php echo isset( $p->apa_skill_level ) && $p->apa_skill_level !== null ? esc_html( $p->apa_skill_level ) : '—'; ?></td>
                    <td><?php echo isset( $p->fargo_rating ) && $p->fargo_rating !== null ? esc_html( $p->fargo_rating ) : '—'; ?></td>
                    <td class="ptm-actions">
                        <button type="button" class="button button-small ptm-edit-player"
                                data-id="<?php echo $p->id; ?>"
                                data-name="<?php echo esc_attr( $p->name ); ?>"
                                data-email="<?php echo esc_attr( $p->email ); ?>"
                                data-phone="<?php echo esc_attr( $p->phone ); ?>"
                                data-apa-number="<?php echo esc_attr( $p->apa_number ?? '' ); ?>"
                                data-apa-sl="<?php echo esc_attr( $p->apa_skill_level ?? '' ); ?>"
                                data-fargo-id="<?php echo esc_attr( $p->fargo_id ?? '' ); ?>"
                                data-fargo-rating="<?php echo esc_attr( $p->fargo_rating ?? '' ); ?>"
                                data-do-not-notify="<?php echo ! empty( $p_meta['do_not_notify'] ) ? '1' : '0'; ?>"
                                data-meta="<?php echo esc_attr( wp_json_encode( $p_meta ) ); ?>">
                            <?php _e( 'Edit', 'ptm-tournaments' ); ?>
                        </button>
                        <a href="<?php echo admin_url( 'admin.php?page=ptm-players&action=stats&player_id=' . $p->id ); ?>"
                           class="button button-small">
                            <?php _e( 'Stats', 'ptm-tournaments' ); ?>
                        </a>
                        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display:inline">
                            <?php wp_nonce_field( 'ptm_delete_player' ); ?>
                            <input type="hidden" name="action"    value="ptm_delete_player">
                            <input type="hidden" name="player_id" value="<?php echo $p->id; ?>">
                            <button type="submit" class="button button-small button-link-delete"
                                    onclick="return confirm('<?php _e( 'Delete this player?', 'ptm-tournaments' ); ?>')">
                                <?php _e( 'Delete', 'ptm-tournaments' ); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="ptm-pagination" id="ptm-players-pagination"></div>

        <?php endif; ?>
    </div>

</div><!-- .wrap -->
