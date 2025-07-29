<?php
/**
 * Test file to verify WooCommerce Blocks integration
 * Access via: http://bankoffit.local/wp-content/plugins/woo-promptpay-n8n/test-blocks.php
 */

// WordPress bootstrap
require_once '../../../wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. Admin privileges required.' );
}

echo '<h1>WooCommerce Blocks PromptPay Integration Test</h1>';

// Test 1: Check if WooCommerce Blocks is available
echo '<h2>1. WooCommerce Blocks Availability</h2>';
if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    echo '✅ WooCommerce Blocks AbstractPaymentMethodType class is available<br>';
} else {
    echo '❌ WooCommerce Blocks AbstractPaymentMethodType class NOT found<br>';
}

// Test 2: Check if our Blocks integration class exists
echo '<h2>2. PromptPay Blocks Integration Class</h2>';
if ( class_exists( 'WooPromptPay\Blocks\PP_Blocks_Integration' ) ) {
    echo '✅ PP_Blocks_Integration class is available<br>';
    
    $integration = new WooPromptPay\Blocks\PP_Blocks_Integration();
    echo 'Integration Name: ' . $integration->get_name() . '<br>';
    echo 'Integration Supported Features: ' . implode( ', ', $integration->get_supported_features() ) . '<br>';
} else {
    echo '❌ PP_Blocks_Integration class NOT found<br>';
}

// Test 3: Check payment gateway registration
echo '<h2>3. Payment Gateway Registration</h2>';
$gateways = WC()->payment_gateways()->payment_gateways();
if ( isset( $gateways['promptpay_n8n'] ) ) {
    echo '✅ PromptPay gateway is registered<br>';
    $gateway = $gateways['promptpay_n8n'];
    echo 'Gateway ID: ' . $gateway->id . '<br>';
    echo 'Gateway Title: ' . $gateway->title . '<br>';
    echo 'Gateway Enabled: ' . $gateway->enabled . '<br>';
    echo 'Gateway Available: ' . ( $gateway->is_available() ? 'Yes' : 'No' ) . '<br>';
} else {
    echo '❌ PromptPay gateway NOT registered<br>';
}

// Test 4: Check available payment gateways
echo '<h2>4. Available Payment Gateways</h2>';
$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
echo 'Total available gateways: ' . count( $available_gateways ) . '<br>';
echo 'Available gateway IDs: ' . implode( ', ', array_keys( $available_gateways ) ) . '<br>';

if ( isset( $available_gateways['promptpay_n8n'] ) ) {
    echo '✅ PromptPay is in available gateways<br>';
} else {
    echo '❌ PromptPay NOT in available gateways<br>';
}

// Test 5: Check Blocks payment method registry
echo '<h2>5. Blocks Payment Method Registry</h2>';
if ( function_exists( 'woocommerce_store_api_register_payment_requirements' ) ) {
    echo '✅ WooCommerce Store API functions are available<br>';
} else {
    echo '❌ WooCommerce Store API functions NOT available<br>';
}

// Test 6: Check JavaScript file
echo '<h2>6. JavaScript Integration File</h2>';
$js_file = WPPN8N_DIR . 'assets/js/blocks-integration.js';
if ( file_exists( $js_file ) ) {
    echo '✅ blocks-integration.js file exists<br>';
    echo 'File size: ' . filesize( $js_file ) . ' bytes<br>';
    echo 'File URL: ' . WPPN8N_URL . 'assets/js/blocks-integration.js<br>';
} else {
    echo '❌ blocks-integration.js file NOT found<br>';
}

// Test 7: Debug recent logs
echo '<h2>7. Recent Debug Logs</h2>';
$log_file = WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $log_file ) ) {
    $logs = file_get_contents( $log_file );
    $recent_logs = array_slice( explode( "\n", $logs ), -20 );
    $promptpay_logs = array_filter( $recent_logs, function( $log ) {
        return strpos( $log, 'WooPromptPay' ) !== false;
    });
    
    if ( ! empty( $promptpay_logs ) ) {
        echo '✅ Recent PromptPay logs found:<br>';
        echo '<pre style="background: #f0f0f0; padding: 10px; max-height: 300px; overflow-y: auto;">';
        echo implode( "\n", array_slice( $promptpay_logs, -10 ) );
        echo '</pre>';
    } else {
        echo '⚠️ No recent PromptPay logs found<br>';
    }
} else {
    echo '❌ Debug log file not found<br>';
}

// Test 8: Plugin activation status
echo '<h2>8. Plugin Status</h2>';
if ( is_plugin_active( 'woo-promptpay-n8n/woo-promptpay-confirm.php' ) ) {
    echo '✅ Plugin is active<br>';
} else {
    echo '❌ Plugin is NOT active<br>';
}

echo '<h2>Actions</h2>';
echo '<p><a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=promptpay_n8n' ) . '">PromptPay Settings</a></p>';
echo '<p><a href="' . wc_get_checkout_url() . '">Checkout Page</a></p>';
echo '<p><a href="' . wc_get_checkout_url() . '?debug_promptpay=1">Checkout with Debug</a></p>';

?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #0073aa; }
h2 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
</style>
