<?php
/**
 * Plugin Name: Spectacular Eyewear Platform
 * Plugin URI:  https://spectacular.com
 * Description: A full digital platform for eyewear showcase, competition voting, and affiliate management.
 * Version:     1.0.0
 * Author:      Naveed
 * Author URI:  https://naveedsoft.com
 * License:     GPL-2.0+
 * Text Domain: spectacular
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SEP_VERSION', '1.0.0' );
define( 'SEP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SEP_PLUGIN_DIR . 'includes/class-sep-database.php';
require_once SEP_PLUGIN_DIR . 'includes/class-sep-competition.php';
require_once SEP_PLUGIN_DIR . 'includes/class-sep-affiliate.php';
require_once SEP_PLUGIN_DIR . 'includes/class-sep-products.php';
require_once SEP_PLUGIN_DIR . 'includes/class-sep-shortcodes.php';

if ( is_admin() ) {
    require_once SEP_PLUGIN_DIR . 'admin/class-sep-admin.php';
}

/**
 * Main plugin class.
 */
final class Spectacular_Eyewear_Platform {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, array( 'SEP_Database', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'SEP_Database', 'deactivate' ) );

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'template_redirect', array( 'SEP_Affiliate', 'track_referral_click' ) );

        SEP_Shortcodes::init();
        SEP_Competition::init_ajax();
        SEP_Affiliate::init_ajax();
        SEP_Products::init_ajax();

        if ( is_admin() ) {
            SEP_Admin::init();
        }
    }

    public function init() {
        load_plugin_textdomain( 'spectacular', false, dirname( SEP_PLUGIN_BASENAME ) . '/languages' );
    }

    public function enqueue_public_assets() {
        wp_enqueue_style(
            'sep-public',
            SEP_PLUGIN_URL . 'public/css/sep-public.css',
            array(),
            SEP_VERSION
        );
        wp_enqueue_script(
            'sep-public',
            SEP_PLUGIN_URL . 'public/js/sep-public.js',
            array( 'jquery' ),
            SEP_VERSION,
            true
        );
        wp_localize_script( 'sep-public', 'sepAjax', array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'sep_public_nonce' ),
        ) );
    }
}

Spectacular_Eyewear_Platform::instance();
