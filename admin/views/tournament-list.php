<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ptm-admin">

    <h1 class="wp-heading-inline"><?php _e( 'Tournaments', 'ptm-tournaments' ); ?></h1>
    <a href="<?php echo admin_url( 'admin.php?page=ptm-tournament-new' ); ?>" class="page-title-action">
        <?php _e( 'Add New', 'ptm-tournaments' ); ?>
    </a>

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'Tournament deleted.', 'ptm-tournaments' ); ?></p></div>
    <?php elseif ( isset( $_GET['delete_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php _e( 'Could not delete tournament. Please try again.', 'ptm-tournaments' ); ?></p></div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php if ( empty( $tournaments ) ) : ?>
        <div class="ptm-empty-state">
            <span class="dashicons dashicons-awards"></span>
            <p><?php _e( 'No tournaments yet. Add your first one!', 'ptm-tournaments' ); ?></p>
            <a href="<?php echo admin_url( 'admin.php?page=ptm-tournament-new' ); ?>" class="button button-primary">
                <?php _e( 'Add Tournament', 'ptm-tournaments' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped ptm-table">
            <thead>
                <tr>
                    <th><?php _e( 'Tournament', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Game', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Format', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Date', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Players', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Status', 'ptm-tournaments' ); ?></th>
                    <th><?php _e( 'Actions', 'ptm-tournaments' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $tournaments as $t ) :
                    $player_count = PTM_Tournament::get_player_count( $t->id );
                    $match_counts = PTM_Tournament::get_match_counts( $t->id );
                ?>
                <tr>
                    <td>
                        <strong>
                            <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=edit&tournament_id=' . $t->id ); ?>">
                                <?php echo esc_html( $t->name ); ?>
                            </a>
                        </strong>
                        <?php if ( ! $t->is_public ) : ?>
                            <span class="ptm-badge ptm-badge--private"><?php _e( 'Private', 'ptm-tournaments' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( strtoupper( $t->game_type ) ); ?></td>
                    <td><?php echo $t->bracket_type === 'double_elim' ? __( 'Double Elim', 'ptm-tournaments' ) : __( 'Single Elim', 'ptm-tournaments' ); ?></td>
                    <td><?php echo $t->tournament_date ? esc_html( date( 'M j, Y', strtotime( $t->tournament_date ) ) ) : '—'; ?></td>
                    <td><?php echo $player_count; ?></td>
                    <td>
                        <span class="ptm-status ptm-status--<?php echo esc_attr( $t->status ); ?>">
                            <?php echo esc_html( ucfirst( str_replace( '_', ' ', $t->status ) ) ); ?>
                        </span>
                    </td>
                    <td class="ptm-actions">
                        <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=edit&tournament_id=' . $t->id ); ?>" class="button button-small">
                            <?php _e( 'Edit', 'ptm-tournaments' ); ?>
                        </a>
                        <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=roster&tournament_id=' . $t->id ); ?>" class="button button-small">
                            <?php _e( 'Roster', 'ptm-tournaments' ); ?>
                        </a>
                        <?php if ( $t->status !== 'draft' ) : ?>
                        <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=bracket&tournament_id=' . $t->id ); ?>" class="button button-small button-primary">
                            <?php _e( 'Bracket', 'ptm-tournaments' ); ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
