<?php
/**
 * Debug helper for PromptPay Gateway
 * 
 * Add this to your functions.php or run as a standalone script to debug gateway registration
 */

// Only run if WooCommerce is active
if ( ! function_exists( 'WC' ) ) {
    die( 'WooCommerce is not active!' );
}

// Debug function to check gateway registration
function debug_promptpay_gateway() {
    echo "<h2>PromptPay Gateway Debug Information</h2>";
    
    // Check if our gateway class exists
    echo "<h3>1. Class Existence Check</h3>";
    if ( class_exists( 'WC_Payment_Gateway_PromptPay_N8N' ) ) {
        echo "✅ WC_Payment_Gateway_PromptPay_N8N class exists<br>";
    } else {
        echo "❌ WC_Payment_Gateway_PromptPay_N8N class NOT found<br>";
    }
    
    // Check available payment gateways
    echo "<h3>2. Registered Payment Gateways</h3>";
    $gateways = WC()->payment_gateways()->payment_gateways();
    
    if ( isset( $gateways['promptpay_n8n'] ) ) {
        echo "✅ PromptPay gateway is registered<br>";
        $gateway = $gateways['promptpay_n8n'];
        echo "Gateway ID: " . $gateway->id . "<br>";
        echo "Gateway Title: " . $gateway->title . "<br>";
        echo "Gateway Enabled: " . $gateway->enabled . "<br>";
        echo "Gateway Available: " . ( $gateway->is_available() ? 'Yes' : 'No' ) . "<br>";
    } else {
        echo "❌ PromptPay gateway NOT registered<br>";
        echo "Available gateways: " . implode( ', ', array_keys( $gateways ) ) . "<br>";
    }
    
    // Check available payment gateways for checkout
    echo "<h3>3. Available Payment Gateways at Checkout</h3>";
    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    
    if ( isset( $available_gateways['promptpay_n8n'] ) ) {
        echo "✅ PromptPay gateway is available at checkout<br>";
    } else {
        echo "❌ PromptPay gateway NOT available at checkout<br>";
        echo "Available at checkout: " . implode( ', ', array_keys( $available_gateways ) ) . "<br>";
    }
    
    // Check plugin constants
    echo "<h3>4. Plugin Constants</h3>";
    if ( defined( 'PROMPTPAY_N8N_VERSION' ) ) {
        echo "✅ Plugin constants defined<br>";
        echo "Version: " . PROMPTPAY_N8N_VERSION . "<br>";
        echo "Plugin URL: " . PROMPTPAY_N8N_PLUGIN_URL . "<br>";
        echo "Plugin Path: " . PROMPTPAY_N8N_PLUGIN_PATH . "<br>";
    } else {
        echo "❌ Plugin constants NOT defined<br>";
    }
    
    // Check if main plugin class exists
    echo "<h3>5. Main Plugin Class</h3>";
    if ( class_exists( 'PromptPay_N8N_Gateway_Main' ) ) {
        echo "✅ Main plugin class exists<br>";
    } else {
        echo "❌ Main plugin class NOT found<br>";
    }
    
    // Check hooks
    echo "<h3>6. WordPress Hooks</h3>";
    global $wp_filter;
    
    if ( isset( $wp_filter['woocommerce_payment_gateways'] ) ) {
        echo "✅ woocommerce_payment_gateways hook has callbacks<br>";
        foreach ( $wp_filter['woocommerce_payment_gateways']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
                    $class_name = get_class( $callback['function'][0] );
                    $method_name = $callback['function'][1];
                    echo "- Priority $priority: $class_name::$method_name<br>";
                }
            }
        }
    } else {
        echo "❌ No woocommerce_payment_gateways hooks found<br>";
    }
}

// Run the debug function
debug_promptpay_gateway();
?>
