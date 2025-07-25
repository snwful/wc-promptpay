<?php
/**
 * Plugin Name: WC PromptPay
 * Plugin URI: https://github.com/yourname/wc-promptpay
 * Description: WooCommerce PromptPay payment gateway with QR code generation and n8n webhook integration
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: wc-promptpay
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_PROMPTPAY_VERSION', '1.0.0');
define('WC_PROMPTPAY_PLUGIN_FILE', __FILE__);
define('WC_PROMPTPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PROMPTPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_PROMPTPAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WC PromptPay Class
 */
class WC_PromptPay {
    
    /**
     * The single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Main WC PromptPay Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
        
        // HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        // Blocks compatibility
        add_action('woocommerce_blocks_loaded', array($this, 'blocks_support'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Include required files
        $this->includes();
        
        // Initialize payment gateway
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        
        // Add webhook endpoint
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook'));
        
        // Add QR code download endpoint
        add_action('init', array($this, 'add_qr_download_endpoint'));
        add_action('template_redirect', array($this, 'handle_qr_download'));
    }
    
    /**
     * Include required files
     */
    public function includes() {
        include_once WC_PROMPTPAY_PLUGIN_DIR . 'includes/class-wc-promptpay-gateway.php';
        include_once WC_PROMPTPAY_PLUGIN_DIR . 'includes/class-wc-promptpay-qr-generator.php';
        include_once WC_PROMPTPAY_PLUGIN_DIR . 'includes/class-wc-promptpay-webhook.php';
        include_once WC_PROMPTPAY_PLUGIN_DIR . 'includes/class-wc-promptpay-blocks.php';
    }
    
    /**
     * Add the gateway to WooCommerce
     */
    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_PromptPay_Gateway';
        return $gateways;
    }
    
    /**
     * Add webhook endpoint
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule('^wc-promptpay/webhook/?$', 'index.php?wc_promptpay_webhook=1', 'top');
        add_rewrite_rule('^wc-promptpay/webhook/([^/]+)/?$', 'index.php?wc_promptpay_webhook=1&order_id=$matches[1]', 'top');
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'wc_promptpay_webhook';
            $vars[] = 'order_id';
            return $vars;
        });
    }
    
    /**
     * Handle webhook requests
     */
    public function handle_webhook() {
        if (get_query_var('wc_promptpay_webhook')) {
            $webhook = new WC_PromptPay_Webhook();
            $webhook->handle_request();
            exit;
        }
    }
    
    /**
     * Add QR code download endpoint
     */
    public function add_qr_download_endpoint() {
        add_rewrite_rule('^wc-promptpay/qr/([^/]+)/?$', 'index.php?wc_promptpay_qr=1&order_id=$matches[1]', 'top');
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'wc_promptpay_qr';
            return $vars;
        });
    }
    
    /**
     * Handle QR code download
     */
    public function handle_qr_download() {
        if (get_query_var('wc_promptpay_qr')) {
            $order_id = get_query_var('order_id');
            if ($order_id) {
                $qr_generator = new WC_PromptPay_QR_Generator();
                $qr_generator->download_qr_code($order_id);
            }
            exit;
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-promptpay', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    /**
     * Initialize Blocks support
     */
    public function blocks_support() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function($payment_method_registry) {
                    $payment_method_registry->register(new WC_PromptPay_Blocks());
                }
            );
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . sprintf(esc_html__('WC PromptPay requires WooCommerce to be installed and active. You can download %s here.', 'wc-promptpay'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
    }
}

/**
 * Main instance of WC PromptPay
 */
function WC_PromptPay() {
    return WC_PromptPay::instance();
}

// Global for backwards compatibility
$GLOBALS['wc_promptpay'] = WC_PromptPay();

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
});
