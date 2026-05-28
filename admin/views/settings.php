<?php if ( ! defined( 'ABSPATH' ) ) exit;
$s = PTM_Settings::get_all();
$base_url = home_url( '/' );
?>
<div class="wrap ptm-admin">

    <h1><?php _e( 'Pool Tournament Manager Settings', 'ptm-tournaments' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Settings saved. Permalink rules have been flushed automatically.', 'ptm-tournaments' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
        <?php wp_nonce_field( 'ptm_save_settings' ); ?>
        <input type="hidden" name="action" value="ptm_save_settings">

        <!-- ── URL / Permalink Settings ──────────────────────────────── -->
        <div class="postbox" style="max-width:800px; margin-top:20px;">
            <div class="postbox-header">
                <h2><?php _e( 'URLs &amp; Permalinks', 'ptm-tournaments' ); ?></h2>
            </div>
            <div class="inside">

                <p class="description" style="margin-bottom:16px;">
                    <?php _e( 'Changing these slugs will update the URL structure for all tournament pages. Permalink rules are flushed automatically on save — you do not need to visit Settings → Permalinks manually.', 'ptm-tournaments' ); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th><label for="tournament_base_slug"><?php _e( 'Tournament Bracket URL', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <code><?php echo esc_html( $base_url ); ?></code>
                            <input type="text" id="tournament_base_slug" name="tournament_base_slug"
                                   value="<?php echo esc_attr( $s['tournament_base_slug'] ); ?>"
                                   class="regular-text" placeholder="tournament">
                            <code>/{tournament-slug}/</code>
                            <p class="description">
                                <?php _e( 'Example:', 'ptm-tournaments' ); ?>
                                <code><?php echo esc_html( home_url( '/' . $s['tournament_base_slug'] . '/spring-8-ball-2026/' ) ); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="results_sub_slug"><?php _e( 'Results Sub-page', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <code><?php echo esc_html( $base_url . $s['tournament_base_slug'] ); ?>/{tournament-slug}/</code>
                            <input type="text" id="results_sub_slug" name="results_sub_slug"
                                   value="<?php echo esc_attr( $s['results_sub_slug'] ); ?>"
                                   class="regular-text" placeholder="results">
                            <code>/</code>
                            <p class="description">
                                <?php _e( 'Example:', 'ptm-tournaments' ); ?>
                                <code><?php echo esc_html( home_url( '/' . $s['tournament_base_slug'] . '/spring-8-ball-2026/' . $s['results_sub_slug'] . '/' ) ); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="scorer_base_slug"><?php _e( 'Scorer / Score-entry URL', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <code><?php echo esc_html( $base_url ); ?></code>
                            <input type="text" id="scorer_base_slug" name="scorer_base_slug"
                                   value="<?php echo esc_attr( $s['scorer_base_slug'] ); ?>"
                                   class="regular-text" placeholder="ptm-score">
                            <code>/{match-token}/</code>
                            <p class="description">
                                <?php _e( 'This is the private URL given to table scorers. Example:', 'ptm-tournaments' ); ?>
                                <code><?php echo esc_html( home_url( '/' . $s['scorer_base_slug'] . '/abc123.../' ) ); ?></code>
                            </p>
                            <div class="notice notice-warning inline" style="margin-top:8px;">
                                <p><?php _e( '⚠ If you change the scorer URL slug, any scorer links already shared (e.g. via QR code) will break. Only change this before a tournament starts.', 'ptm-tournaments' ); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>

            </div>
        </div>

        <!-- ── Tournament Defaults ────────────────────────────────────── -->
        <div class="postbox" style="max-width:800px; margin-top:20px;">
            <div class="postbox-header">
                <h2><?php _e( 'Tournament Defaults', 'ptm-tournaments' ); ?></h2>
            </div>
            <div class="inside">
                <p class="description" style="margin-bottom:16px;">
                    <?php _e( 'These values are pre-filled when creating a new tournament. They can be overridden per-tournament.', 'ptm-tournaments' ); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="default_num_tables"><?php _e( 'Default Number of Tables', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <input type="number" id="default_num_tables" name="default_num_tables"
                                   min="1" max="20" class="small-text"
                                   value="<?php echo absint( $s['default_num_tables'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="default_race_to"><?php _e( 'Default Race To (Winners)', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <input type="number" id="default_race_to" name="default_race_to"
                                   min="1" max="20" class="small-text"
                                   value="<?php echo absint( $s['default_race_to'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="default_race_to_losers"><?php _e( 'Default Race To (Losers)', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <input type="number" id="default_race_to_losers" name="default_race_to_losers"
                                   min="1" max="20" class="small-text"
                                   value="<?php echo absint( $s['default_race_to_losers'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="default_entrance_fee"><?php _e( 'Default Entrance Fee ($)', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <input type="number" id="default_entrance_fee" name="default_entrance_fee"
                                   min="0" step="0.01" class="small-text"
                                   value="<?php echo number_format( (float) $s['default_entrance_fee'], 2, '.', '' ); ?>">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── Display Settings ───────────────────────────────────────── -->
        <div class="postbox" style="max-width:800px; margin-top:20px;">
            <div class="postbox-header">
                <h2><?php _e( 'Display Settings', 'ptm-tournaments' ); ?></h2>
            </div>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th><label for="club_name"><?php _e( 'Club / Organization Name', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <input type="text" id="club_name" name="club_name"
                                   class="regular-text"
                                   value="<?php echo esc_attr( $s['club_name'] ); ?>"
                                   placeholder="<?php _e( 'e.g. Gardner Deer Club', 'ptm-tournaments' ); ?>">
                            <p class="description"><?php _e( 'Shown in the scorer page header and public bracket titles.', 'ptm-tournaments' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="poll_interval_ms"><?php _e( 'Live Update Interval', 'ptm-tournaments' ); ?></label></th>
                        <td>
                            <select id="poll_interval_ms" name="poll_interval_ms">
                                <?php foreach ( [ 2000 => '2 seconds', 5000 => '5 seconds', 10000 => '10 seconds', 30000 => '30 seconds' ] as $ms => $label ) : ?>
                                    <option value="<?php echo $ms; ?>" <?php selected( (int) $s['poll_interval_ms'], $ms ); ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'How often the public bracket page checks for score updates.', 'ptm-tournaments' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Prize Pot Display', 'ptm-tournaments' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_prize_pot_public" value="1"
                                       <?php checked( $s['show_prize_pot_public'], 1 ); ?>>
                                <?php _e( 'Show prize pot and payout amounts on public results pages', 'ptm-tournaments' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── Shortcode Reference ────────────────────────────────────── -->
        <div class="postbox" style="max-width:800px; margin-top:20px;">
            <div class="postbox-header">
                <h2><?php _e( 'Shortcode Reference', 'ptm-tournaments' ); ?></h2>
            </div>
            <div class="inside">
                <table class="wp-list-table widefat fixed" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th style="width:280px"><?php _e( 'Shortcode', 'ptm-tournaments' ); ?></th>
                            <th><?php _e( 'Description', 'ptm-tournaments' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[ptm_bracket id="5"]</code></td>
                            <td><?php _e( 'Shows the live bracket for tournament ID 5. Auto-updates every few seconds when active.', 'ptm-tournaments' ); ?></td>
                        </tr>
                        <tr>
                            <td><code>[ptm_tournaments]</code></td>
                            <td><?php _e( 'Shows a listing of public tournaments. Optional: status="active,draft,complete", limit="10"', 'ptm-tournaments' ); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3 style="margin-top:20px;"><?php _e( 'Pretty URLs', 'ptm-tournaments' ); ?></h3>
                <table class="wp-list-table widefat fixed" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th style="width:380px"><?php _e( 'URL', 'ptm-tournaments' ); ?></th>
                            <th><?php _e( 'Shows', 'ptm-tournaments' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code><?php echo esc_html( home_url( '/' . $s['tournament_base_slug'] . '/{slug}/' ) ); ?></code></td>
                            <td><?php _e( 'Live bracket for that tournament', 'ptm-tournaments' ); ?></td>
                        </tr>
                        <tr>
                            <td><code><?php echo esc_html( home_url( '/' . $s['tournament_base_slug'] . '/{slug}/' . $s['results_sub_slug'] . '/' ) ); ?></code></td>
                            <td><?php _e( 'Final results and payout breakdown', 'ptm-tournaments' ); ?></td>
                        </tr>
                        <tr>
                            <td><code><?php echo esc_html( home_url( '/' . $s['scorer_base_slug'] . '/{token}/' ) ); ?></code></td>
                            <td><?php _e( 'Scorer entry page (private, no login required)', 'ptm-tournaments' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="max-width:800px; margin-top:16px;">
            <?php submit_button( __( 'Save Settings', 'ptm-tournaments' ), 'primary large', 'submit', false ); ?>
        </div>

    </form>
</div>
