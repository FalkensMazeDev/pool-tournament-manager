<?php
defined( 'ABSPATH' ) || exit;

class PTM_Install {

    const DB_VERSION_OPTION = 'ptm_db_version';
    const DB_VERSION        = '1.6.0';

    public static function activate() {
        self::create_tables();
        PTM_Roles::add_roles();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        $installed = get_option( self::DB_VERSION_OPTION, '0.0.0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::create_tables();
            self::run_migrations( $installed );
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }

    /**
     * Incremental migrations for sites upgrading from older versions.
     * dbDelta handles ADD COLUMN safely — skips if column already exists.
     */
    private static function run_migrations( string $from ): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        if ( version_compare( $from, '1.6.0', '<' ) ) {
            // Add money_won to player stats
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}ptm_player_stats ADD COLUMN IF NOT EXISTS money_won DECIMAL(10,2) NOT NULL DEFAULT 0.00" );
        }

        if ( version_compare( $from, '1.5.0', '<' ) ) {
            dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_tournaments (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name             VARCHAR(200)    NOT NULL,
                game_type        ENUM('8ball','9ball','10ball') NOT NULL,
                bracket_type     ENUM('single_elim','double_elim') NOT NULL,
                status           ENUM('draft','active','complete') NOT NULL DEFAULT 'draft',
                race_to_winners  TINYINT UNSIGNED NOT NULL DEFAULT 5,
                race_to_losers   TINYINT UNSIGNED NOT NULL DEFAULT 4,
                handicap_enabled TINYINT(1)       NOT NULL DEFAULT 0,
                is_public        TINYINT(1)       NOT NULL DEFAULT 1,
                tournament_date  DATE                      DEFAULT NULL,
                num_tables       TINYINT UNSIGNED NOT NULL DEFAULT 4,
                entrance_fee     DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
                director_fee     DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
                money_added      DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
                slug             VARCHAR(220)             DEFAULT NULL,
                notes            TEXT                      DEFAULT NULL,
                created_by       BIGINT UNSIGNED           DEFAULT NULL,
                created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status (status),
                KEY tournament_date (tournament_date),
                UNIQUE KEY slug (slug)
            ) $charset;" );
        }

        if ( version_compare( $from, '1.2.0', '<' ) ) {
            // Add entrance_fee to existing tournaments
            dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_tournaments (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name             VARCHAR(200)    NOT NULL,
                game_type        ENUM('8ball','9ball','10ball') NOT NULL,
                bracket_type     ENUM('single_elim','double_elim') NOT NULL,
                status           ENUM('draft','active','complete') NOT NULL DEFAULT 'draft',
                race_to_winners  TINYINT UNSIGNED NOT NULL DEFAULT 5,
                race_to_losers   TINYINT UNSIGNED NOT NULL DEFAULT 4,
                handicap_enabled TINYINT(1)       NOT NULL DEFAULT 0,
                is_public        TINYINT(1)       NOT NULL DEFAULT 1,
                num_tables       TINYINT UNSIGNED NOT NULL DEFAULT 4,
                entrance_fee     DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
                slug             VARCHAR(220)             DEFAULT NULL,
                tournament_date  DATE                      DEFAULT NULL,
                notes            TEXT                      DEFAULT NULL,
                created_by       BIGINT UNSIGNED           DEFAULT NULL,
                created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status (status),
                KEY tournament_date (tournament_date)
            ) $charset;" );

            // Payout structure table — one row per finish position group
            dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_payout_rules (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tournament_id   BIGINT UNSIGNED NOT NULL,
                position_label  VARCHAR(50)     NOT NULL,
                position_from   SMALLINT UNSIGNED NOT NULL,
                position_to     SMALLINT UNSIGNED NOT NULL,
                pct             DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
                PRIMARY KEY (id),
                KEY tournament_id (tournament_id)
            ) $charset;" );
        }

        if ( version_compare( $from, '1.2.0', '<' ) ) {
            // Back-fill slugs for tournaments that don't have one
            $rows = $wpdb->get_results(
                "SELECT id, name FROM {$wpdb->prefix}ptm_tournaments WHERE slug IS NULL OR slug = ''",
                ARRAY_A
            );
            foreach ( $rows as $row ) {
                $slug = PTM_Tournament::generate_slug( $row['name'], (int) $row['id'] );
                $wpdb->update(
                    $wpdb->prefix . 'ptm_tournaments',
                    [ 'slug' => $slug ],
                    [ 'id'   => $row['id'] ],
                    [ '%s' ], [ '%d' ]
                );
            }
        }

        if ( version_compare( $from, '1.1.0', '<' ) ) {
            // Add num_tables to existing tournaments table
            dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_tournaments (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name             VARCHAR(200)    NOT NULL,
                game_type        ENUM('8ball','9ball','10ball') NOT NULL,
                bracket_type     ENUM('single_elim','double_elim') NOT NULL,
                status           ENUM('draft','active','complete') NOT NULL DEFAULT 'draft',
                race_to_winners  TINYINT UNSIGNED NOT NULL DEFAULT 5,
                race_to_losers   TINYINT UNSIGNED NOT NULL DEFAULT 4,
                handicap_enabled TINYINT(1)       NOT NULL DEFAULT 0,
                is_public        TINYINT(1)       NOT NULL DEFAULT 1,
                num_tables       TINYINT UNSIGNED NOT NULL DEFAULT 4,
            entrance_fee     DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
            slug             VARCHAR(220)             DEFAULT NULL,
                tournament_date  DATE                      DEFAULT NULL,
                notes            TEXT                      DEFAULT NULL,
                created_by       BIGINT UNSIGNED           DEFAULT NULL,
                created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status (status),
                KEY tournament_date (tournament_date)
            ) $charset;" );
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Permanent player registry
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_players (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(100)    NOT NULL,
            email           VARCHAR(150)             DEFAULT NULL,
            phone           VARCHAR(30)              DEFAULT NULL,
            apa_number      VARCHAR(30)              DEFAULT NULL,
            apa_skill_level TINYINT UNSIGNED         DEFAULT NULL,
            fargo_id        VARCHAR(30)              DEFAULT NULL,
            fargo_rating    SMALLINT UNSIGNED        DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name)
        ) $charset;" );

        // Player custom meta — arbitrary key-value pairs per player
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_player_meta (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id  BIGINT UNSIGNED NOT NULL,
            meta_key   VARCHAR(100)    NOT NULL,
            meta_value TEXT                     DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY player_meta_key (player_id, meta_key),
            KEY player_id (player_id)
        ) $charset;" );

        // Career stats — one row per player per tournament
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_player_stats (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id       BIGINT UNSIGNED NOT NULL,
            tournament_id   BIGINT UNSIGNED NOT NULL,
            matches_played  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            matches_won     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            matches_lost    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            games_won       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            games_lost      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            finish_position SMALLINT UNSIGNED          DEFAULT NULL,
            money_won       DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            UNIQUE KEY player_tournament (player_id, tournament_id),
            KEY player_id (player_id),
            KEY tournament_id (tournament_id)
        ) $charset;" );

        // Tournament config
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_tournaments (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name             VARCHAR(200)    NOT NULL,
            game_type        ENUM('8ball','9ball','10ball') NOT NULL,
            bracket_type     ENUM('single_elim','double_elim') NOT NULL,
            status           ENUM('draft','active','complete') NOT NULL DEFAULT 'draft',
            race_to_winners  TINYINT UNSIGNED NOT NULL DEFAULT 5,
            race_to_losers   TINYINT UNSIGNED NOT NULL DEFAULT 4,
            handicap_enabled TINYINT(1)       NOT NULL DEFAULT 0,
            is_public        TINYINT(1)       NOT NULL DEFAULT 1,
            tournament_date  DATE                      DEFAULT NULL,
            num_tables       TINYINT UNSIGNED NOT NULL DEFAULT 4,
            entrance_fee     DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
            director_fee     DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
            money_added      DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
            slug             VARCHAR(220)             DEFAULT NULL,
            notes            TEXT                      DEFAULT NULL,
            created_by       BIGINT UNSIGNED           DEFAULT NULL,
            created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY tournament_date (tournament_date),
            UNIQUE KEY slug (slug)
        ) $charset;" );

        // Tournament roster — players entered into a specific tournament
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_tournament_players (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id BIGINT UNSIGNED NOT NULL,
            player_id     BIGINT UNSIGNED NOT NULL,
            seed          SMALLINT UNSIGNED        DEFAULT NULL,
            skill_level   TINYINT UNSIGNED         DEFAULT NULL,
            added_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tournament_player (tournament_id, player_id),
            KEY tournament_id (tournament_id),
            KEY player_id (player_id)
        ) $charset;" );

        // Handicap race-to matrix per tournament
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_handicap_rules (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id       BIGINT UNSIGNED NOT NULL,
            skill_level_higher  TINYINT UNSIGNED NOT NULL,
            skill_level_lower   TINYINT UNSIGNED NOT NULL,
            race_to_higher      TINYINT UNSIGNED NOT NULL,
            race_to_lower       TINYINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tournament_matchup (tournament_id, skill_level_higher, skill_level_lower),
            KEY tournament_id (tournament_id)
        ) $charset;" );

        // Payout rules — prize structure per tournament
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_payout_rules (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id   BIGINT UNSIGNED NOT NULL,
            position_label  VARCHAR(50)     NOT NULL,
            position_from   SMALLINT UNSIGNED NOT NULL,
            position_to     SMALLINT UNSIGNED NOT NULL,
            pct             DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY tournament_id (tournament_id)
        ) $charset;" );

        // Match tree — one row per match in every bracket
        dbDelta( "CREATE TABLE {$wpdb->prefix}ptm_matches (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tournament_id       BIGINT UNSIGNED NOT NULL,
            bracket_side        ENUM('winners','losers','finals') NOT NULL DEFAULT 'winners',
            round               SMALLINT        NOT NULL DEFAULT 1,
            match_number        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            player1_id          BIGINT UNSIGNED          DEFAULT NULL,
            player2_id          BIGINT UNSIGNED          DEFAULT NULL,
            player1_score       TINYINT UNSIGNED NOT NULL DEFAULT 0,
            player2_score       TINYINT UNSIGNED NOT NULL DEFAULT 0,
            race_to_player1     TINYINT UNSIGNED NOT NULL DEFAULT 5,
            race_to_player2     TINYINT UNSIGNED NOT NULL DEFAULT 5,
            winner_id           BIGINT UNSIGNED          DEFAULT NULL,
            status              ENUM('pending','in_progress','complete') NOT NULL DEFAULT 'pending',
            next_match_id       BIGINT UNSIGNED          DEFAULT NULL,
            loser_next_match_id BIGINT UNSIGNED          DEFAULT NULL,
            score_token         VARCHAR(64)              DEFAULT NULL,
            table_number        TINYINT UNSIGNED         DEFAULT NULL,
            created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY score_token (score_token),
            KEY tournament_id (tournament_id),
            KEY status (status),
            KEY bracket_side (bracket_side)
        ) $charset;" );
    }

    /**
     * Drops all plugin tables. Called from uninstall.php only.
     */
    public static function drop_tables(): void {
        global $wpdb;

        // Drop in reverse dependency order
        $tables = [
            "{$wpdb->prefix}ptm_payout_rules",
            "{$wpdb->prefix}ptm_player_stats",
            "{$wpdb->prefix}ptm_matches",
            "{$wpdb->prefix}ptm_handicap_rules",
            "{$wpdb->prefix}ptm_tournament_players",
            "{$wpdb->prefix}ptm_tournaments",
            "{$wpdb->prefix}ptm_players",
        ];

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS `$table`" );
        }
    }
}
