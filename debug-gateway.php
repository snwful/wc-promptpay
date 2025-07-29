<?php
/**
 * Debug helper for PromptPay Gateway
 * Add ?debug_promptpay=1 to any page URL to see debug info
 */

if ( isset( $_GET['debug_promptpay'] ) && current_user_can( 'manage_options' ) ) {
    add_action( 'wp_footer', function() {
        echo '<div style="position: fixed; top: 0; left: 0; background: #000; color: #fff; padding: 20px; z-index: 9999; max-width: 500px; font-size: 12px;">';
        echo '<h3>PromptPay Gateway Debug Info</h3>';
        
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<p style="color: red;">❌ WooCommerce is not active!</p>';
            echo '</div>';
            return;
        }
        
        echo '<p style="color: green;">✅ WooCommerce is active</p>';
        
        // Check if our plugin class exists
        if ( ! class_exists( 'WooPromptPay\Gateway\PP_Payment_Gateway' ) ) {
            echo '<p style="color: red;">❌ PromptPay Gateway class not found!</p>';
            echo '</div>';
            return;
        }
        
        echo '<p style="color: green;">✅ PromptPay Gateway class exists</p>';
        
        // Get available payment gateways
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        
        echo '<h4>Available Payment Gateways:</h4>';
        echo '<ul>';
        foreach ( $gateways as $id => $gateway ) {
            echo '<li>' . $id . ' - ' . $gateway->get_title() . '</li>';
        }
        echo '</ul>';
        
        // Check if our gateway is in the list
        if ( isset( $gateways['promptpay_n8n'] ) ) {
            echo '<p style="color: green;">✅ PromptPay Gateway is available</p>';
            
            $gateway = $gateways['promptpay_n8n'];
            echo '<h4>Gateway Settings:</h4>';
            echo '<ul>';
            echo '<li>Enabled: ' . $gateway->enabled . '</li>';
            echo '<li>Title: ' . $gateway->title . '</li>';
            echo '<li>PromptPay ID: ' . $gateway->promptpay_id . '</li>';
            echo '<li>Has Fields: ' . ( $gateway->has_fields ? 'Yes' : 'No' ) . '</li>';
            echo '</ul>';
        } else {
            echo '<p style="color: red;">❌ PromptPay Gateway is NOT available</p>';
            
            // Try to get all registered gateways (including unavailable ones)
            $all_gateways = WC()->payment_gateways()->payment_gateways();
            echo '<h4>All Registered Gateways:</h4>';
            echo '<ul>';
            foreach ( $all_gateways as $id => $gateway ) {
                $available = $gateway->is_available() ? 'Available' : 'Not Available';
                echo '<li>' . $id . ' - ' . $gateway->get_title() . ' (' . $available . ')</li>';
            }
            echo '</ul>';
        }
        
        echo '<p><small>Add ?debug_promptpay=1 to URL to see this debug info</small></p>';
        echo '</div>';
    });
}
