<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Products {

    public static function init_ajax() {
        add_action( 'wp_ajax_sep_get_products', array( __CLASS__, 'ajax_get_products' ) );
        add_action( 'wp_ajax_nopriv_sep_get_products', array( __CLASS__, 'ajax_get_products' ) );
        add_action( 'wp_ajax_sep_get_product', array( __CLASS__, 'ajax_get_product' ) );
        add_action( 'wp_ajax_nopriv_sep_get_product', array( __CLASS__, 'ajax_get_product' ) );
    }

    public static function get_products( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'status'   => 'active',
            'category' => '',
            'style'    => '',
            'size'     => '',
            'search'   => '',
            'featured' => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'limit'    => 50,
            'offset'   => 0,
            'min_price' => 0,
            'max_price' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['category'] ) ) {
            $where[]  = 'category = %s';
            $params[] = $args['category'];
        }
        if ( ! empty( $args['style'] ) ) {
            $where[]  = 'style = %s';
            $params[] = $args['style'];
        }
        if ( ! empty( $args['size'] ) ) {
            $where[]  = 'size = %s';
            $params[] = $args['size'];
        }
        if ( '' !== $args['featured'] ) {
            $where[]  = 'featured = %d';
            $params[] = (int) $args['featured'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = '(name LIKE %s OR brand LIKE %s OR description LIKE %s)';
            $s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }
        if ( $args['min_price'] > 0 ) {
            $where[]  = 'price >= %f';
            $params[] = $args['min_price'];
        }
        if ( $args['max_price'] > 0 ) {
            $where[]  = 'price <= %f';
            $params[] = $args['max_price'];
        }

        $where_sql = implode( ' AND ', $where );
        $orderby   = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $sql = "SELECT * FROM {$wpdb->prefix}sep_products WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function get_product( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sep_products WHERE id = %d", $id )
        );
    }

    public static function get_product_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sep_products WHERE slug = %s", $slug )
        );
    }

    public static function get_product_count( $status = '' ) {
        global $wpdb;
        if ( $status ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sep_products WHERE status = %s", $status )
            );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sep_products" );
    }

    public static function get_categories() {
        global $wpdb;
        return $wpdb->get_col( "SELECT DISTINCT category FROM {$wpdb->prefix}sep_products WHERE category != '' AND status = 'active' ORDER BY category ASC" );
    }

    public static function get_styles() {
        global $wpdb;
        return $wpdb->get_col( "SELECT DISTINCT style FROM {$wpdb->prefix}sep_products WHERE style != '' AND status = 'active' ORDER BY style ASC" );
    }

    public static function get_sizes() {
        global $wpdb;
        return $wpdb->get_col( "SELECT DISTINCT size FROM {$wpdb->prefix}sep_products WHERE size != '' AND status = 'active' ORDER BY size ASC" );
    }

    public static function add_product( $data ) {
        global $wpdb;

        $slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $data['name'] );
        $slug = self::ensure_unique_slug( $slug );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'sep_products',
            array(
                'name'           => sanitize_text_field( $data['name'] ),
                'brand'          => sanitize_text_field( isset( $data['brand'] ) ? $data['brand'] : '' ),
                'slug'           => $slug,
                'description'    => wp_kses_post( isset( $data['description'] ) ? $data['description'] : '' ),
                'price'          => floatval( isset( $data['price'] ) ? $data['price'] : 0 ),
                'currency'       => sanitize_text_field( isset( $data['currency'] ) ? $data['currency'] : get_option( 'sep_currency', 'UGX' ) ),
                'category'       => sanitize_text_field( isset( $data['category'] ) ? $data['category'] : '' ),
                'style'          => sanitize_text_field( isset( $data['style'] ) ? $data['style'] : '' ),
                'size'           => sanitize_text_field( isset( $data['size'] ) ? $data['size'] : '' ),
                'image_url'      => esc_url_raw( isset( $data['image_url'] ) ? $data['image_url'] : '' ),
                'gallery'        => isset( $data['gallery'] ) ? wp_json_encode( $data['gallery'] ) : '[]',
                'color_variants' => isset( $data['color_variants'] ) ? wp_json_encode( $data['color_variants'] ) : '[]',
                'stock'          => absint( isset( $data['stock'] ) ? $data['stock'] : 0 ),
                'featured'       => absint( isset( $data['featured'] ) ? $data['featured'] : 0 ),
                'status'         => 'active',
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $inserted ) {
            SEP_Database::log_activity( 'product', sprintf( 'Product added: %s', $data['name'] ) );
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function update_product( $id, $data ) {
        global $wpdb;
        $update = array();
        $format = array();

        $text_fields = array( 'name', 'brand', 'slug', 'category', 'style', 'size', 'currency', 'status' );
        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $update[ $field ] = sanitize_text_field( $data[ $field ] );
                $format[]         = '%s';
            }
        }

        if ( isset( $data['description'] ) ) {
            $update['description'] = wp_kses_post( $data['description'] );
            $format[]              = '%s';
        }
        if ( isset( $data['price'] ) ) {
            $update['price'] = floatval( $data['price'] );
            $format[]        = '%f';
        }
        if ( isset( $data['image_url'] ) ) {
            $update['image_url'] = esc_url_raw( $data['image_url'] );
            $format[]            = '%s';
        }
        if ( isset( $data['gallery'] ) ) {
            $update['gallery'] = wp_json_encode( $data['gallery'] );
            $format[]          = '%s';
        }
        if ( isset( $data['color_variants'] ) ) {
            $update['color_variants'] = wp_json_encode( $data['color_variants'] );
            $format[]                 = '%s';
        }
        if ( isset( $data['stock'] ) ) {
            $update['stock'] = absint( $data['stock'] );
            $format[]        = '%d';
        }
        if ( isset( $data['featured'] ) ) {
            $update['featured'] = absint( $data['featured'] );
            $format[]           = '%d';
        }

        if ( empty( $update ) ) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'sep_products',
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        if ( false !== $result ) {
            SEP_Database::log_activity( 'product', sprintf( 'Product #%d updated', $id ) );
        }

        return $result;
    }

    public static function delete_product( $id ) {
        global $wpdb;
        SEP_Database::log_activity( 'product', sprintf( 'Product #%d deleted', $id ) );
        return $wpdb->delete( $wpdb->prefix . 'sep_products', array( 'id' => $id ), array( '%d' ) );
    }

    public static function ajax_get_products() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        $args = array(
            'status'    => 'active',
            'category'  => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
            'style'     => isset( $_POST['style'] ) ? sanitize_text_field( wp_unslash( $_POST['style'] ) ) : '',
            'size'      => isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '',
            'search'    => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
            'min_price' => isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : 0,
            'max_price' => isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : 0,
            'limit'     => 24,
            'offset'    => isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0,
        );

        $products   = self::get_products( $args );
        $categories = self::get_categories();
        $styles     = self::get_styles();
        $sizes      = self::get_sizes();

        $data = array();
        foreach ( $products as $p ) {
            $data[] = self::format_product_for_api( $p );
        }

        wp_send_json_success( array(
            'products'   => $data,
            'categories' => $categories,
            'styles'     => $styles,
            'sizes'      => $sizes,
        ) );
    }

    public static function ajax_get_product() {
        check_ajax_referer( 'sep_public_nonce', 'nonce' );

        $id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'spectacular' ) ) );
        }

        $product = self::get_product( $id );
        if ( ! $product || 'active' !== $product->status ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'spectacular' ) ) );
        }

        wp_send_json_success( array( 'product' => self::format_product_for_api( $product ) ) );
    }

    private static function format_product_for_api( $product ) {
        return array(
            'id'             => (int) $product->id,
            'name'           => $product->name,
            'brand'          => $product->brand,
            'slug'           => $product->slug,
            'description'    => $product->description,
            'price'          => (float) $product->price,
            'currency'       => $product->currency,
            'category'       => $product->category,
            'style'          => $product->style,
            'size'           => $product->size,
            'image_url'      => $product->image_url,
            'gallery'        => json_decode( $product->gallery, true ),
            'color_variants' => json_decode( $product->color_variants, true ),
            'stock'          => (int) $product->stock,
            'featured'       => (int) $product->featured,
        );
    }

    private static function ensure_unique_slug( $slug, $exclude_id = 0 ) {
        global $wpdb;
        $original = $slug;
        $counter  = 1;

        while ( true ) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}sep_products WHERE slug = %s AND id != %d",
                    $slug,
                    $exclude_id
                )
            );
            if ( ! $existing ) {
                return $slug;
            }
            $slug = $original . '-' . $counter;
            $counter++;
        }
    }
}
