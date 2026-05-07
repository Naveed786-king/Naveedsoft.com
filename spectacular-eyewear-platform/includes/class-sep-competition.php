<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Competition {

    public static function init_ajax() {
        add_action( 'wp_ajax_sep_cast_vote', array( __CLASS__, 'ajax_cast_vote' ) );
        add_action( 'wp_ajax_nopriv_sep_cast_vote', array( __CLASS__, 'ajax_cast_vote' ) );
        add_action( 'wp_ajax_sep_get_leaderboard', array( __CLASS__, 'ajax_get_leaderboard' ) );
        add_action( 'wp_ajax_nopriv_sep_get_leaderboard', array( __CLASS__, 'ajax_get_leaderboard' ) );
    }

    public static function get_contestants( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'status'  => 'active',
            'orderby' => 'votes',
            'order'   => 'DESC',
            'limit'   => 50,
            'offset'  => 0,
        );
        $args  = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'sep_contestants';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
            $args['status'],
            $args['limit'],
            $args['offset']
        );
        return $wpdb->get_results( $sql );
    }

    public static function get_contestant( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sep_contestants WHERE id = %d", $id )
        );
    }

    public static function get_total_votes() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COALESCE(SUM(votes), 0) FROM {$wpdb->prefix}sep_contestants WHERE status = 'active'" );
    }

    public static function get_contestant_count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sep_contestants WHERE status = 'active'" );
    }

    public static function has_voted_today( $contestant_id = 0 ) {
        global $wpdb;
        $ip     = self::get_voter_ip();
        $cookie = self::get_voter_cookie();
        $today  = current_time( 'Y-m-d' );

        $where_contestant = '';
        $params           = array( $today, $ip );
        if ( $contestant_id > 0 ) {
            $where_contestant = ' AND contestant_id = %d';
            $params[]         = $contestant_id;
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sep_votes
             WHERE DATE(voted_at) = %s AND (voter_ip = %s OR voter_cookie = %s){$where_contestant}",
            array_merge( array( $today, $ip, $cookie ), $contestant_id > 0 ? array( $contestant_id ) : array() )
        );

        $limit = (int) get_option( 'sep_vote_limit_per_day', 1 );
        return (int) $wpdb->get_var( $sql ) >= $limit;
    }

    public static function cast_vote( $contestant_id ) {
        global $wpdb;

        $status = get_option( 'sep_competition_status', 'open' );
        if ( 'open' !== $status ) {
            return new WP_Error( 'closed', __( 'Voting is currently closed.', 'spectacular' ) );
        }

        $contestant = self::get_contestant( $contestant_id );
        if ( ! $contestant || 'active' !== $contestant->status ) {
            return new WP_Error( 'invalid', __( 'Invalid contestant.', 'spectacular' ) );
        }

        if ( self::has_voted_today() ) {
            return new WP_Error( 'limit', __( 'You have already voted today. Come back tomorrow!', 'spectacular' ) );
        }

        $ip     = self::get_voter_ip();
        $cookie = self::get_voter_cookie();

        $wpdb->insert(
            $wpdb->prefix . 'sep_votes',
            array(
                'contestant_id' => $contestant_id,
                'voter_ip'      => $ip,
                'voter_cookie'  => $cookie,
                'user_id'       => get_current_user_id(),
                'voted_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s' )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}sep_contestants SET votes = votes + 1 WHERE id = %d",
                $contestant_id
            )
        );

        SEP_Database::log_activity( 'vote', sprintf( 'Vote cast for %s', $contestant->name ), array(
            'contestant_id' => $contestant_id,
            'ip'            => $ip,
        ) );

        return true;
    }

    public static function ajax_cast_vote() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        $contestant_id = isset( $_POST['contestant_id'] ) ? absint( $_POST['contestant_id'] ) : 0;
        if ( ! $contestant_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid contestant.', 'spectacular' ) ) );
        }

        $result = self::cast_vote( $contestant_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $contestant = self::get_contestant( $contestant_id );
        wp_send_json_success( array(
            'message' => __( 'Vote recorded! Thank you.', 'spectacular' ),
            'votes'   => $contestant ? (int) $contestant->votes : 0,
            'total'   => self::get_total_votes(),
        ) );
    }

    public static function ajax_get_leaderboard() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        $contestants = self::get_contestants( array( 'limit' => 20 ) );
        $total       = self::get_total_votes();
        $data        = array();

        foreach ( $contestants as $c ) {
            $pct    = $total > 0 ? round( ( $c->votes / $total ) * 100, 1 ) : 0;
            $data[] = array(
                'id'        => (int) $c->id,
                'name'      => $c->name,
                'image_url' => $c->image_url,
                'votes'     => (int) $c->votes,
                'percent'   => $pct,
            );
        }

        wp_send_json_success( array(
            'contestants' => $data,
            'total_votes' => $total,
        ) );
    }

    public static function add_contestant( $data ) {
        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'sep_contestants',
            array(
                'name'       => sanitize_text_field( $data['name'] ),
                'bio'        => sanitize_textarea_field( isset( $data['bio'] ) ? $data['bio'] : '' ),
                'image_url'  => esc_url_raw( isset( $data['image_url'] ) ? $data['image_url'] : '' ),
                'votes'      => 0,
                'status'     => 'active',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( $inserted ) {
            SEP_Database::log_activity( 'contestant', sprintf( 'New contestant added: %s', $data['name'] ) );
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function update_contestant( $id, $data ) {
        global $wpdb;
        $update = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( $data['name'] );
            $format[]       = '%s';
        }
        if ( isset( $data['bio'] ) ) {
            $update['bio'] = sanitize_textarea_field( $data['bio'] );
            $format[]      = '%s';
        }
        if ( isset( $data['image_url'] ) ) {
            $update['image_url'] = esc_url_raw( $data['image_url'] );
            $format[]            = '%s';
        }
        if ( isset( $data['status'] ) ) {
            $update['status'] = sanitize_text_field( $data['status'] );
            $format[]         = '%s';
        }

        if ( empty( $update ) ) {
            return false;
        }

        return $wpdb->update(
            $wpdb->prefix . 'sep_contestants',
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
    }

    public static function delete_contestant( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'sep_votes', array( 'contestant_id' => $id ), array( '%d' ) );
        return $wpdb->delete( $wpdb->prefix . 'sep_contestants', array( 'id' => $id ), array( '%d' ) );
    }

    public static function reset_votes( $contestant_id = 0 ) {
        global $wpdb;
        if ( $contestant_id > 0 ) {
            $wpdb->delete( $wpdb->prefix . 'sep_votes', array( 'contestant_id' => $contestant_id ), array( '%d' ) );
            $wpdb->update(
                $wpdb->prefix . 'sep_contestants',
                array( 'votes' => 0 ),
                array( 'id' => $contestant_id ),
                array( '%d' ),
                array( '%d' )
            );
        } else {
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sep_votes" );
            $wpdb->update( $wpdb->prefix . 'sep_contestants', array( 'votes' => 0 ), array( 'status' => 'active' ), array( '%d' ), array( '%s' ) );
        }
        SEP_Database::log_activity( 'competition', 'Votes reset' );
    }

    private static function get_voter_ip() {
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

    private static function get_voter_cookie() {
        if ( isset( $_COOKIE['sep_voter_id'] ) ) {
            return sanitize_text_field( $_COOKIE['sep_voter_id'] );
        }
        $cookie_val = wp_generate_password( 32, false );
        setcookie( 'sep_voter_id', $cookie_val, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        return $cookie_val;
    }
}
