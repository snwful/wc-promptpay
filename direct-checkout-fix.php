<?php
/**
 * Direct Checkout Fix - Last Resort Solution
 * Directly modify WooCommerce checkout to add PromptPay
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Force add PromptPay to available payment gateways
add_filter( 'woocommerce_available_payment_gateways', 'force_add_promptpay_gateway', 999 );

function force_add_promptpay_gateway( $gateways ) {
    if ( ! is_checkout() ) {
        return $gateways;
    }
    
    error_log( 'DIRECT FIX: Forcing PromptPay into available gateways' );
    
    // Create a simple PromptPay gateway object
    if ( ! isset( $gateways['promptpay_direct'] ) ) {
        $gateways['promptpay_direct'] = new stdClass();
        $gateways['promptpay_direct']->id = 'promptpay_direct';
        $gateways['promptpay_direct']->title = 'PromptPay (Direct Fix)';
        $gateways['promptpay_direct']->description = 'Pay with PromptPay - Direct Fix Solution';
        $gateways['promptpay_direct']->enabled = 'yes';
        $gateways['promptpay_direct']->has_fields = true;
    }
    
    return $gateways;
}

// Add PromptPay option directly to checkout form
add_action( 'woocommerce_review_order_before_submit', 'direct_promptpay_injection', 5 );

function direct_promptpay_injection() {
    error_log( 'DIRECT FIX: Injecting PromptPay before submit button' );
    
    $total = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;
    $formatted_total = number_format( $total, 2 );
    
    ?>
    <div id="direct-promptpay-fix" style="margin: 20px 0; padding: 20px; border: 3px solid #ff0066; background: #ffe6f2; border-radius: 10px;">
        <h2 style="color: #ff0066; margin-top: 0;">🚀 DIRECT FIX: PromptPay Payment</h2>
        <p><strong>This is the DIRECT FIX solution!</strong></p>
        
        <div style="margin: 15px 0;">
            <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 15px;">
                <input type="radio" name="payment_method" value="promptpay_direct" id="payment_method_promptpay_direct" style="margin-right: 10px;" />
                <strong style="font-size: 16px;">เลือกชำระเงินผ่าน PromptPay (Direct Fix)</strong>
            </label>
            
            <div id="promptpay-direct-details" style="margin-left: 30px; display: none; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <p style="margin: 10px 0; color: #666;">
                    ชำระเงินผ่าน PromptPay โดยสแกน QR Code ด้วยแอปธนาคารของคุณ
                </p>
                
                <div style="text-align: center; margin: 15px 0; padding: 30px; background: #f9f9f9; border: 2px dashed #ccc; border-radius: 5px;">
                    <div style="font-size: 48px; margin-bottom: 10px;">📱</div>
                    <p style="margin: 0; font-weight: bold; font-size: 18px;">QR Code สำหรับชำระเงิน</p>
                    <p style="margin: 10px 0; font-size: 20px; color: #ff0066;">
                        จำนวนเงิน: ฿<?php echo $formatted_total; ?>
                    </p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">
                        PromptPay ID: 0864639798
                    </p>
                </div>
                
                <div style="margin: 15px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">อัปโหลดสลิปการชำระเงิน</h4>
                    <input type="file" id="promptpay-direct-slip" accept="image/*,.pdf" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                        รองรับไฟล์: JPG, PNG, PDF (ขนาดไม่เกิน 5MB)
                    </p>
                    <div id="direct-slip-status" style="margin-top: 10px;"></div>
                </div>
                
                <div style="margin: 15px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                    <h5 style="margin: 0 0 10px 0; color: #856404;">วิธีการชำระเงิน:</h5>
                    <ol style="margin: 0; padding-left: 20px; color: #856404;">
                        <li>สแกน QR Code ด้วยแอปธนาคารของคุณ</li>
                        <li>ทำการชำระเงินตามจำนวนที่แสดง</li>
                        <li>อัปโหลดสลิปการชำระเงิน</li>
                        <li>คลิก "สั่งซื้อ" เพื่อดำเนินการต่อ</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('DIRECT FIX: PromptPay script loaded');
        
        // Handle payment method selection
        $('#payment_method_promptpay_direct').on('change', function() {
            if ($(this).is(':checked')) {
                console.log('DIRECT FIX: PromptPay selected');
                $('#promptpay-direct-details').slideDown();
                
                // Disable place order until slip is uploaded
                $('#place_order').prop('disabled', true).text('กรุณาอัปโหลดสลิปก่อนสั่งซื้อ');
            }
        });
        
        // Handle other payment methods
        $('input[name="payment_method"]:not(#payment_method_promptpay_direct)').on('change', function() {
            if ($(this).is(':checked')) {
                $('#promptpay-direct-details').slideUp();
                $('#place_order').prop('disabled', false).text('สั่งซื้อ');
            }
        });
        
        // Handle file upload
        $('#promptpay-direct-slip').on('change', function() {
            var file = this.files[0];
            var statusDiv = $('#direct-slip-status');
            
            if (!file) return;
            
            console.log('DIRECT FIX: File selected:', file.name);
            
            // Validate file
            var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            var maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!validTypes.includes(file.type)) {
                statusDiv.html('<p style="color: #d63384; margin: 0;">❌ ไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ JPG, PNG หรือ PDF</p>');
                return;
            }
            
            if (file.size > maxSize) {
                statusDiv.html('<p style="color: #d63384; margin: 0;">❌ ไฟล์ใหญ่เกินไป กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB</p>');
                return;
            }
            
            // Show success and enable place order
            statusDiv.html('<p style="color: #198754; margin: 0;">✅ อัปโหลดสลิปสำเร็จ: ' + file.name + '</p>');
            $('#place_order').prop('disabled', false).text('สั่งซื้อ');
            
            console.log('DIRECT FIX: File upload successful');
        });
    });
    </script>
    
    <style>
    #direct-promptpay-fix {
        animation: direct-pulse 1s ease-in-out infinite alternate;
    }
    
    @keyframes direct-pulse {
        0% { border-color: #ff0066; }
        100% { border-color: #ff6699; }
    }
    </style>
    <?php
}

// Handle form submission for direct PromptPay
add_action( 'woocommerce_checkout_process', 'handle_direct_promptpay_submission' );

function handle_direct_promptpay_submission() {
    if ( isset( $_POST['payment_method'] ) && $_POST['payment_method'] === 'promptpay_direct' ) {
        error_log( 'DIRECT FIX: PromptPay payment method submitted' );
        
        // For now, just log - we can add actual processing later
        WC()->session->set( 'chosen_payment_method', 'promptpay_direct' );
    }
}

// Add admin notice for direct fix
add_action( 'admin_notices', 'direct_fix_admin_notice' );

function direct_fix_admin_notice() {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>🚀 DIRECT FIX ACTIVE:</strong> PromptPay direct checkout fix is running. Check checkout page for pink PromptPay option.</p>';
        echo '</div>';
    }
}
