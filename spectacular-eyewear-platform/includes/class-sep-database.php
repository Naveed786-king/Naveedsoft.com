<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Database {

    public static function activate() {
        self::create_tables();
        self::seed_options();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = array();

        // Products table
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_products (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(255)    NOT NULL,
            brand           VARCHAR(255)    NOT NULL DEFAULT '',
            slug            VARCHAR(255)    NOT NULL DEFAULT '',
            description     TEXT            NOT NULL DEFAULT '',
            price           DECIMAL(12,2)   NOT NULL DEFAULT 0,
            currency        VARCHAR(10)     NOT NULL DEFAULT 'UGX',
            category        VARCHAR(100)    NOT NULL DEFAULT '',
            style           VARCHAR(100)    NOT NULL DEFAULT '',
            size            VARCHAR(50)     NOT NULL DEFAULT '',
            image_url       TEXT            NOT NULL DEFAULT '',
            gallery         LONGTEXT        NOT NULL DEFAULT '',
            color_variants  LONGTEXT        NOT NULL DEFAULT '',
            stock           INT             NOT NULL DEFAULT 0,
            featured        TINYINT(1)      NOT NULL DEFAULT 0,
            status          VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_slug (slug),
            KEY idx_status (status),
            KEY idx_category (category)
        ) $charset;";

        // Competition contestants
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_contestants (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(255)    NOT NULL,
            bio         TEXT            NOT NULL DEFAULT '',
            image_url   TEXT            NOT NULL DEFAULT '',
            votes       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status      VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_votes (votes)
        ) $charset;";

        // Votes log
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_votes (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contestant_id   BIGINT UNSIGNED NOT NULL,
            voter_ip        VARCHAR(45)     NOT NULL DEFAULT '',
            voter_cookie    VARCHAR(64)     NOT NULL DEFAULT '',
            user_id         BIGINT UNSIGNED NOT NULL DEFAULT 0,
            voted_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_contestant (contestant_id),
            KEY idx_voter_ip (voter_ip),
            KEY idx_voted_at (voted_at)
        ) $charset;";

        // Affiliates
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_affiliates (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name            VARCHAR(255)    NOT NULL,
            email           VARCHAR(255)    NOT NULL DEFAULT '',
            phone           VARCHAR(50)     NOT NULL DEFAULT '',
            referral_code   VARCHAR(64)     NOT NULL,
            total_clicks    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_signups   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_earnings  DECIMAL(12,2)   NOT NULL DEFAULT 0,
            pending_balance DECIMAL(12,2)   NOT NULL DEFAULT 0,
            status          VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_code (referral_code),
            KEY idx_user (user_id),
            KEY idx_email (email)
        ) $charset;";

        // Affiliate clicks
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_affiliate_clicks (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id    BIGINT UNSIGNED NOT NULL,
            visitor_ip      VARCHAR(45)     NOT NULL DEFAULT '',
            page_url        TEXT            NOT NULL DEFAULT '',
            referrer_url    TEXT            NOT NULL DEFAULT '',
            user_agent      TEXT            NOT NULL DEFAULT '',
            clicked_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_affiliate (affiliate_id),
            KEY idx_clicked (clicked_at)
        ) $charset;";

        // Affiliate conversions
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_affiliate_conversions (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id    BIGINT UNSIGNED NOT NULL,
            type            VARCHAR(50)     NOT NULL DEFAULT 'signup',
            amount          DECIMAL(12,2)   NOT NULL DEFAULT 0,
            description     VARCHAR(255)    NOT NULL DEFAULT '',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_affiliate (affiliate_id)
        ) $charset;";

        // Withdrawal requests
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_withdrawals (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id    BIGINT UNSIGNED NOT NULL,
            amount          DECIMAL(12,2)   NOT NULL DEFAULT 0,
            method          VARCHAR(50)     NOT NULL DEFAULT 'mobile_money',
            account_info    VARCHAR(255)    NOT NULL DEFAULT '',
            status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
            admin_note      TEXT            NOT NULL DEFAULT '',
            requested_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at    DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_affiliate (affiliate_id),
            KEY idx_status (status)
        ) $charset;";

        // Activity log
        $tables[] = "CREATE TABLE {$wpdb->prefix}sep_activity_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type        VARCHAR(50)     NOT NULL DEFAULT '',
            message     TEXT            NOT NULL DEFAULT '',
            meta        LONGTEXT        NOT NULL DEFAULT '',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (type),
            KEY idx_created (created_at)
        ) $charset;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        update_option( 'sep_db_version', SEP_VERSION );
    }

    public static function seed_options() {
        $defaults = array(
            'sep_competition_status'    => 'open',
            'sep_competition_title'     => 'Spectacular Eyewear Competition',
            'sep_competition_countdown' => '',
            'sep_vote_limit_per_day'    => 1,
            'sep_affiliate_commission'  => 10,
            'sep_currency'              => 'UGX',
            'sep_whatsapp_number'       => '256700193921',
        );
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    public static function log_activity( $type, $message, $meta = array() ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'sep_activity_log',
            array(
                'type'       => sanitize_text_field( $type ),
                'message'    => sanitize_text_field( $message ),
                'meta'       => wp_json_encode( $meta ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );
    }
}
