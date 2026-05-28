<?php if ( ! defined( 'ABSPATH' ) ) exit;
$is_new = ! $tournament;
$tid    = $tournament ? $tournament->id : 0;
?>
<div class="wrap ptm-admin">

    <h1><?php echo $is_new ? __( 'Add Tournament', 'ptm-tournaments' ) : __( 'Edit Tournament', 'ptm-tournaments' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'Tournament saved.', 'ptm-tournaments' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="ptm-tournament-form">
        <?php wp_nonce_field( 'ptm_save_tournament' ); ?>
        <input type="hidden" name="action"        value="ptm_save_tournament">
        <input type="hidden" name="tournament_id" value="<?php echo $tid; ?>">

        <div class="ptm-form-grid">

            <!-- Left column: core settings -->
            <div class="ptm-form-col">
                <div class="postbox">
                    <div class="postbox-header"><h2><?php _e( 'Tournament Details', 'ptm-tournaments' ); ?></h2></div>
                    <div class="inside">

                        <table class="form-table">
                            <tr>
                                <th><label for="name"><?php _e( 'Tournament Name', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <input type="text" id="name" name="name" class="regular-text"
                                           value="<?php echo $tournament ? esc_attr( $tournament->name ) : ''; ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="tournament_date"><?php _e( 'Date', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <input type="date" id="tournament_date" name="tournament_date"
                                           value="<?php echo $tournament ? esc_attr( $tournament->tournament_date ) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="game_type"><?php _e( 'Game Type', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <select id="game_type" name="game_type">
                                        <?php
                                        $game_types = [ '8ball' => '8 Ball', '9ball' => '9 Ball', '10ball' => '10 Ball' ];
                                        foreach ( $game_types as $val => $label ) :
                                            $selected = ( $tournament && $tournament->game_type === $val ) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $val; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="bracket_type"><?php _e( 'Bracket Format', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <select id="bracket_type" name="bracket_type">
                                        <option value="single_elim" <?php selected( $tournament ? $tournament->bracket_type : '', 'single_elim' ); ?>><?php _e( 'Single Elimination', 'ptm-tournaments' ); ?></option>
                                        <option value="double_elim" <?php selected( $tournament ? $tournament->bracket_type : '', 'double_elim' ); ?>><?php _e( 'Double Elimination', 'ptm-tournaments' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="race_to_winners"><?php _e( 'Race To (Winners)', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <input type="number" id="race_to_winners" name="race_to_winners" min="1" max="20"
                                           value="<?php echo $tournament ? esc_attr( $tournament->race_to_winners ) : (int) PTM_Settings::get( 'default_race_to' ); ?>" class="small-text">
                                    <p class="description"><?php _e( 'Number of games a player must win on the winners side.', 'ptm-tournaments' ); ?></p>
                                </td>
                            </tr>
                            <tr class="ptm-losers-row" <?php echo ( ! $tournament || $tournament->bracket_type === 'single_elim' ) ? 'style="display:none"' : ''; ?>>
                                <th><label for="race_to_losers"><?php _e( 'Race To (Losers)', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <input type="number" id="race_to_losers" name="race_to_losers" min="1" max="20"
                                           value="<?php echo $tournament ? esc_attr( $tournament->race_to_losers ) : (int) PTM_Settings::get( 'default_race_to_losers' ); ?>" class="small-text">
                                    <p class="description"><?php _e( 'Number of games a player must win on the losers side.', 'ptm-tournaments' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="num_tables"><?php _e( 'Number of Tables', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <input type="number" id="num_tables" name="num_tables" min="1" max="20"
                                           value="<?php echo $tournament ? esc_attr( $tournament->num_tables ) : (int) PTM_Settings::get( 'default_num_tables' ); ?>" class="small-text">
                                    <p class="description"><?php _e( 'How many physical pool tables are available. Matches are automatically assigned to free tables.', 'ptm-tournaments' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="entrance_fee"><?php _e( 'Entrance Fee ($)', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <input type="number" id="entrance_fee" name="entrance_fee" min="0" step="0.01"
                                           value="<?php echo $tournament ? esc_attr( number_format( (float)$tournament->entrance_fee, 2, '.', '' ) ) : number_format( (float) PTM_Settings::get( 'default_entrance_fee' ), 2, '.', '' ); ?>" class="small-text">
                                    <p class="description"><?php _e( 'Per-player entry fee. Used to calculate the prize pot.', 'ptm-tournaments' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="slug"><?php _e( 'URL Slug', 'ptm-tournaments' ); ?></label></th>
                                <td>
                                    <code><?php echo esc_html( home_url( '/tournament/' ) ); ?></code>
                                    <input type="text" id="slug" name="slug" class="regular-text"
                                           value="<?php echo $tournament ? esc_attr( $tournament->slug ) : ''; ?>"
                                           placeholder="<?php _e( 'auto-generated from name', 'ptm-tournaments' ); ?>">
                                    <p class="description"><?php _e( 'Leave blank to auto-generate. Only lowercase letters, numbers, hyphens.', 'ptm-tournaments' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e( 'Visibility', 'ptm-tournaments' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="is_public" value="1"
                                               <?php checked( $tournament ? $tournament->is_public : 1, 1 ); ?>>
                                        <?php _e( 'Show on public site', 'ptm-tournaments' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e( 'Handicap', 'ptm-tournaments' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="handicap_enabled" id="handicap_enabled" value="1"
                                               <?php checked( $tournament ? $tournament->handicap_enabled : 0, 1 ); ?>>
                                        <?php _e( 'Enable skill-based handicapping', 'ptm-tournaments' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                    </div>
                </div>
            </div>

            <!-- Right column: handicap rules -->
            <div class="ptm-form-col ptm-handicap-section" <?php echo ( ! $tournament || ! $tournament->handicap_enabled ) ? 'style="display:none"' : ''; ?>>
                <div class="postbox">
                    <div class="postbox-header"><h2><?php _e( 'Handicap Rules', 'ptm-tournaments' ); ?></h2></div>
                    <div class="inside">
                        <p class="description">
                            <?php _e( 'Define race-to values for skill matchups. Higher skill player faces a longer race.', 'ptm-tournaments' ); ?>
                        </p>

                        <table class="widefat ptm-handicap-table" id="handicap-rules-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Higher Skill', 'ptm-tournaments' ); ?></th>
                                    <th><?php _e( 'Lower Skill', 'ptm-tournaments' ); ?></th>
                                    <th><?php _e( 'Higher Race To', 'ptm-tournaments' ); ?></th>
                                    <th><?php _e( 'Lower Race To', 'ptm-tournaments' ); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="handicap-rules-body">
                                <?php foreach ( $handicap_rules as $i => $rule ) : ?>
                                <tr class="ptm-rule-row">
                                    <td><input type="number" name="handicap_rules[<?php echo $i; ?>][skill_level_higher]" value="<?php echo esc_attr( $rule->skill_level_higher ); ?>" min="1" max="9" class="small-text"></td>
                                    <td><input type="number" name="handicap_rules[<?php echo $i; ?>][skill_level_lower]"  value="<?php echo esc_attr( $rule->skill_level_lower );  ?>" min="1" max="9" class="small-text"></td>
                                    <td><input type="number" name="handicap_rules[<?php echo $i; ?>][race_to_higher]"      value="<?php echo esc_attr( $rule->race_to_higher );      ?>" min="1" max="20" class="small-text"></td>
                                    <td><input type="number" name="handicap_rules[<?php echo $i; ?>][race_to_lower]"       value="<?php echo esc_attr( $rule->race_to_lower );       ?>" min="1" max="20" class="small-text"></td>
                                    <td><button type="button" class="button button-small ptm-remove-rule">&times;</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="button" class="button" id="ptm-add-rule" style="margin-top:10px;">
                            + <?php _e( 'Add Rule', 'ptm-tournaments' ); ?>
                        </button>

                    </div>
                </div>
            </div>

        <!-- Payout rules panel (always shown) -->
        <div class="ptm-form-col ptm-payout-section" style="grid-column: 1 / -1">
            <div class="postbox">
                <div class="postbox-header"><h2><?php _e( 'Payout Structure', 'ptm-tournaments' ); ?></h2></div>
                <div class="inside">
                    <p class="description">
                        <?php _e( 'Define how the prize pot is split. Percentages should total 100%. Dollar amounts are calculated automatically from Entrance Fee × Players.', 'ptm-tournaments' ); ?>
                    </p>
                    <?php
                    $payout_rules = $tournament_id ? PTM_Tournament::get_payout_rules( $tournament_id ) : [];
                    $default_rules = empty( $payout_rules ) ? [
                        [ 'position_label' => '1st Place',   'position_from' => 1, 'position_to' => 1, 'pct' => 50 ],
                        [ 'position_label' => '2nd Place',   'position_from' => 2, 'position_to' => 2, 'pct' => 30 ],
                        [ 'position_label' => '3rd-4th Place','position_from'=> 3, 'position_to' => 4, 'pct' => 10 ],
                    ] : $payout_rules;
                    ?>
                    <table class="widefat ptm-payout-table" id="payout-rules-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'Label', 'ptm-tournaments' ); ?></th>
                                <th><?php _e( 'From Position', 'ptm-tournaments' ); ?></th>
                                <th><?php _e( 'To Position', 'ptm-tournaments' ); ?></th>
                                <th><?php _e( '% Each (of Pot)', 'ptm-tournaments' ); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="payout-rules-body">
                            <?php foreach ( $default_rules as $i => $rule ) : ?>
                            <tr class="ptm-payout-row">
                                <td><input type="text"   name="payout_rules[<?php echo $i; ?>][position_label]" value="<?php echo esc_attr( is_array($rule) ? $rule['position_label'] : $rule->position_label ); ?>" class="regular-text" placeholder="e.g. 1st Place"></td>
                                <td><input type="number" name="payout_rules[<?php echo $i; ?>][position_from]"  value="<?php echo esc_attr( is_array($rule) ? $rule['position_from'] : $rule->position_from ); ?>"  min="1" class="small-text"></td>
                                <td><input type="number" name="payout_rules[<?php echo $i; ?>][position_to]"    value="<?php echo esc_attr( is_array($rule) ? $rule['position_to'] : $rule->position_to ); ?>"    min="1" class="small-text"></td>
                                <td><input type="number" name="payout_rules[<?php echo $i; ?>][pct]"           value="<?php echo esc_attr( is_array($rule) ? $rule['pct'] : $rule->pct ); ?>"           min="0" max="100" step="0.5" class="small-text"> %</td>
                                <td><button type="button" class="button button-small ptm-remove-payout">&times;</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:10px; display:flex; align-items:center; gap:16px;">
                        <button type="button" class="button" id="ptm-add-payout">+ <?php _e( 'Add Row', 'ptm-tournaments' ); ?></button>
                        <span id="ptm-pct-total-wrap" style="font-size:13px;">
                            <?php _e( 'Total pot paid out:', 'ptm-tournaments' ); ?>
                            <strong id="ptm-pct-total">0</strong>%
                            <span id="ptm-pct-warn" style="color:#d63638; display:none"> ⚠ <?php _e( 'Should equal 100% to pay out the full pot', 'ptm-tournaments' ); ?></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- .ptm-form-grid -->

        <div class="ptm-form-actions">
            <?php submit_button( $is_new ? __( 'Create Tournament', 'ptm-tournaments' ) : __( 'Save Tournament', 'ptm-tournaments' ), 'primary', 'submit', false ); ?>

            <?php if ( ! $is_new ) : ?>
                &nbsp;
                <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=roster&tournament_id=' . $tid ); ?>" class="button">
                    <?php _e( 'Manage Roster →', 'ptm-tournaments' ); ?>
                </a>
            <?php endif; ?>

            <?php if ( ! $is_new && $tournament->status !== 'draft' ) : ?>
                &nbsp;
                <a href="<?php echo admin_url( 'admin.php?page=ptm-tournaments&action=bracket&tournament_id=' . $tid ); ?>" class="button button-primary">
                    <?php _e( 'View Bracket →', 'ptm-tournaments' ); ?>
                </a>
            <?php endif; ?>

        </div><!-- .ptm-form-actions -->

    </form><!-- #ptm-tournament-form -->

    <?php if ( ! $is_new && PTM_Roles::can_manage_tournaments() ) : ?>
    <div class="ptm-delete-section">
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>"
              onsubmit="return confirm('<?php _e( 'Delete this tournament and all its data? This cannot be undone.', 'ptm-tournaments' ); ?>')">
            <?php wp_nonce_field( 'ptm_delete_tournament' ); ?>
            <input type="hidden" name="action"        value="ptm_delete_tournament">
            <input type="hidden" name="tournament_id" value="<?php echo $tid; ?>">
            <button type="submit" class="button button-link-delete">
                <?php _e( 'Delete Tournament', 'ptm-tournaments' ); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>
