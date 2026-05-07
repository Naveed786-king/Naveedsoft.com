<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Affiliate {

    public static function init_ajax() {
        add_action( 'wp_ajax_sep_get_affiliate_stats', array( __CLASS__, 'ajax_get_stats' ) );
        add_action( 'wp_ajax_sep_request_withdrawal', array( __CLASS__, 'ajax_request_withdrawal' ) );
        add_action( 'wp_ajax_sep_get_affiliate_activity', array( __CLASS__, 'ajax_get_activity' ) );
    }

    public static function track_referral_click() {
        if ( ! isset( $_GET['ref'] ) ) {
            return;
        }

        $code = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
        if ( empty( $code ) ) {
            return;
        }

        global $wpdb;
        $affiliate = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sep_affiliates WHERE referral_code = %s AND status = 'active'",
                $code
            )
        );

        if ( ! $affiliate ) {
            return;
        }

        $visitor_ip = self::get_visitor_ip();

        // Avoid duplicate clicks from same IP within 24 hours
        $recent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sep_affiliate_clicks
                 WHERE affiliate_id = %d AND visitor_ip = %s AND clicked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $affiliate->id,
                $visitor_ip
            )
        );

        if ( $recent > 0 ) {
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'sep_affiliate_clicks',
            array(
                'affiliate_id' => $affiliate->id,
                'visitor_ip'   => $visitor_ip,
                'page_url'     => esc_url_raw( home_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ),
                'referrer_url' => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
                'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'clicked_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}sep_affiliates SET total_clicks = total_clicks + 1 WHERE id = %d",
                $affiliate->id
            )
        );

        // Set cookie for 30 days to track conversions
        setcookie( 'sep_ref', $code, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        SEP_Database::log_activity( 'affiliate_click', sprintf( 'Referral click for %s', $affiliate->name ), array(
            'affiliate_id' => $affiliate->id,
            'ip'           => $visitor_ip,
        ) );
    }

    public static function get_affiliate_by_user( $user_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sep_affiliates WHERE user_id = %d",
                $user_id
            )
        );
    }

    public static function get_affiliate( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sep_affiliates WHERE id = %d", $id )
        );
    }

    public static function get_all_affiliates( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'status'  => '',
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => 50,
            'offset'  => 0,
            'search'  => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[]  = '(name LIKE %s OR email LIKE %s OR referral_code LIKE %s)';
            $search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode( ' AND ', $where );
        $orderby   = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $sql = "SELECT * FROM {$wpdb->prefix}sep_affiliates WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql );
    }

    public static function get_affiliate_count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sep_affiliates" );
    }

    public static function get_total_earnings() {
        global $wpdb;
        return (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_earnings), 0) FROM {$wpdb->prefix}sep_affiliates" );
    }

    public static function add_affiliate( $data ) {
        global $wpdb;
        $code = ! empty( $data['referral_code'] ) ? $data['referral_code'] : self::generate_referral_code();

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'sep_affiliates',
            array(
                'user_id'         => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
                'name'            => sanitize_text_field( $data['name'] ),
                'email'           => sanitize_email( isset( $data['email'] ) ? $data['email'] : '' ),
                'phone'           => sanitize_text_field( isset( $data['phone'] ) ? $data['phone'] : '' ),
                'referral_code'   => sanitize_text_field( $code ),
                'total_clicks'    => 0,
                'total_signups'   => 0,
                'total_earnings'  => 0,
                'pending_balance' => 0,
                'status'          => 'active',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s', '%s' )
        );

        if ( $inserted ) {
            SEP_Database::log_activity( 'affiliate', sprintf( 'New affiliate added: %s', $data['name'] ) );
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function update_affiliate( $id, $data ) {
        global $wpdb;
        $update = array();
        $format = array();

        $fields = array(
            'name'            => '%s',
            'email'           => '%s',
            'phone'           => '%s',
            'referral_code'   => '%s',
            'total_clicks'    => '%d',
            'total_signups'   => '%d',
            'total_earnings'  => '%f',
            'pending_balance' => '%f',
            'status'          => '%s',
        );

        foreach ( $fields as $field => $fmt ) {
            if ( isset( $data[ $field ] ) ) {
                $update[ $field ] = $data[ $field ];
                $format[]         = $fmt;
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return $wpdb->update(
            $wpdb->prefix . 'sep_affiliates',
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
    }

    public static function delete_affiliate( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'sep_affiliate_clicks', array( 'affiliate_id' => $id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'sep_affiliate_conversions', array( 'affiliate_id' => $id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'sep_withdrawals', array( 'affiliate_id' => $id ), array( '%d' ) );
        return $wpdb->delete( $wpdb->prefix . 'sep_affiliates', array( 'id' => $id ), array( '%d' ) );
    }

    public static function add_conversion( $affiliate_id, $type, $amount, $description = '' ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'sep_affiliate_conversions',
            array(
                'affiliate_id' => $affiliate_id,
                'type'         => sanitize_text_field( $type ),
                'amount'       => floatval( $amount ),
                'description'  => sanitize_text_field( $description ),
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%f', '%s', '%s' )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}sep_affiliates
                 SET total_earnings = total_earnings + %f, pending_balance = pending_balance + %f, total_signups = total_signups + 1
                 WHERE id = %d",
                $amount,
                $amount,
                $affiliate_id
            )
        );

        SEP_Database::log_activity( 'conversion', sprintf( 'Conversion of %s for affiliate #%d', number_format( $amount ), $affiliate_id ) );
    }

    // Withdrawals
    public static function get_withdrawals( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'affiliate_id' => 0,
            'status'       => '',
            'limit'        => 50,
            'offset'       => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['affiliate_id'] > 0 ) {
            $where[]  = 'w.affiliate_id = %d';
            $params[] = $args['affiliate_id'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'w.status = %s';
            $params[] = $args['status'];
        }

        $where_sql = implode( ' AND ', $where );

        $sql = "SELECT w.*, a.name as affiliate_name, a.email as affiliate_email
                FROM {$wpdb->prefix}sep_withdrawals w
                LEFT JOIN {$wpdb->prefix}sep_affiliates a ON w.affiliate_id = a.id
                WHERE {$where_sql}
                ORDER BY w.requested_at DESC
                LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql );
    }

    public static function get_pending_withdrawals_count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sep_withdrawals WHERE status = 'pending'"
        );
    }

    public static function get_pending_withdrawals_total() {
        global $wpdb;
        return (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}sep_withdrawals WHERE status = 'pending'"
        );
    }

    public static function request_withdrawal( $affiliate_id, $amount, $method, $account_info ) {
        global $wpdb;

        $affiliate = self::get_affiliate( $affiliate_id );
        if ( ! $affiliate ) {
            return new WP_Error( 'invalid', __( 'Invalid affiliate.', 'spectacular' ) );
        }
        if ( $amount <= 0 ) {
            return new WP_Error( 'amount', __( 'Amount must be greater than zero.', 'spectacular' ) );
        }
        if ( $amount > $affiliate->pending_balance ) {
            return new WP_Error( 'balance', __( 'Insufficient balance.', 'spectacular' ) );
        }

        $wpdb->insert(
            $wpdb->prefix . 'sep_withdrawals',
            array(
                'affiliate_id' => $affiliate_id,
                'amount'       => $amount,
                'method'       => sanitize_text_field( $method ),
                'account_info' => sanitize_text_field( $account_info ),
                'status'       => 'pending',
                'requested_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%f', '%s', '%s', '%s', '%s' )
        );

        SEP_Database::log_activity( 'withdrawal', sprintf( 'Withdrawal request of %s by %s', number_format( $amount ), $affiliate->name ) );

        return $wpdb->insert_id;
    }

    public static function process_withdrawal( $withdrawal_id, $action, $note = '' ) {
        global $wpdb;

        $withdrawal = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sep_withdrawals WHERE id = %d", $withdrawal_id )
        );

        if ( ! $withdrawal || 'pending' !== $withdrawal->status ) {
            return false;
        }

        $new_status = 'approve' === $action ? 'approved' : 'rejected';

        $wpdb->update(
            $wpdb->prefix . 'sep_withdrawals',
            array(
                'status'       => $new_status,
                'admin_note'   => sanitize_text_field( $note ),
                'processed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $withdrawal_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( 'approved' === $new_status ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}sep_affiliates SET pending_balance = pending_balance - %f WHERE id = %d",
                    $withdrawal->amount,
                    $withdrawal->affiliate_id
                )
            );
        }

        SEP_Database::log_activity( 'withdrawal', sprintf( 'Withdrawal #%d %s', $withdrawal_id, $new_status ) );

        return true;
    }

    public static function ajax_get_stats() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'spectacular' ) ) );
        }

        $affiliate = self::get_affiliate_by_user( get_current_user_id() );
        if ( ! $affiliate ) {
            wp_send_json_error( array( 'message' => __( 'You are not registered as an affiliate.', 'spectacular' ) ) );
        }

        $recent_clicks = self::get_recent_clicks( $affiliate->id, 10 );

        wp_send_json_success( array(
            'referral_code'   => $affiliate->referral_code,
            'referral_link'   => add_query_arg( 'ref', $affiliate->referral_code, home_url( '/' ) ),
            'total_clicks'    => (int) $affiliate->total_clicks,
            'total_signups'   => (int) $affiliate->total_signups,
            'total_earnings'  => (float) $affiliate->total_earnings,
            'pending_balance' => (float) $affiliate->pending_balance,
            'recent_clicks'   => $recent_clicks,
        ) );
    }

    public static function ajax_request_withdrawal() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'spectacular' ) ) );
        }

        $affiliate = self::get_affiliate_by_user( get_current_user_id() );
        if ( ! $affiliate ) {
            wp_send_json_error( array( 'message' => __( 'You are not registered as an affiliate.', 'spectacular' ) ) );
        }

        $amount       = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
        $method       = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : 'mobile_money';
        $account_info = isset( $_POST['account_info'] ) ? sanitize_text_field( wp_unslash( $_POST['account_info'] ) ) : '';

        $result = self::request_withdrawal( $affiliate->id, $amount, $method, $account_info );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Withdrawal request submitted. Admin will review shortly.', 'spectacular' ) ) );
    }

    public static function ajax_get_activity() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'spectacular' ) ) );
        }

        $affiliate = self::get_affiliate_by_user( get_current_user_id() );
        if ( ! $affiliate ) {
            wp_send_json_error( array( 'message' => __( 'Not an affiliate.', 'spectacular' ) ) );
        }

        global $wpdb;
        $conversions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sep_affiliate_conversions WHERE affiliate_id = %d ORDER BY created_at DESC LIMIT 20",
                $affiliate->id
            )
        );

        $withdrawals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sep_withdrawals WHERE affiliate_id = %d ORDER BY requested_at DESC LIMIT 20",
                $affiliate->id
            )
        );

        wp_send_json_success( array(
            'conversions' => $conversions,
            'withdrawals' => $withdrawals,
        ) );
    }

    public static function get_recent_clicks( $affiliate_id, $limit = 10 ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT visitor_ip, page_url, clicked_at FROM {$wpdb->prefix}sep_affiliate_clicks
                 WHERE affiliate_id = %d ORDER BY clicked_at DESC LIMIT %d",
                $affiliate_id,
                $limit
            )
        );
    }

    private static function generate_referral_code() {
        return strtoupper( substr( md5( uniqid( wp_rand(), true ) ), 0, 8 ) );
    }

    private static function get_visitor_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                return $ip;
            }
        }
        return '0.0.0.0';
    }
}
