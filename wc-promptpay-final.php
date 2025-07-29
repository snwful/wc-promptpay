<?php
/**
 * Plugin Name: WC PromptPay Gateway Final
 * Description: Final working WooCommerce PromptPay payment gateway
 * Author: Senior WordPress Developer
 * Version: 3.0.0
 * License: GPL2+
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 * Text Domain: wc-promptpay-final
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WC_PROMPTPAY_FINAL_VERSION', '3.0.0' );
define( 'WC_PROMPTPAY_FINAL_FILE', __FILE__ );
define( 'WC_PROMPTPAY_FINAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_PROMPTPAY_FINAL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the gateway
 */
add_action( 'plugins_loaded', 'wc_promptpay_final_init', 0 );

function wc_promptpay_final_init() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>WC PromptPay Gateway requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    // Include the gateway class
    include_once WC_PROMPTPAY_FINAL_DIR . 'class-wc-promptpay-final-gateway.php';
}

/**
 * Add the gateway to WooCommerce
 */
add_filter( 'woocommerce_payment_gateways', 'wc_promptpay_final_add_gateway' );

function wc_promptpay_final_add_gateway( $gateways ) {
    $gateways[] = 'WC_PromptPay_Final_Gateway';
    return $gateways;
}

/**
 * Plugin activation
 */
register_activation_hook( __FILE__, function() {
    flush_rewrite_rules();
});

/**
 * Plugin deactivation
 */
register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
});
