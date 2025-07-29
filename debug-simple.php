<?php
/**
 * Simple debug script for PromptPay plugin
 */

// WordPress bootstrap
require_once '../../../wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied.' );
}

echo '<h1>PromptPay Plugin Debug</h1>';

// Check if plugin is active
echo '<h2>Plugin Status</h2>';
if ( is_plugin_active( 'woo-promptpay-n8n/woo-promptpay-confirm.php' ) ) {
    echo '✅ Plugin is ACTIVE<br>';
} else {
    echo '❌ Plugin is NOT active<br>';
}

// Check gateway registration
echo '<h2>Gateway Registration</h2>';
$gateways = WC()->payment_gateways()->payment_gateways();
if ( isset( $gateways['promptpay_n8n'] ) ) {
    echo '✅ PromptPay gateway is registered<br>';
    $gateway = $gateways['promptpay_n8n'];
    echo 'Gateway Enabled: ' . $gateway->enabled . '<br>';
    echo 'Gateway Available: ' . ( $gateway->is_available() ? 'Yes' : 'No' ) . '<br>';
} else {
    echo '❌ PromptPay gateway NOT registered<br>';
}

// Check WooCommerce Blocks
echo '<h2>WooCommerce Blocks</h2>';
if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    echo '✅ WooCommerce Blocks is available<br>';
} else {
    echo '❌ WooCommerce Blocks NOT available<br>';
}

// Check our Blocks integration
if ( class_exists( 'WooPromptPay\Blocks\PP_Blocks_Integration' ) ) {
    echo '✅ PromptPay Blocks integration class exists<br>';
} else {
    echo '❌ PromptPay Blocks integration class NOT found<br>';
}

// Check recent logs
echo '<h2>Recent Logs</h2>';
$log_file = WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $log_file ) ) {
    $logs = file_get_contents( $log_file );
    $lines = explode( "\n", $logs );
    $recent_lines = array_slice( $lines, -50 ); // Last 50 lines
    
    $promptpay_logs = array_filter( $recent_lines, function( $line ) {
        return strpos( $line, 'WooPromptPay' ) !== false;
    });
    
    if ( ! empty( $promptpay_logs ) ) {
        echo '✅ Recent PromptPay logs found:<br>';
        echo '<pre style="background: #f0f0f0; padding: 10px; max-height: 200px; overflow-y: auto;">';
        foreach ( array_slice( $promptpay_logs, -10 ) as $log ) {
            echo esc_html( $log ) . "\n";
        }
        echo '</pre>';
    } else {
        echo '⚠️ No recent PromptPay logs found<br>';
    }
} else {
    echo '❌ Debug log file not found<br>';
}

echo '<h2>Actions</h2>';
echo '<p><a href="' . wc_get_checkout_url() . '">Test Checkout Page</a></p>';
echo '<p><a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=promptpay_n8n' ) . '">PromptPay Settings</a></p>';

?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #0073aa; }
h2 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
</style>
