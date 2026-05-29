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

        // Enqueue assets
        wp_enqueue_style(  'ptm-public', PTM_PLUGIN_URL . 'public/css/public.css', [], PTM_VERSION );
        wp_enqueue_script( 'ptm-public', PTM_PLUGIN_URL . 'public/js/public.js', [ 'jquery' ], PTM_VERSION, true );
        wp_localize_script( 'ptm-public', 'PTM', [
            'restUrl'      => rest_url( 'gdc/v1/' ),
            'pollInterval' => (int) PTM_Settings::get( 'poll_interval_ms' ),
        ] );

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

        // Titles
        add_filter( 'document_title_parts', function( $parts ) use ( $tournament, $sub_page ) {
            $parts['title'] = esc_html( $tournament->name )
                . ( $sub_page === 'results' ? ' — Results' : '' );
            return $parts;
        } );
        add_filter( 'the_content', fn() => $content_html );

        // Inject synthetic post so theme renders normally
        global $wp_query, $post;
        $fake                  = new stdClass();
        $fake->ID              = 0;
        $fake->post_title      = $tournament->name;
        $fake->post_content    = $content_html;
        $fake->post_status     = 'publish';
        $fake->post_type       = 'page';
        $fake->post_name       = 'ptm-tournament-' . $tournament_id;
        $fake->post_date       = current_time( 'mysql' );
        $fake->post_date_gmt   = current_time( 'mysql', 1 );
        $fake->post_modified   = $fake->post_date;
        $fake->post_modified_gmt = $fake->post_date_gmt;
        $fake->guid            = home_url( '/tournament/' . ( $tournament->slug ?? $tournament_id ) . '/' );
        $fake->comment_status  = 'closed';
        $fake->ping_status     = 'closed';
        $fake->comment_count   = 0;
        $fake->post_password   = '';
        $fake->post_excerpt    = '';
        $fake->post_parent     = 0;
        $fake->menu_order      = 0;
        $fake->filter          = 'raw';
        $post                  = new WP_Post( $fake );

        $wp_query->posts           = [ $post ];
        $wp_query->post            = $post;
        $wp_query->found_posts     = 1;
        $wp_query->post_count      = 1;
        $wp_query->is_404          = false;
        $wp_query->is_page         = true;
        $wp_query->is_singular     = true;
        $wp_query->is_home         = false;

        $this->disable_page_builders();
        require $this->resolve_page_template();
        exit;
    }

    /**
     * Suppress page builder plugins so they don't intercept PTM virtual pages.
     * Covers Divi, Elementor, Beaver Builder, and Gutenberg FSE.
     */
    private function disable_page_builders(): void {
        // Divi
        add_filter( 'et_pb_is_pagebuilder_used', '__return_false' );
        add_filter( 'et_fb_enabled',             '__return_false' );
        // Elementor
        add_filter( 'elementor/page/should_run',    '__return_false' );
        add_filter( 'elementor/document/urls/edit', '__return_false' );
        // Beaver Builder
        add_filter( 'fl_builder_is_enabled', '__return_false' );
        // Gutenberg Full-Site Editing
        add_filter( 'use_block_editor_for_post', '__return_false' );
    }

    /**
     * Resolves the best standard page template, bypassing builder-injected templates.
     * Prefers page.php → singular.php → index.php over any builder-specific file.
     */
    private function resolve_page_template(): string {
        $standard = locate_template( [ 'page.php', 'singular.php', 'index.php' ] );
        if ( $standard ) {
            return $standard;
        }
        return get_index_template();
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
