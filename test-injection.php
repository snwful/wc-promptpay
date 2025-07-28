<?php
/**
 * Test Injection File - Direct PromptPay Injection
 * Add this to functions.php or load as separate plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook into checkout to inject PromptPay manually - try multiple hooks
add_action( 'woocommerce_checkout_after_order_review', 'force_promptpay_injection_test' );
add_action( 'woocommerce_checkout_after_customer_details', 'force_promptpay_injection_test_2' );
add_action( 'wp_footer', 'force_promptpay_injection_footer' );

function force_promptpay_injection_test() {
    if ( ! is_checkout() ) {
        return;
    }
    
    // Log for debugging
    error_log( 'TEST INJECTION: PromptPay injection test running at ' . current_time( 'Y-m-d H:i:s' ) );
    
    // Get cart total
    $total = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;
    
    ?>
    <div id="promptpay-test-injection" style="margin: 20px 0; padding: 20px; border: 3px solid #ff0000; border-radius: 10px; background: #ffe6e6;">
        <h2 style="color: #ff0000; margin-top: 0;">🚨 TEST: PromptPay Payment Method</h2>
        <p><strong>This is a TEST injection to verify code is loading!</strong></p>
        <p><strong>Order Total:</strong> ฿<?php echo number_format( $total, 2 ); ?></p>
        
        <div style="margin: 15px 0;">
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="payment_method" value="promptpay_test" id="payment_method_promptpay_test" />
                <strong style="font-size: 16px;">PromptPay Payment (TEST)</strong>
            </label>
            
            <div style="margin-left: 25px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                <p>Pay with PromptPay by scanning the QR code with your mobile banking app.</p>
                
                <!-- QR Code Area -->
                <div style="text-align: center; margin: 15px 0; padding: 30px; background: #f9f9f9; border: 2px dashed #ccc;">
                    <p style="font-size: 18px; margin: 0;"><strong>QR Code Area</strong></p>
                    <p style="margin: 5px 0;">Amount: ฿<?php echo number_format( $total, 2 ); ?></p>
                    <p style="font-size: 12px; color: #666;">QR code will be generated here</p>
                </div>
                
                <!-- Upload Form -->
                <div style="margin: 15px 0;">
                    <label for="promptpay_slip_test" style="display: block; margin-bottom: 5px;"><strong>Upload Payment Slip:</strong></label>
                    <input type="file" id="promptpay_slip_test" name="promptpay_slip_test" accept="image/*,.pdf" style="width: 100%; padding: 5px;" />
                    <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Upload your payment slip (JPG, PNG, or PDF, max 5MB)</p>
                </div>
                
                <div style="margin: 15px 0; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px;">
                    <p style="margin: 0; font-size: 14px;"><strong>Instructions:</strong></p>
                    <ol style="margin: 5px 0 0 20px; font-size: 14px;">
                        <li>Scan the QR code with your banking app</li>
                        <li>Complete the payment</li>
                        <li>Upload the payment slip</li>
                        <li>Click "Place Order"</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('TEST INJECTION: PromptPay test injection loaded successfully!');
        
        // Handle payment method selection
        $('#payment_method_promptpay_test').on('change', function() {
            if ($(this).is(':checked')) {
                console.log('TEST INJECTION: PromptPay test method selected');
                $('#place_order').prop('disabled', true).text('Please upload payment slip first');
            }
        });
        
        // Handle other payment methods
        $('input[name="payment_method"]:not(#payment_method_promptpay_test)').on('change', function() {
            if ($(this).is(':checked')) {
                $('#place_order').prop('disabled', false).text('Place order');
            }
        });
        
        // Handle file upload
        $('#promptpay_slip_test').on('change', function() {
            if (this.files && this.files[0]) {
                console.log('TEST INJECTION: File selected:', this.files[0].name);
                $('#place_order').prop('disabled', false).text('Place order');
            }
        });
    });
    </script>
    
    <style type="text/css">
    #promptpay-test-injection {
        animation: test-blink 1s ease-in-out infinite alternate;
    }
    
    @keyframes test-blink {
        0% { border-color: #ff0000; }
        100% { border-color: #ff6666; }
    }
    </style>
    <?php
}

// Add admin notice to confirm file is loaded
add_action( 'admin_notices', 'promptpay_test_injection_notice' );

function promptpay_test_injection_notice() {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>PromptPay Test Injection:</strong> Test file is active! Check checkout page for red test injection box.</p>';
        echo '</div>';
    }
}

// Additional test functions for different hooks
function force_promptpay_injection_test_2() {
    if ( ! is_checkout() ) {
        return;
    }
    
    error_log( 'TEST INJECTION 2: After customer details hook fired' );
    
    echo '<div style="margin: 20px 0; padding: 15px; border: 2px solid #00ff00; background: #e6ffe6; border-radius: 5px;">';
    echo '<h3 style="color: #00aa00; margin-top: 0;">✅ TEST 2: Hook after customer details works!</h3>';
    echo '<p>This proves the checkout hooks are working.</p>';
    echo '</div>';
}

function force_promptpay_injection_footer() {
    if ( ! is_checkout() ) {
        return;
    }
    
    error_log( 'TEST INJECTION 3: Footer hook fired on checkout' );
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('TEST INJECTION 3: Footer injection working!');
        
        // Try to inject PromptPay after payment options using JavaScript
        var paymentDiv = $('#payment');
        if (paymentDiv.length > 0) {
            console.log('TEST INJECTION 3: Found payment div, injecting PromptPay');
            
            var promptpayHtml = '<div id="promptpay-js-injection" style="margin: 20px 0; padding: 20px; border: 3px solid #0066cc; background: #e6f3ff; border-radius: 10px;">' +
                '<h2 style="color: #0066cc; margin-top: 0;">💙 JavaScript Injection: PromptPay Payment</h2>' +
                '<p><strong>This was injected via JavaScript!</strong></p>' +
                '<div style="margin: 15px 0;">' +
                    '<label style="display: block; margin-bottom: 10px;">' +
                        '<input type="radio" name="payment_method" value="promptpay_js" id="payment_method_promptpay_js" />' +
                        '<strong style="font-size: 16px;"> PromptPay Payment (JS Injection)</strong>' +
                    '</label>' +
                    '<div style="margin-left: 25px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">' +
                        '<p>Pay with PromptPay by scanning the QR code.</p>' +
                        '<div style="text-align: center; margin: 15px 0; padding: 30px; background: #f9f9f9; border: 2px dashed #ccc;">' +
                            '<p style="font-size: 18px; margin: 0;"><strong>QR Code Area (JS)</strong></p>' +
                            '<p style="margin: 5px 0;">Amount: ฿<?php echo WC()->cart ? number_format( WC()->cart->get_total( 'raw' ), 2 ) : '0.00'; ?></p>' +
                        '</div>' +
                        '<div style="margin: 15px 0;">' +
                            '<label style="display: block; margin-bottom: 5px;"><strong>Upload Payment Slip:</strong></label>' +
                            '<input type="file" id="promptpay_slip_js" accept="image/*,.pdf" style="width: 100%; padding: 5px;" />' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            paymentDiv.after(promptpayHtml);
            
            // Handle payment method selection
            $('#payment_method_promptpay_js').on('change', function() {
                if ($(this).is(':checked')) {
                    console.log('JS INJECTION: PromptPay JS method selected');
                    $('#place_order').prop('disabled', true).text('Please upload payment slip first');
                }
            });
            
            // Handle file upload
            $('#promptpay_slip_js').on('change', function() {
                if (this.files && this.files[0]) {
                    console.log('JS INJECTION: File selected:', this.files[0].name);
                    $('#place_order').prop('disabled', false).text('Place order');
                }
            });
        } else {
            console.log('TEST INJECTION 3: Payment div not found!');
        }
    });
    </script>
    <?php
}
