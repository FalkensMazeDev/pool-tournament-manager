<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ptm-admin">

    <h1 class="wp-heading-inline"><?php _e( 'Player Registry', 'ptm-tournaments' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'Player saved.', 'ptm-tournaments' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'Player deleted.', 'ptm-tournaments' ); ?></p></div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <div class="ptm-players-layout">

        <!-- Player list -->
        <div class="ptm-players-main">
            <?php if ( empty( $players ) ) : ?>
                <div class="ptm-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <p><?php _e( 'No players in the registry yet.', 'ptm-tournaments' ); ?></p>
                </div>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped ptm-table">
                <thead>
                    <tr>
                        <th><?php _e( 'Name', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Email', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Phone', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Actions', 'ptm-tournaments' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $players as $p ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $p->name ); ?></strong></td>
                        <td><?php echo $p->email ? esc_html( $p->email ) : '—'; ?></td>
                        <td><?php echo $p->phone ? esc_html( $p->phone ) : '—'; ?></td>
                        <td class="ptm-actions">
                            <button type="button" class="button button-small ptm-edit-player"
                                    data-id="<?php echo $p->id; ?>"
                                    data-name="<?php echo esc_attr( $p->name ); ?>"
                                    data-email="<?php echo esc_attr( $p->email ); ?>"
                                    data-phone="<?php echo esc_attr( $p->phone ); ?>">
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
            <?php endif; ?>
        </div>

        <!-- Add / Edit Player Sidebar -->
        <div class="ptm-players-sidebar">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 id="ptm-player-form-title"><?php _e( 'Add Player', 'ptm-tournaments' ); ?></h2>
                </div>
                <div class="inside">
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="ptm-player-form">
                        <?php wp_nonce_field( 'ptm_save_player' ); ?>
                        <input type="hidden" name="action"    value="ptm_save_player">
                        <input type="hidden" name="player_id" id="edit-player-id" value="">

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

                        <button type="submit" class="button button-primary" style="width:100%;" id="ptm-player-submit">
                            <?php _e( 'Add Player', 'ptm-tournaments' ); ?>
                        </button>
                        <button type="button" class="button" style="width:100%;margin-top:5px;display:none" id="ptm-player-cancel">
                            <?php _e( 'Cancel Edit', 'ptm-tournaments' ); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- .ptm-players-layout -->
</div>
