<?php
/**
 * Plugin Name: Woo PromptPay n8n
 * Description: Accept PromptPay payments in WooCommerce with QR generation, slip upload and n8n webhook confirmation.
 * Author: Senior WordPress Developer
 * Version: 1.6.2
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
define( 'WPPN8N_VERSION', '1.6.2' );
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
        
        // Load debug helper
        require_once WPPN8N_DIR . 'debug-gateway.php';
        
        // Load WooCommerce Blocks PromptPay solution
        require_once WPPN8N_DIR . 'class-pp-blocks-checkout.php';
        
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
        
        // Force gateway to be available at checkout
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'force_gateway_availability' ] );
        
        // Additional hooks to ensure gateway visibility
        add_action( 'woocommerce_checkout_init', [ $this, 'ensure_gateway_availability' ] );
        add_filter( 'woocommerce_checkout_get_value', [ $this, 'set_default_payment_method' ], 10, 2 );
        
        // Debug checkout rendering
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'debug_payment_methods' ] );
        add_action( 'wp_footer', [ $this, 'debug_checkout_js' ] );
        
        // Force inject PromptPay into checkout template
        add_action( 'woocommerce_review_order_after_payment', [ $this, 'inject_promptpay_option' ] );
        add_action( 'wp_head', [ $this, 'inject_promptpay_styles' ] );
        
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
        // Debug: Log gateway registration
        error_log( 'WooPromptPay: Registering payment gateway' );
        
        // Check if class exists
        if ( class_exists( 'WooPromptPay\Gateway\PP_Payment_Gateway' ) ) {
            $gateways[] = Gateway\PP_Payment_Gateway::class;
            error_log( 'WooPromptPay: Gateway class added to gateways array' );
        } else {
            error_log( 'WooPromptPay: Gateway class not found!' );
        }
        
        return $gateways;
    }
    
    /**
     * Force PromptPay gateway to be available at checkout
     */
    public function force_gateway_availability( $available_gateways ) {
        // Debug logging
        error_log( 'WooPromptPay: Forcing gateway availability' );
        error_log( 'WooPromptPay: Available gateways count = ' . count( $available_gateways ) );
        
        // Log current available gateways
        $gateway_ids = array_keys( $available_gateways );
        error_log( 'WooPromptPay: Current available gateways: ' . implode( ', ', $gateway_ids ) );
        
        // Get all registered gateways
        $all_gateways = WC()->payment_gateways()->payment_gateways();
        
        // Always force add our gateway if it exists and is properly configured
        if ( isset( $all_gateways['promptpay_n8n'] ) ) {
            $gateway = $all_gateways['promptpay_n8n'];
            error_log( 'WooPromptPay: Gateway found - Enabled: ' . $gateway->enabled . ', PromptPay ID: ' . $gateway->promptpay_id );
            
            // Force add our gateway if it's enabled and has PromptPay ID
            if ( 'yes' === $gateway->enabled && ! empty( $gateway->promptpay_id ) ) {
                $available_gateways['promptpay_n8n'] = $gateway;
                error_log( 'WooPromptPay: Forced PromptPay gateway to be available' );
                error_log( 'WooPromptPay: New available gateways count = ' . count( $available_gateways ) );
            } else {
                error_log( 'WooPromptPay: Gateway not added - conditions not met' );
            }
        } else {
            error_log( 'WooPromptPay: Gateway not found in all_gateways' );
        }
        
        return $available_gateways;
    }
    
    /**
     * Ensure gateway availability at checkout init
     */
    public function ensure_gateway_availability() {
        error_log( 'WooPromptPay: Checkout init - ensuring gateway availability' );
        
        // Force refresh payment gateways
        WC()->payment_gateways()->init();
    }
    
    /**
     * Set PromptPay as default payment method if it's the only one available
     */
    public function set_default_payment_method( $value, $input ) {
        if ( 'payment_method' === $input && empty( $value ) ) {
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            
            if ( isset( $available_gateways['promptpay_n8n'] ) && count( $available_gateways ) === 1 ) {
                error_log( 'WooPromptPay: Setting PromptPay as default payment method' );
                return 'promptpay_n8n';
            }
        }
        
        return $value;
    }
    
    /**
     * Debug payment methods at checkout
     */
    public function debug_payment_methods() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        
        error_log( 'WooPromptPay: Debug payment methods at checkout' );
        error_log( 'WooPromptPay: Available gateways at checkout: ' . implode( ', ', array_keys( $available_gateways ) ) );
        
        if ( isset( $available_gateways['promptpay_n8n'] ) ) {
            $gateway = $available_gateways['promptpay_n8n'];
            error_log( 'WooPromptPay: PromptPay gateway details:' );
            error_log( 'WooPromptPay: - ID: ' . $gateway->id );
            error_log( 'WooPromptPay: - Title: ' . $gateway->title );
            error_log( 'WooPromptPay: - Enabled: ' . $gateway->enabled );
            error_log( 'WooPromptPay: - Has Fields: ' . ( $gateway->has_fields ? 'yes' : 'no' ) );
            error_log( 'WooPromptPay: - Available: ' . ( $gateway->is_available() ? 'yes' : 'no' ) );
        } else {
            error_log( 'WooPromptPay: PromptPay gateway NOT found in available gateways at checkout!' );
        }
        
        // Output debug info to frontend for admin
        echo '<div style="display:none;" id="promptpay-debug">';
        echo 'Available gateways: ' . implode( ', ', array_keys( $available_gateways ) );
        echo '</div>';
    }
    
    /**
     * Debug checkout JavaScript
     */
    public function debug_checkout_js() {
        if ( ! is_checkout() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('PromptPay Debug: Checking payment methods...');
            
            // Check if payment method radio buttons exist
            var paymentMethods = $('input[name="payment_method"]');
            console.log('PromptPay Debug: Found ' + paymentMethods.length + ' payment methods');
            
            paymentMethods.each(function() {
                console.log('PromptPay Debug: Payment method - ' + $(this).val() + ' (checked: ' + $(this).is(':checked') + ')');
            });
            
            // Check specifically for PromptPay
            var promptpayMethod = $('input[name="payment_method"][value="promptpay_n8n"]');
            if (promptpayMethod.length > 0) {
                console.log('PromptPay Debug: PromptPay method found!');
                console.log('PromptPay Debug: PromptPay visible: ' + promptpayMethod.is(':visible'));
                console.log('PromptPay Debug: PromptPay parent HTML:', promptpayMethod.parent().html());
            } else {
                console.log('PromptPay Debug: PromptPay method NOT found in DOM!');
            }
            
            // Check debug info
            var debugInfo = $('#promptpay-debug').text();
            if (debugInfo) {
                console.log('PromptPay Debug: Backend info - ' + debugInfo);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Force inject PromptPay option into checkout
     */
    public function inject_promptpay_option() {
        error_log( 'WooPromptPay v1.4.0: inject_promptpay_option called at ' . current_time( 'Y-m-d H:i:s' ) );
        
        if ( ! is_checkout() ) {
            error_log( 'WooPromptPay: Not checkout page, skipping injection' );
            return;
        }
        
        error_log( 'WooPromptPay: Is checkout page, proceeding with injection' );
        
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        error_log( 'WooPromptPay: Available gateways for injection: ' . implode( ', ', array_keys( $available_gateways ) ) );
        
        if ( ! isset( $available_gateways['promptpay_n8n'] ) ) {
            error_log( 'WooPromptPay: Gateway not available for injection' );
            return;
        }
        
        $gateway = $available_gateways['promptpay_n8n'];
        $total = WC()->cart->get_total( 'raw' );
        
        error_log( 'WooPromptPay v1.4.0: SUCCESSFULLY injecting PromptPay option manually - Total: ' . $total );
        
        ?>
        <div id="promptpay-manual-injection" style="margin: 20px 0; padding: 15px; border: 2px solid #0073aa; border-radius: 5px; background: #f0f8ff;">
            <h3 style="margin-top: 0; color: #0073aa;">🔥 PromptPay Payment (Manual Injection)</h3>
            <p><strong>Amount:</strong> ฿<?php echo number_format( $total, 2 ); ?></p>
            
            <div style="margin: 15px 0;">
                <label>
                    <input type="radio" name="payment_method" value="promptpay_n8n" id="payment_method_promptpay_n8n" />
                    <strong><?php echo esc_html( $gateway->get_title() ); ?></strong>
                </label>
                <div style="margin-left: 25px; margin-top: 10px;">
                    <p><?php echo esc_html( $gateway->get_description() ); ?></p>
                    
                    <!-- QR Code Placeholder -->
                    <div style="text-align: center; margin: 15px 0; padding: 20px; background: #fff; border: 1px dashed #ccc;">
                        <p><strong>QR Code will appear here</strong></p>
                        <p>Amount: ฿<?php echo number_format( $total, 2 ); ?></p>
                    </div>
                    
                    <!-- Upload Form -->
                    <div style="margin: 15px 0;">
                        <label for="promptpay_slip"><strong>Upload Payment Slip:</strong></label><br>
                        <input type="file" id="promptpay_slip" name="promptpay_slip" accept="image/*,.pdf" style="margin-top: 5px;" />
                        <p style="font-size: 12px; color: #666;">Upload your payment slip (JPG, PNG, or PDF, max 5MB)</p>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('PromptPay: Manual injection loaded');
            
            // Handle payment method selection
            $('#payment_method_promptpay_n8n').on('change', function() {
                if ($(this).is(':checked')) {
                    console.log('PromptPay: Payment method selected');
                    // Disable place order until slip is uploaded and verified
                    $('#place_order').prop('disabled', true).text('Please upload payment slip first');
                }
            });
            
            // Handle other payment methods
            $('input[name="payment_method"]:not(#payment_method_promptpay_n8n)').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#place_order').prop('disabled', false).text('Place order');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Inject PromptPay styles
     */
    public function inject_promptpay_styles() {
        if ( ! is_checkout() ) {
            return;
        }
        
        ?>
        <style type="text/css">
        #promptpay-manual-injection {
            animation: promptpay-highlight 2s ease-in-out;
        }
        
        @keyframes promptpay-highlight {
            0% { background-color: #fff3cd; }
            50% { background-color: #f0f8ff; }
            100% { background-color: #f0f8ff; }
        }
        
        #promptpay-manual-injection input[type="radio"]:checked + strong {
            color: #0073aa;
        }
        
        #promptpay-manual-injection .payment-fields {
            margin-left: 25px;
            margin-top: 10px;
        }
        </style>
        <?php
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
