<?php
/**
 * Plugin Name: PromptPay n8n Gateway
 * Plugin URI: https://github.com/Lumi-dev/wc-promptpay
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

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// HPOS compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

// Initialize plugin after WooCommerce is loaded
add_action( 'plugins_loaded', 'promptpay_n8n_init' );

function promptpay_n8n_init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Include payment gateway class
	include_once 'includes/class-wc-gateway-promptpay-n8n.php';

	// Add payment gateway to WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'promptpay_n8n_add_gateway' );
}

function promptpay_n8n_add_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_PromptPay_N8N';
	return $gateways;
}ay_n8n_gateway();
