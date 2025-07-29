<?php
/**
 * Plugin Name: WooCommerce PromptPay Gateway (Standard)
 * Description: Standard WooCommerce PromptPay payment gateway with QR generation and slip upload verification.
 * Author: Senior WordPress Developer
 * Version: 2.0.0
 * License: GPL2+
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 * Text Domain: woo-promptpay-n8n
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WC_PROMPTPAY_VERSION', '2.0.0' );
define( 'WC_PROMPTPAY_FILE', __FILE__ );
define( 'WC_PROMPTPAY_BASENAME', plugin_basename( __FILE__ ) );
define( 'WC_PROMPTPAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_PROMPTPAY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the gateway
 */
add_action( 'plugins_loaded', 'wc_promptpay_init_gateway_class' );

function wc_promptpay_init_gateway_class() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wc_promptpay_missing_wc_notice' );
        return;
    }

    // Include the gateway class
    require_once WC_PROMPTPAY_DIR . 'includes/class-wc-gateway-promptpay.php';

    // Declare HPOS compatibility
    add_action( 'before_woocommerce_init', function() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    });
}

/**
 * Add the gateway to WooCommerce
 */
add_filter( 'woocommerce_payment_gateways', 'wc_promptpay_add_gateway_class' );

function wc_promptpay_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Gateway_PromptPay';
    return $gateways;
}

/**
 * WooCommerce missing notice
 */
function wc_promptpay_missing_wc_notice() {
    echo '<div class="error"><p>';
    echo esc_html__( 'WooCommerce PromptPay Gateway requires WooCommerce to be installed and active.', 'woo-promptpay-n8n' );
    echo '</p></div>';
}

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, 'wc_promptpay_activate' );

function wc_promptpay_activate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, 'wc_promptpay_deactivate' );

function wc_promptpay_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Add settings link to plugin page
 */
add_filter( 'plugin_action_links_' . WC_PROMPTPAY_BASENAME, 'wc_promptpay_plugin_action_links' );

function wc_promptpay_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=promptpay' ) . '">' . __( 'Settings', 'woo-promptpay-n8n' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * Load plugin textdomain
 */
add_action( 'init', 'wc_promptpay_load_textdomain' );

function wc_promptpay_load_textdomain() {
    load_plugin_textdomain( 'woo-promptpay-n8n', false, dirname( WC_PROMPTPAY_BASENAME ) . '/languages/' );
}
