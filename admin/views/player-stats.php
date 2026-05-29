<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ptm-admin">

    <h1>
        <?php printf( __( 'Stats: %s', 'ptm-tournaments' ), esc_html( $player->name ) ); ?>
    </h1>

    <div class="ptm-breadcrumb">
        <a href="<?php echo admin_url( 'admin.php?page=ptm-players' ); ?>"><?php _e( '← Player Registry', 'ptm-tournaments' ); ?></a>
    </div>

    <?php
    // Career totals
    $totals = [
        'matches_played' => 0,
        'matches_won'    => 0,
        'matches_lost'   => 0,
        'games_won'      => 0,
        'games_lost'     => 0,
        'wins_1st'       => 0,
        'money_won'      => 0.0,
    ];
    foreach ( $stats as $s ) {
        $totals['matches_played'] += $s->matches_played;
        $totals['matches_won']    += $s->matches_won;
        $totals['matches_lost']   += $s->matches_lost;
        $totals['games_won']      += $s->games_won;
        $totals['games_lost']     += $s->games_lost;
        if ( $s->finish_position == 1 ) $totals['wins_1st']++;
        $totals['money_won']      += isset( $s->money_won ) ? (float) $s->money_won : 0.0;
    }
    $win_pct = $totals['matches_played'] > 0
        ? round( $totals['matches_won'] / $totals['matches_played'] * 100, 1 )
        : 0;
    ?>

    <!-- Career summary cards -->
    <div class="ptm-stats-cards">
        <div class="ptm-stat-card">
            <div class="ptm-stat-value"><?php echo $totals['matches_played']; ?></div>
            <div class="ptm-stat-label"><?php _e( 'Matches Played', 'ptm-tournaments' ); ?></div>
        </div>
        <div class="ptm-stat-card">
            <div class="ptm-stat-value"><?php echo $win_pct; ?>%</div>
            <div class="ptm-stat-label"><?php _e( 'Win Rate', 'ptm-tournaments' ); ?></div>
        </div>
        <div class="ptm-stat-card">
            <div class="ptm-stat-value"><?php echo $totals['matches_won']; ?> – <?php echo $totals['matches_lost']; ?></div>
            <div class="ptm-stat-label"><?php _e( 'Match W–L', 'ptm-tournaments' ); ?></div>
        </div>
        <div class="ptm-stat-card">
            <div class="ptm-stat-value"><?php echo $totals['games_won']; ?> – <?php echo $totals['games_lost']; ?></div>
            <div class="ptm-stat-label"><?php _e( 'Game W–L', 'ptm-tournaments' ); ?></div>
        </div>
        <div class="ptm-stat-card">
            <div class="ptm-stat-value"><?php echo $totals['wins_1st']; ?></div>
            <div class="ptm-stat-label"><?php _e( '1st Place Finishes', 'ptm-tournaments' ); ?></div>
        </div>
        <div class="ptm-stat-card">
            <div class="ptm-stat-value">$<?php echo number_format( $totals['money_won'], 2 ); ?></div>
            <div class="ptm-stat-label"><?php _e( 'Money Won', 'ptm-tournaments' ); ?></div>
        </div>
    </div>

    <!-- Per-tournament breakdown -->
    <div class="postbox" style="margin-top:20px">
        <div class="postbox-header"><h2><?php _e( 'Tournament History', 'ptm-tournaments' ); ?></h2></div>
        <div class="inside">
            <?php if ( empty( $stats ) ) : ?>
                <p><?php _e( 'No tournament history yet.', 'ptm-tournaments' ); ?></p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped ptm-table">
                <thead>
                    <tr>
                        <th><?php _e( 'Tournament', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Game', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Date', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Finish', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Matches W–L', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Games W–L', 'ptm-tournaments' ); ?></th>
                        <th><?php _e( 'Money Won', 'ptm-tournaments' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats as $s ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=bracket&tournament_id=' . $s->tournament_id ); ?>">
                                <?php echo esc_html( $s->tournament_name ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( strtoupper( $s->game_type ) ); ?></td>
                        <td><?php echo $s->tournament_date ? esc_html( date( 'M j, Y', strtotime( $s->tournament_date ) ) ) : '—'; ?></td>
                        <td>
                            <?php if ( $s->finish_position == 1 ) : ?>
                                🏆 1st
                            <?php elseif ( $s->finish_position == 2 ) : ?>
                                🥈 2nd
                            <?php elseif ( $s->finish_position == 3 ) : ?>
                                🥉 3rd
                            <?php elseif ( $s->finish_position ) : ?>
                                <?php echo $s->finish_position . __( 'th', 'ptm-tournaments' ); ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo $s->matches_won; ?> – <?php echo $s->matches_lost; ?></td>
                        <td><?php echo $s->games_won;   ?> – <?php echo $s->games_lost;   ?></td>
                        <td><?php echo isset( $s->money_won ) && $s->money_won > 0 ? '$' . number_format( (float) $s->money_won, 2 ) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.ptm-stats-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 20px;
}
.ptm-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px 24px;
    min-width: 140px;
    text-align: center;
}
.ptm-stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #1d2327;
    line-height: 1;
    margin-bottom: 6px;
}
.ptm-stat-label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
