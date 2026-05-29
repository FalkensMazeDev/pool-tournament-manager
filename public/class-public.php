<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTM_Public {

    public function __construct() {
        add_action( 'init',                     [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars',               [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect',        [ $this, 'handle_scorer_page' ] );
        add_action( 'template_redirect',        [ $this, 'handle_bracket_page' ] );
        add_action( 'wp_enqueue_scripts',       [ $this, 'enqueue_scripts' ] );
        add_shortcode( 'ptm_tournaments',       [ $this, 'shortcode_tournament_list' ] );
        add_shortcode( 'ptm_bracket',           [ $this, 'shortcode_bracket' ] );
    }

    // ----------------------------------------------------------------
    // Rewrite rules for scorer token URL
    // ----------------------------------------------------------------

    // ── URL / rewrite rules ──────────────────────────────────────────────────

    public function add_rewrite_rules() {
        $scorer_slug     = preg_quote( PTM_Settings::get( 'scorer_base_slug' ),      '#' );
        $tourney_slug    = preg_quote( PTM_Settings::get( 'tournament_base_slug' ),  '#' );
        $results_slug    = preg_quote( PTM_Settings::get( 'results_sub_slug' ),      '#' );

        // Scorer token URL
        add_rewrite_rule(
            '^' . $scorer_slug . '/([a-zA-Z0-9]+)/?$',
            'index.php?ptm_score_token=$matches[1]',
            'top'
        );

        // Tournament results sub-page (must be before bracket rule)
        add_rewrite_rule(
            '^' . $tourney_slug . '/([^/]+)/' . $results_slug . '/?$',
            'index.php?ptm_tournament_slug=$matches[1]&ptm_page=results',
            'top'
        );

        // Tournament bracket
        add_rewrite_rule(
            '^' . $tourney_slug . '/([^/]+)/?$',
            'index.php?ptm_tournament_slug=$matches[1]',
            'top'
        );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'ptm_score_token';
        $vars[] = 'ptm_tournament';       // legacy ?ptm_tournament=N still works
        $vars[] = 'ptm_tournament_slug';  // pretty /tournament/{slug}/
        $vars[] = 'ptm_page';             // sub-page (results, etc.)
        return $vars;
    }

    // ── Template loader — child theme overrides ───────────────────────────────

    /**
     * Loads a plugin view, allowing child/parent theme overrides.
     *
     * Override search order:
     *   1. {child-theme}/ptm-tournaments/{template}.php
     *   2. {parent-theme}/ptm-tournaments/{template}.php
     *   3. plugin's own public/views/{template}.php
     *
     * @param string $template  Filename without path, e.g. 'bracket.php'
     * @param array  $vars      Variables to extract into the template scope.
     */
    public static function load_template( string $template, array $vars = [] ): void {
        $locations = [
            get_stylesheet_directory() . '/ptm-tournaments/' . $template,
            get_template_directory()   . '/ptm-tournaments/' . $template,
            PTM_PLUGIN_DIR . 'public/views/' . $template,
        ];

        $file = null;
        foreach ( $locations as $path ) {
            if ( file_exists( $path ) ) {
                $file = $path;
                break;
            }
        }

        if ( ! $file ) return;

        // Expose variables to the template
        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        require $file;
    }

    // ----------------------------------------------------------------
    // Scorer token page
    // ----------------------------------------------------------------

    public function handle_scorer_page() {
        $token = get_query_var( 'ptm_score_token' );
        if ( ! $token ) return;

        $match_data = PTM_Match::get_by_token( sanitize_text_field( $token ) );
        if ( ! $match_data ) {
            wp_die( __( 'Match not found.', 'ptm-tournaments' ), '', [ 'response' => 404 ] );
        }

        // Hydrate with player names and tournament info
        $match_data = $this->hydrate_match_for_scorer( $match_data );

        // Cast to object so scorer.php can use -> notation
        $match = (object) $match_data;

        // Render the scorer page (bypasses theme entirely)
        require_once PTM_PLUGIN_DIR . 'public/views/scorer.php';
        exit;
    }

    /**
     * Joins player names, winner name, and tournament info onto a match array.
     */
    private function hydrate_match_for_scorer( array $match ): array {
        global $wpdb;

        $match['player1_name']    = null;
        $match['player2_name']    = null;
        $match['winner_name']     = null;
        $match['tournament_name'] = null;
        $match['game_type']       = null;

        if ( $match['player1_id'] ) {
            $match['player1_name'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}ptm_players WHERE id = %d", $match['player1_id'] )
            );
        }
        if ( $match['player2_id'] ) {
            $match['player2_name'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}ptm_players WHERE id = %d", $match['player2_id'] )
            );
        }
        if ( $match['winner_id'] ) {
            $match['winner_name'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}ptm_players WHERE id = %d", $match['winner_id'] )
            );
        }

        $tournament = PTM_Tournament::get( (int) $match['tournament_id'] );
        if ( $tournament ) {
            $match['tournament_name'] = $tournament['name'];
            $match['game_type']       = $tournament['game_type'];
        }

        return $match;
    }

    // ----------------------------------------------------------------
    // Bracket page via ?ptm_tournament=N  (no shortcode page required)
    // ----------------------------------------------------------------

    // ── Tournament page dispatcher ───────────────────────────────────────────

    public function handle_bracket_page() {
        // Resolve tournament from pretty slug or legacy query var
        $slug          = get_query_var( 'ptm_tournament_slug' );
        $legacy_id     = absint( get_query_var( 'ptm_tournament' ) );
        $sub_page      = sanitize_key( get_query_var( 'ptm_page' ) ?: 'bracket' );

        if ( ! $slug && ! $legacy_id ) return;

        // Resolve: try slug first, fall back to numeric ID (for old tournaments without slugs)
        if ( $slug ) {
            $t_raw = PTM_Tournament::get_by_slug( $slug );
            if ( ! $t_raw && is_numeric( $slug ) ) {
                // Slug looks like a numeric ID — try direct ID lookup
                $t_raw = PTM_Tournament::get( (int) $slug );
            }
        } else {
            $t_raw = PTM_Tournament::get( $legacy_id );
        }

        if ( ! $t_raw ) {
            wp_die( __( 'Tournament not found.', 'ptm-tournaments' ), '', [ 'response' => 404 ] );
        }

        $tournament    = (object) $t_raw;
        $tournament_id = (int) $tournament->id;

        if ( ! $tournament->is_public && ! PTM_Roles::can_manage_tournaments() ) {
            wp_die( __( 'Tournament not found.', 'ptm-tournaments' ), '', [ 'response' => 404 ] );
        }

        // Build page content
        ob_start();
        if ( $sub_page === 'results' ) {
            $results  = PTM_Tournament::get_results( $tournament_id );
            $payouts  = PTM_Tournament::calculate_payouts( $tournament_id );
            self::load_template( 'results.php', compact( 'tournament', 'tournament_id', 'results', 'payouts' ) );
        } else {
            $bracket_raw = PTM_Bracket::get_bracket( $tournament_id );
            $bracket     = [];
            foreach ( $bracket_raw as $side => $rounds ) {
                foreach ( $rounds as $round => $matches ) {
                    $bracket[ $side ][ $round ] = array_map( fn( $m ) => (object) $m, $matches );
                }
            }
            self::load_template( 'bracket.php', compact( 'tournament', 'tournament_id', 'bracket' ) );
        }
        $content_html = ob_get_clean();

        // Render standalone page (bypasses theme entirely, same as scorer page)
        $page_title = esc_html( $tournament->name )
            . ( $sub_page === 'results' ? ' — Results' : '' );
        require PTM_PLUGIN_DIR . 'public/views/standalone-page.php';
        exit;
    }

    // ----------------------------------------------------------------
    // Scripts & Styles
    // ----------------------------------------------------------------

    public function enqueue_scripts() {
        $token      = get_query_var( 'ptm_score_token' );
        $tournament = get_query_var( 'ptm_tournament' );

        // Only load on PTM pages
        if ( ! $token && ! $tournament && ! $this->page_has_ptm_shortcode() ) {
            return;
        }

        wp_enqueue_style(
            'ptm-public',
            PTM_PLUGIN_URL . 'public/css/public.css',
            [],
            PTM_VERSION
        );

        wp_enqueue_script(
            'ptm-public',
            PTM_PLUGIN_URL . 'public/js/public.js',
            [ 'jquery' ],
            PTM_VERSION,
            true
        );

        wp_localize_script( 'ptm-public', 'PTM', [
            'restUrl'      => rest_url( 'gdc/v1/' ),
            'pollInterval' => 5000, // 5 seconds
        ] );
    }

    private function page_has_ptm_shortcode() {
        global $post;
        if ( ! $post ) return false;
        return has_shortcode( $post->post_content, 'ptm_tournaments' )
            || has_shortcode( $post->post_content, 'ptm_bracket' );
    }

    // ----------------------------------------------------------------
    // Shortcode: Tournament List
    // [ptm_tournaments] — shows public upcoming/active tournaments
    // ----------------------------------------------------------------

    public function shortcode_tournament_list( $atts ) {
        $atts = shortcode_atts( [
            'status' => 'active,draft',
            'limit'  => 10,
        ], $atts );

        $statuses    = array_map( 'trim', explode( ',', $atts['status'] ) );
        $tournaments = array_map(
            fn( $t ) => (object) $t,
            PTM_Tournament::get_all( [
                'status'    => $statuses,
                'is_public' => 1,
                'limit'     => absint( $atts['limit'] ),
                'order'     => 'ASC',
                'orderby'   => 'tournament_date',
            ] )
        );

        ob_start();
        require PTM_PLUGIN_DIR . 'public/views/tournament-list.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // Shortcode: Bracket View
    // [ptm_bracket id="5"] — shows a specific tournament bracket
    // Also handles ?ptm_tournament=5 query var
    // ----------------------------------------------------------------

    public function shortcode_bracket( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts );

        $tournament_id = absint( $atts['id'] ) ?: absint( get_query_var( 'ptm_tournament' ) );

        if ( ! $tournament_id ) {
            return '<p>' . __( 'No tournament specified.', 'ptm-tournaments' ) . '</p>';
        }

        $t_raw = PTM_Tournament::get( $tournament_id );

        if ( ! $t_raw ) {
            return '<p>' . __( 'Tournament not found.', 'ptm-tournaments' ) . '</p>';
        }

        $tournament = (object) $t_raw;

        if ( ! $tournament->is_public && ! PTM_Roles::can_manage_tournaments() ) {
            return '<p>' . __( 'Tournament not found.', 'ptm-tournaments' ) . '</p>';
        }

        $bracket_raw = PTM_Bracket::get_bracket( $tournament_id );
        $bracket     = [];
        foreach ( $bracket_raw as $side => $rounds ) {
            foreach ( $rounds as $round => $matches ) {
                $bracket[ $side ][ $round ] = array_map( fn( $m ) => (object) $m, $matches );
            }
        }

        ob_start();
        require PTM_PLUGIN_DIR . 'public/views/bracket.php';
        return ob_get_clean();
    }
}
