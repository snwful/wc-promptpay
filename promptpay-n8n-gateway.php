<?php
/**
 * Plugin Name: PromptPay n8n Gateway
 * Plugin URI: https://github.com/snwful/wc-promptpay
 * Description: A WooCommerce payment gateway for PromptPay with n8n webhook integration for payment verification.
 * Version: 1.0.1
 * Author: Lumi-dev
 * Author URI: https://github.com/Lumi-dev
 * Text Domain: promptpay-n8n-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'PROMPTPAY_N8N_VERSION', '1.0.1' );
define( 'PROMPTPAY_N8N_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PROMPTPAY_N8N_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROMPTPAY_N8N_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class PromptPay_N8N_Gateway_Main {

    /**
     * Plugin instance
     *
     * @var PromptPay_N8N_Gateway_Main
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return PromptPay_N8N_Gateway_Main
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Check for active WooCommerce first
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load plugin files
        $this->load_files();

        // Initialize payment gateway
        $this->init_payment_gateway();

        // Initialize admin menu
        if ( is_admin() ) {
            new PromptPay_N8N_Admin_Menu();
        }

        // Initialize REST API
        new PromptPay_N8N_REST_API();

        // Add custom order statuses
        add_action( 'init', array( $this, 'register_custom_order_statuses' ) );
        add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_statuses' ) );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'promptpay-n8n-gateway', false, dirname( PROMPTPAY_N8N_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    /**
     * Load plugin files
     */
    private function load_files() {
        require_once PROMPTPAY_N8N_PLUGIN_PATH . 'includes/class-admin-menu.php';
        require_once PROMPTPAY_N8N_PLUGIN_PATH . 'includes/class-rest-api.php';
        require_once PROMPTPAY_N8N_PLUGIN_PATH . 'includes/class-qr-generator.php';
        require_once PROMPTPAY_N8N_PLUGIN_PATH . 'includes/class-ajax-handler.php';
        
        // Initialize AJAX handler
        new PromptPay_N8N_Ajax_Handler();
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }

    /**
     * Initialize payment gateway
     */
    public function init_payment_gateway() {
        // Hook into plugins_loaded to ensure WooCommerce is fully loaded
        add_action( 'plugins_loaded', 'promptpay_n8n_init_gateway_class', 11 );
    }

    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        register_post_status( 'wc-awaiting-slip', array(
            'label'                     => __( 'Awaiting Payment Slip', 'promptpay-n8n-gateway' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Awaiting Payment Slip <span class="count">(%s)</span>', 'Awaiting Payment Slip <span class="count">(%s)</span>', 'promptpay-n8n-gateway' )
        ) );

        register_post_status( 'wc-pending-verification', array(
            'label'                     => __( 'Pending Verification', 'promptpay-n8n-gateway' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Pending Verification <span class="count">(%s)</span>', 'Pending Verification <span class="count">(%s)</span>', 'promptpay-n8n-gateway' )
        ) );
    }

    /**
     * Add custom order statuses to WooCommerce
     *
     * @param array $order_statuses
     * @return array
     */
    public function add_custom_order_statuses( $order_statuses ) {
        $new_order_statuses = array();

        // Add new order statuses after pending
        foreach ( $order_statuses as $key => $status ) {
            $new_order_statuses[ $key ] = $status;

            if ( 'wc-pending' === $key ) {
                $new_order_statuses['wc-awaiting-slip'] = __( 'Awaiting Payment Slip', 'promptpay-n8n-gateway' );
                $new_order_statuses['wc-pending-verification'] = __( 'Pending Verification', 'promptpay-n8n-gateway' );
            }
        }

        return $new_order_statuses;
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if ( is_checkout() || is_order_received_page() ) {
            wp_enqueue_script( 
                'promptpay-n8n-frontend', 
                PROMPTPAY_N8N_PLUGIN_URL . 'assets/js/frontend.js', 
                array( 'jquery' ), 
                PROMPTPAY_N8N_VERSION, 
                true 
            );

            wp_localize_script( 'promptpay-n8n-frontend', 'promptpay_n8n_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'promptpay_n8n_nonce' ),
                'messages' => array(
                    'uploading' => __( 'Uploading payment slip...', 'promptpay-n8n-gateway' ),
                    'success' => __( 'Payment slip uploaded successfully! We will verify your payment shortly.', 'promptpay-n8n-gateway' ),
                    'error' => __( 'Error uploading payment slip. Please try again.', 'promptpay-n8n-gateway' ),
                    'invalid_file' => __( 'Please select a valid image file (JPG, PNG, PDF).', 'promptpay-n8n-gateway' ),
                    'file_too_large' => __( 'File size must be less than 5MB.', 'promptpay-n8n-gateway' )
                )
            ) );

            wp_enqueue_style( 
                'promptpay-n8n-frontend', 
                PROMPTPAY_N8N_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                PROMPTPAY_N8N_VERSION 
            );
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'promptpay-n8n' ) !== false ) {
            wp_enqueue_script( 
                'promptpay-n8n-admin', 
                PROMPTPAY_N8N_PLUGIN_URL . 'assets/js/admin.js', 
                array( 'jquery' ), 
                PROMPTPAY_N8N_VERSION, 
                true 
            );

            wp_enqueue_style( 
                'promptpay-n8n-admin', 
                PROMPTPAY_N8N_PLUGIN_URL . 'assets/css/admin.css', 
                array(), 
                PROMPTPAY_N8N_VERSION 
            );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-slips';
        
        if ( ! file_exists( $promptpay_dir ) ) {
            wp_mkdir_p( $promptpay_dir );
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n<Files *.php>\ndeny from all\n</Files>";
            file_put_contents( $promptpay_dir . '/.htaccess', $htaccess_content );
        }

        // Create database table for webhook logs
        $this->create_webhook_logs_table();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
    }

    /**
     * Create webhook logs table
     */
    private function create_webhook_logs_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'promptpay_webhook_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            webhook_data longtext NOT NULL,
            status varchar(20) NOT NULL,
            response_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . 
             esc_html__( 'PromptPay n8n Gateway', 'promptpay-n8n-gateway' ) . 
             '</strong>: ' . 
             esc_html__( 'WooCommerce is required for this plugin to work.', 'promptpay-n8n-gateway' ) . 
             '</p></div>';
    }
}

// Initialize the plugin
PromptPay_N8N_Gateway_Main::get_instance();

/**
 * Initialize PromptPay n8n Gateway Class
 * 
 * Load the payment gateway class once WooCommerce is loaded
 */
if ( ! function_exists( 'promptpay_n8n_init_gateway_class' ) ) {
    function promptpay_n8n_init_gateway_class() {
        // Check if WooCommerce Payment Gateway class exists
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        // Load the payment gateway class
        require_once PROMPTPAY_N8N_PLUGIN_PATH . 'includes/class-wc-payment-gateway-promptpay-n8n.php';

        // Add the gateway to WooCommerce
        add_filter( 'woocommerce_payment_gateways', 'promptpay_n8n_add_gateway_class' );
    }
}

/**
 * Add PromptPay n8n Gateway to WooCommerce
 *
 * @param array $gateways
 * @return array
 */
if ( ! function_exists( 'promptpay_n8n_add_gateway_class' ) ) {
    function promptpay_n8n_add_gateway_class( $gateways ) {
        $gateways[] = 'WC_Payment_Gateway_PromptPay_N8N';
        return $gateways;
    }
}
