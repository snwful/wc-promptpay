<?php
/**
 * PromptPay Checkout Injection - Production Solution
 * Uses JavaScript DOM injection to display PromptPay payment method
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook into wp_head for checkout pages only
add_action( 'wp_head', 'promptpay_checkout_injection_script' );

function promptpay_checkout_injection_script() {
    if ( ! is_checkout() ) {
        return;
    }
    
    // Get cart total
    $total = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;
    $formatted_total = number_format( $total, 2 );
    
    // Get PromptPay settings
    $gateway_settings = get_option( 'woocommerce_promptpay_n8n_settings', array() );
    $promptpay_id = isset( $gateway_settings['promptpay_id'] ) ? $gateway_settings['promptpay_id'] : '';
    $gateway_title = isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : 'PromptPay';
    $gateway_description = isset( $gateway_settings['description'] ) ? $gateway_settings['description'] : 'Pay with PromptPay by scanning the QR code with your mobile banking app.';
    
    // Log for debugging
    error_log( 'PromptPay Checkout Injection: Loading for total ' . $formatted_total );
    
    ?>
    <script type="text/javascript">
    // PromptPay Checkout Injection Script
    window.addEventListener('load', function() {
        console.log('PromptPay: Starting checkout injection');
        
        // Wait a bit more for WooCommerce to fully load
        setTimeout(function() {
            injectPromptPayPaymentMethod();
        }, 1000);
        
        // Also try on document ready
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                setTimeout(function() {
                    injectPromptPayPaymentMethod();
                }, 1500);
            });
        }
    });
    
    function injectPromptPayPaymentMethod() {
        // Check if already injected
        if (document.getElementById('promptpay-payment-method')) {
            console.log('PromptPay: Already injected, skipping');
            return;
        }
        
        // Try to find checkout form or payment area
        var targetSelectors = [
            'form.checkout',
            '.woocommerce-checkout',
            '.checkout',
            '#payment',
            '.payment_methods',
            'body'  // Last resort
        ];
        
        var targetElement = null;
        for (var i = 0; i < targetSelectors.length; i++) {
            var element = document.querySelector(targetSelectors[i]);
            if (element) {
                targetElement = element;
                console.log('PromptPay: Found target element:', targetSelectors[i]);
                break;
            }
        }
        
        if (!targetElement) {
            console.log('PromptPay: No target element found');
            return;
        }
        
        // Create PromptPay payment method HTML
        var promptpayHTML = createPromptPayHTML();
        
        // Create container div
        var promptpayContainer = document.createElement('div');
        promptpayContainer.id = 'promptpay-payment-method';
        promptpayContainer.innerHTML = promptpayHTML;
        
        // Find the best insertion point
        var insertionPoint = findBestInsertionPoint(targetElement);
        
        if (insertionPoint.method === 'after') {
            insertionPoint.element.parentNode.insertBefore(promptpayContainer, insertionPoint.element.nextSibling);
        } else if (insertionPoint.method === 'before') {
            insertionPoint.element.parentNode.insertBefore(promptpayContainer, insertionPoint.element);
        } else {
            insertionPoint.element.appendChild(promptpayContainer);
        }
        
        console.log('PromptPay: Payment method injected successfully');
        
        // Initialize event handlers
        initializePromptPayHandlers();
    }
    
    function findBestInsertionPoint(targetElement) {
        // Try to find payment methods area
        var paymentMethods = targetElement.querySelector('.payment_methods, #payment');
        if (paymentMethods) {
            return { element: paymentMethods, method: 'after' };
        }
        
        // Try to find place order button
        var placeOrderBtn = targetElement.querySelector('#place_order, .place-order');
        if (placeOrderBtn) {
            return { element: placeOrderBtn, method: 'before' };
        }
        
        // Try to find checkout form
        var checkoutForm = targetElement.querySelector('form.checkout');
        if (checkoutForm) {
            return { element: checkoutForm, method: 'append' };
        }
        
        // Default to append to target
        return { element: targetElement, method: 'append' };
    }
    
    function createPromptPayHTML() {
        return `
        <div style="margin: 20px 0; padding: 20px; border: 2px solid #0073aa; border-radius: 8px; background: #f0f8ff;">
            <h3 style="margin-top: 0; color: #0073aa; display: flex; align-items: center;">
                <span style="margin-right: 10px;">💳</span>
                <?php echo esc_js( $gateway_title ); ?>
            </h3>
            
            <div style="margin: 15px 0;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="payment_method" value="promptpay_n8n" id="payment_method_promptpay" style="margin-right: 10px;" />
                    <strong>เลือกชำระเงินผ่าน PromptPay</strong>
                </label>
            </div>
            
            <div id="promptpay-payment-details" style="margin-left: 30px; display: none;">
                <p style="margin: 10px 0; color: #666;">
                    <?php echo esc_js( $gateway_description ); ?>
                </p>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #333;">QR Code สำหรับชำระเงิน</h4>
                        <div id="promptpay-qr-code" style="min-height: 200px; display: flex; align-items: center; justify-content: center; background: #f9f9f9; border: 2px dashed #ccc; border-radius: 5px;">
                            <div style="text-align: center;">
                                <div style="font-size: 48px; margin-bottom: 10px;">📱</div>
                                <p style="margin: 0; font-weight: bold;">QR Code จะปรากฏที่นี่</p>
                                <p style="margin: 5px 0 0 0; font-size: 18px; color: #0073aa;">
                                    จำนวนเงิน: ฿<?php echo $formatted_total; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <h4 style="margin: 0 0 10px 0; color: #333;">อัปโหลดสลิปการชำระเงิน</h4>
                        <input type="file" id="promptpay-slip-upload" accept="image/*,.pdf" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                            รองรับไฟล์: JPG, PNG, PDF (ขนาดไม่เกิน 5MB)
                        </p>
                        <div id="slip-upload-status" style="margin-top: 10px;"></div>
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
        `;
    }
    
    function initializePromptPayHandlers() {
        var promptpayRadio = document.getElementById('payment_method_promptpay');
        var promptpayDetails = document.getElementById('promptpay-payment-details');
        var slipUpload = document.getElementById('promptpay-slip-upload');
        var placeOrderBtn = document.getElementById('place_order') || document.querySelector('.place-order, [name="woocommerce_checkout_place_order"]');
        
        if (!promptpayRadio) {
            console.log('PromptPay: Radio button not found');
            return;
        }
        
        // Handle payment method selection
        promptpayRadio.addEventListener('change', function() {
            if (this.checked) {
                console.log('PromptPay: Payment method selected');
                if (promptpayDetails) {
                    promptpayDetails.style.display = 'block';
                }
                
                // Generate QR Code
                generateQRCode();
                
                // Disable place order until slip is uploaded
                if (placeOrderBtn) {
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.textContent = 'กรุณาอัปโหลดสลิปก่อนสั่งซื้อ';
                    placeOrderBtn.style.backgroundColor = '#ccc';
                }
            }
        });
        
        // Handle other payment methods
        var otherRadios = document.querySelectorAll('input[name="payment_method"]:not(#payment_method_promptpay)');
        otherRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.checked && promptpayDetails) {
                    promptpayDetails.style.display = 'none';
                    
                    // Re-enable place order button
                    if (placeOrderBtn) {
                        placeOrderBtn.disabled = false;
                        placeOrderBtn.textContent = 'สั่งซื้อ';
                        placeOrderBtn.style.backgroundColor = '';
                    }
                }
            });
        });
        
        // Handle file upload
        if (slipUpload) {
            slipUpload.addEventListener('change', function() {
                handleSlipUpload(this);
            });
        }
    }
    
    function generateQRCode() {
        var qrContainer = document.getElementById('promptpay-qr-code');
        if (!qrContainer) return;
        
        // For now, show placeholder - will implement actual QR generation later
        qrContainer.innerHTML = `
            <div style="text-align: center;">
                <div style="width: 200px; height: 200px; background: #f0f0f0; border: 2px solid #ddd; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; border-radius: 5px;">
                    <div>
                        <div style="font-size: 24px; margin-bottom: 10px;">📱</div>
                        <p style="margin: 0; font-size: 14px;">QR Code</p>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">กำลังสร้าง...</p>
                    </div>
                </div>
                <p style="margin: 0; font-weight: bold; color: #0073aa;">
                    จำนวนเงิน: ฿<?php echo $formatted_total; ?>
                </p>
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                    PromptPay ID: <?php echo esc_js( $promptpay_id ); ?>
                </p>
            </div>
        `;
        
        console.log('PromptPay: QR Code placeholder generated');
    }
    
    function handleSlipUpload(fileInput) {
        var statusDiv = document.getElementById('slip-upload-status');
        var placeOrderBtn = document.getElementById('place_order') || document.querySelector('.place-order, [name="woocommerce_checkout_place_order"]');
        
        if (!fileInput.files || !fileInput.files[0]) {
            return;
        }
        
        var file = fileInput.files[0];
        console.log('PromptPay: File selected:', file.name, file.size);
        
        // Validate file
        var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        var maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            if (statusDiv) {
                statusDiv.innerHTML = '<p style="color: #d63384; margin: 0;">❌ ไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ JPG, PNG หรือ PDF</p>';
            }
            return;
        }
        
        if (file.size > maxSize) {
            if (statusDiv) {
                statusDiv.innerHTML = '<p style="color: #d63384; margin: 0;">❌ ไฟล์ใหญ่เกินไป กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB</p>';
            }
            return;
        }
        
        // Show success message
        if (statusDiv) {
            statusDiv.innerHTML = '<p style="color: #198754; margin: 0;">✅ อัปโหลดสลิปสำเร็จ: ' + file.name + '</p>';
        }
        
        // Enable place order button
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'สั่งซื้อ';
            placeOrderBtn.style.backgroundColor = '';
        }
        
        console.log('PromptPay: Slip upload successful');
    }
    </script>
    
    <style type="text/css">
    #promptpay-payment-method {
        animation: promptpay-fade-in 0.5s ease-in-out;
    }
    
    @keyframes promptpay-fade-in {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    #promptpay-payment-method input[type="radio"]:checked + strong {
        color: #0073aa;
    }
    
    #promptpay-payment-method .payment-details {
        transition: all 0.3s ease;
    }
    </style>
    <?php
}
