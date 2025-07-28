<?php
/**
 * Force Reload Test - Simple test to verify plugin is loading
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Force log a message every time this file is loaded
error_log( 'FORCE RELOAD TEST: File loaded at ' . current_time( 'Y-m-d H:i:s' ) );

// Add admin notice
add_action( 'admin_notices', 'force_reload_test_notice' );

function force_reload_test_notice() {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>🔄 FORCE RELOAD TEST:</strong> Plugin is loading! Time: ' . current_time( 'Y-m-d H:i:s' ) . '</p>';
        echo '</div>';
    }
}

// Add simple checkout test
add_action( 'wp_footer', 'force_reload_checkout_test' );

function force_reload_checkout_test() {
    if ( ! is_checkout() ) {
        return;
    }
    
    error_log( 'FORCE RELOAD TEST: Checkout page detected at ' . current_time( 'Y-m-d H:i:s' ) );
    
    ?>
    <div style="position: fixed; top: 10px; right: 10px; background: #00ff00; color: #000; padding: 10px; border-radius: 5px; z-index: 99999; font-weight: bold;">
        🟢 RELOAD TEST: <?php echo current_time( 'H:i:s' ); ?>
    </div>
    
    <script>
    console.log('FORCE RELOAD TEST: JavaScript loaded at', new Date().toLocaleTimeString());
    </script>
    <?php
}
