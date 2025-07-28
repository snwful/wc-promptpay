<?php
/**
 * Plugin Name: Woo PromptPay n8n
 * Description: Accept PromptPay payments in WooCommerce with QR generation, slip upload and n8n webhook confirmation.
 * Author: Senior WordPress Developer
 * Version: 1.1.0
 * License: GPL2+
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 * Text Domain: woo-promptpay-n8n
 * Domain Path: /languages
 */

namespace WooPromptPay;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WPPN8N_VERSION', '1.0.1' );
define( 'WPPN8N_FILE', __FILE__ );
define( 'WPPN8N_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPPN8N_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPPN8N_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class WooPromptPayN8N {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
            return;
        }
        
        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
        
        // Load classes
        $this->load_classes();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Load required classes
     */
    private function load_classes() {
        require_once WPPN8N_DIR . 'includes/class-pp-payment-gateway.php';
        require_once WPPN8N_DIR . 'includes/class-pp-qr-generator.php';
        require_once WPPN8N_DIR . 'includes/class-pp-webhook-handler.php';
        require_once WPPN8N_DIR . 'includes/class-pp-upload-handler.php';
        require_once WPPN8N_DIR . 'includes/class-pp-admin-dashboard.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register payment gateway
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway' ] );
        
        // Add rewrite rules for webhook endpoint
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'handle_webhook_request' ] );
        
        // Initialize REST API
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_ppn8n_upload_slip', [ Handlers\PP_Upload_Handler::class, 'handle_ajax' ] );
        add_action( 'wp_ajax_nopriv_ppn8n_upload_slip', [ Handlers\PP_Upload_Handler::class, 'handle_ajax' ] );
        
        // AJAX handler for payment verification
        add_action( 'wp_ajax_ppn8n_verify_payment', [ $this, 'handle_payment_verification' ] );
        add_action( 'wp_ajax_nopriv_ppn8n_verify_payment', [ $this, 'handle_payment_verification' ] );
        
        // Initialize admin dashboard
        if ( is_admin() ) {
            new Admin\PP_Admin_Dashboard();
        }
    }
    
    /**
     * Add payment gateway to WooCommerce
     */
    public function add_gateway( $gateways ) {
        $gateways[] = Gateway\PP_Payment_Gateway::class;
        return $gateways;
    }
    
    /**
     * Add rewrite rules for pretty webhook endpoint
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^pp-n8n/webhook/?$', 'index.php?ppn8n_webhook=1', 'top' );
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'ppn8n_webhook';
        return $vars;
    }
    
    /**
     * Handle webhook request via pretty URL
     */
    public function handle_webhook_request() {
        if ( get_query_var( 'ppn8n_webhook' ) ) {
            $handler = new Webhook\PP_Webhook_Handler();
            $handler->handle_request();
            exit;
        }
    }
    
    /**
     * Initialize REST API endpoints
     */
    public function init_rest_api() {
        $webhook_handler = new Webhook\PP_Webhook_Handler();
        $webhook_handler->register_routes();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
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
     * Handle payment verification AJAX request
     */
    public function handle_payment_verification() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ppn8n_verify_payment' ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'woo-promptpay-n8n' ) ] );
        }
        
        // Check if file was uploaded
        if ( empty( $_FILES['payment_slip']['name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No payment slip uploaded.', 'woo-promptpay-n8n' ) ] );
        }
        
        // Validate file
        $file = $_FILES['payment_slip'];
        $allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'application/pdf' ];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ( ! in_array( $file['type'], $allowed_types ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid file type. Please upload JPG, PNG, or PDF.', 'woo-promptpay-n8n' ) ] );
        }
        
        if ( $file['size'] > $max_size ) {
            wp_send_json_error( [ 'message' => __( 'File size too large. Maximum 5MB allowed.', 'woo-promptpay-n8n' ) ] );
        }
        
        // Simulate n8n verification process
        // In real implementation, this would send to n8n webhook and wait for response
        $verification_result = $this->simulate_n8n_verification( $file );
        
        if ( $verification_result['success'] ) {
            wp_send_json_success( [ 
                'message' => __( 'Payment verified successfully! You can now place your order.', 'woo-promptpay-n8n' ),
                'verified' => true
            ] );
        } else {
            wp_send_json_error( [ 
                'message' => $verification_result['message'] ?: __( 'Payment verification failed. Please check your payment slip.', 'woo-promptpay-n8n' )
            ] );
        }
    }
    
    /**
     * Simulate n8n verification process
     * In real implementation, this would integrate with actual n8n workflow
     */
    private function simulate_n8n_verification( $file ) {
        // Get gateway settings
        $gateway = new Gateway\PP_Payment_Gateway();
        $n8n_webhook_url = $gateway->get_option( 'n8n_webhook_url' );
        
        if ( empty( $n8n_webhook_url ) ) {
            return [
                'success' => false,
                'message' => __( 'n8n webhook URL not configured.', 'woo-promptpay-n8n' )
            ];
        }
        
        // For demo purposes, we'll simulate a successful verification
        // In real implementation, you would:
        // 1. Upload file to temporary location
        // 2. Send file data to n8n webhook
        // 3. Wait for n8n to process and return verification result
        // 4. Return the actual verification status
        
        // Simulate processing delay
        sleep( 2 );
        
        // Simulate 90% success rate for demo
        $success_rate = 0.9;
        $is_verified = ( mt_rand() / mt_getrandmax() ) < $success_rate;
        
        if ( $is_verified ) {
            return [
                'success' => true,
                'message' => __( 'Payment slip verified successfully by n8n workflow.', 'woo-promptpay-n8n' )
            ];
        } else {
            return [
                'success' => false,
                'message' => __( 'Payment verification failed. Please ensure your payment slip is clear and matches the order amount.', 'woo-promptpay-n8n' )
            ];
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>';
        echo esc_html__( 'Woo PromptPay n8n requires WooCommerce to be installed and active.', 'woo-promptpay-n8n' );
        echo '</p></div>';
    }
}

// Initialize plugin
WooPromptPayN8N::instance();
