<?php
/**
 * WooCommerce Blocks PromptPay Integration
 * Provides PromptPay payment method for WooCommerce Blocks checkout
 * 
 * @package WooPromptPayN8N
 * @since 1.6.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add PromptPay to WooCommerce Blocks
add_action( 'wp_enqueue_scripts', 'enqueue_blocks_promptpay_script' );

function enqueue_blocks_promptpay_script() {
    if ( ! is_checkout() ) {
        return;
    }
    
    // Enqueue PromptPay script for WooCommerce Blocks checkout
    
    // Get cart total
    $total = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;
    $formatted_total = number_format( $total, 2 );
    
    ?>
    <script type="text/javascript">
    // WooCommerce Blocks PromptPay Injection
    // PromptPay WooCommerce Blocks Integration
    
    // Wait for blocks to load
    function waitForBlocks() {
        // Wait for WooCommerce Blocks to load
        
        // Check if blocks are loaded - be more flexible
        var paymentBlock = document.querySelector('.wc-block-checkout__payment-method');
        var paymentStep = document.querySelector('.wc-block-components-checkout-step__content');
        var checkoutForm = document.querySelector('.wc-block-checkout__main');
        
        // If any payment-related container is found, try to inject
        if (paymentBlock || paymentStep || checkoutForm) {
            // WooCommerce Blocks detected, inject PromptPay
            injectPromptPayBlocks();
        } else {
            // Blocks not ready, retry
            setTimeout(waitForBlocks, 1000);
        }
    }
    
    function injectPromptPayBlocks() {
        // Check if already injected
        if (document.getElementById('promptpay-blocks-injection')) {
            // Already injected, skip
            return;
        }
        
        // Find the payment methods container - try multiple selectors
        var radioControl = document.querySelector('.wc-block-components-radio-control');
        var paymentBlock = document.querySelector('.wc-block-checkout__payment-method');
        var paymentContent = document.querySelector('.wc-block-components-checkout-step__content');
        
        // Use the first available container
        var targetContainer = radioControl || paymentBlock || paymentContent;
        
        if (!targetContainer) {
            // No suitable container found, try again later
            setTimeout(injectPromptPayBlocks, 1000);
            return;
        }
        
        // Create PromptPay payment option HTML - adapt based on container type
        var isRadioControl = !!radioControl;
        var promptpayHTML = '';
        
        if (isRadioControl) {
            // Standard radio control option
            promptpayHTML = `
            <div class="wc-block-components-radio-control__option" id="promptpay-blocks-injection">
                <input type="radio" id="promptpay-blocks-radio" name="radio-control-wc-payment-method-options" value="promptpay_n8n" class="wc-block-components-radio-control__input">
                <label for="promptpay-blocks-radio" class="wc-block-components-radio-control__label">
                    <span class="wc-block-components-radio-control__label-group">
                        <span class="wc-block-components-radio-control__text">
                            <span class="wc-block-components-payment-method-label__text">PromptPay (Blocks Fix) - ฿${window.wcBlocksData?.cartTotals?.total_price || '<?php echo $formatted_total; ?>'} </span>
                        </span>
                    </span>
                </label>
                <div id="promptpay-blocks-content" class="wc-block-components-radio-control__option-layout" style="display: none; margin-top: 15px; padding: 20px; border: 2px solid #00d084; border-radius: 8px; background: #f8f9fa;">
                    ${getPromptPayContentHTML()}
                </div>
            </div>
            `;
        } else {
            // Standalone payment section when no other methods exist
            promptpayHTML = `
            <div class="promptpay-standalone-section" id="promptpay-blocks-injection" style="margin: 20px 0; padding: 20px; border: 2px solid #00d084; border-radius: 8px; background: #f8f9fa;">
                <h3 style="margin: 0 0 20px; color: #333; font-size: 18px;">💳 วิธีการชำระเงิน</h3>
                <div class="promptpay-payment-option" style="margin-bottom: 15px;">
                    <input type="radio" id="promptpay-blocks-radio" name="payment_method" value="promptpay_n8n" checked style="margin-right: 10px;">
                    <label for="promptpay-blocks-radio" style="font-weight: bold; color: #333;">PromptPay - ฿${window.wcBlocksData?.cartTotals?.total_price || '<?php echo $formatted_total; ?>'} </label>
                </div>
                <div id="promptpay-blocks-content" style="display: block;">
                    ${getPromptPayContentHTML()}
                </div>
            </div>
            `;
        }
        
        function getPromptPayContentHTML() {
            return `
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="background: white; padding: 20px; border-radius: 8px; display: inline-block; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="width: 200px; height: 200px; background: #f0f0f0; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; border-radius: 8px;">
                            <span style="color: #666; font-size: 14px;">QR Code Placeholder</span>
                        </div>
                        <p style="margin: 0; font-size: 16px; font-weight: bold; color: #333;">ยอดชำระ: ฿${window.wcBlocksData?.cartTotals?.total_price || '<?php echo $formatted_total; ?>'} </p>
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <p style="margin: 0 0 10px; font-weight: bold; color: #333;">📱 วิธีการชำระเงิน:</p>
                    <ol style="margin: 0; padding-left: 20px; color: #666; line-height: 1.6;">
                        <li>เปิดแอปธนาคารบนมือถือ</li>
                        <li>สแกน QR Code ด้านบน</li>
                        <li>ตรวจสอบยอดเงินให้ถูกต้อง</li>
                        <li>ยืนยันการโอนเงิน</li>
                        <li>อัปโหลดสลิปการโอนด้านล่าง</li>
                    </ol>
                </div>
                <div style="border: 2px dashed #00d084; border-radius: 8px; padding: 20px; background: white;">
                    <label for="promptpay-blocks-slip" style="display: block; margin-bottom: 10px; font-weight: bold; color: #333;">📎 อัปโหลดสลิปการโอนเงิน:</label>
                    <input type="file" id="promptpay-blocks-slip" accept="image/*,.pdf" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                    <p style="margin: 0; font-size: 12px; color: #666;">รองรับไฟล์: JPG, PNG, PDF (ขนาดไม่เกิน 5MB)</p>
                    <div id="promptpay-upload-status" style="margin-top: 10px;"></div>
                </div>
            `;
        }
        
        // Insert PromptPay option into the target container
        if (radioControl) {
            // If radio control exists, insert as a new option
            radioControl.insertAdjacentHTML('beforeend', promptpayHTML);
        } else {
            // If no radio control, create our own payment section
            targetContainer.insertAdjacentHTML('beforeend', promptpayHTML);
        }
        
        // PromptPay option injected successfully
        
        // Initialize event handlers
        initializeBlocksHandlers();
    }
    
    function initializeBlocksHandlers() {
        var promptpayRadio = document.getElementById('promptpay-blocks-radio');
        var promptpayContent = document.getElementById('promptpay-blocks-content');
        var slipUpload = document.getElementById('promptpay-blocks-slip');
        
        if (!promptpayRadio) {
            // Radio button not found
            return;
        }
        
        // Handle PromptPay selection
        promptpayRadio.addEventListener('change', function() {
            if (this.checked) {
                // PromptPay payment method selected
                if (promptpayContent) {
                    promptpayContent.style.display = 'block';
                }
                
                // Disable place order until slip is uploaded
                var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
                if (placeOrderBtn) {
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.textContent = 'กรุณาอัปโหลดสลิปการโอนเงิน';
                    placeOrderBtn.style.backgroundColor = '#ccc';
                }
            } else {
                if (promptpayContent) {
                    promptpayContent.style.display = 'none';
                }
                
                // Re-enable place order for other payment methods
                var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
                if (placeOrderBtn) {
                    placeOrderBtn.disabled = false;
                    placeOrderBtn.textContent = 'สั่งซื้อ';
                    placeOrderBtn.style.backgroundColor = '';
                }
            }
        });
        
        // Monitor other payment method changes
        var allRadios = document.querySelectorAll('input[name="radio-control-wc-payment-method-options"]');
        allRadios.forEach(function(radio) {
            if (radio.id !== 'promptpay-blocks-radio') {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        // Other payment method selected, enable place order
                        var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
                        if (placeOrderBtn) {
                            placeOrderBtn.disabled = false;
                            placeOrderBtn.textContent = 'สั่งซื้อ';
                            placeOrderBtn.style.backgroundColor = '';
                        }
                    }
                });
            }
        });
        
        // Handle file upload
        if (slipUpload) {
            slipUpload.addEventListener('change', function() {
                handleBlocksSlipUpload(this);
            });
        }
    }
    
    function handleBlocksSlipUpload(fileInput) {
        var statusDiv = document.getElementById('blocks-slip-status');
        
        if (!fileInput.files || !fileInput.files[0]) {
            return;
        }
        
        var file = fileInput.files[0];
        // File selected for upload
        
        // Validate file
        var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        var maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!validTypes.includes(file.type)) {
            if (statusDiv) {
                statusDiv.innerHTML = '<p style="color: #d63384; margin: 0; padding: 10px; background: #f8d7da; border-radius: 3px;">❌ ไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ JPG, PNG หรือ PDF</p>';
            }
            return;
        }
        
        if (file.size > maxSize) {
            if (statusDiv) {
                statusDiv.innerHTML = '<p style="color: #d63384; margin: 0; padding: 10px; background: #f8d7da; border-radius: 3px;">❌ ไฟล์ใหญ่เกินไป กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB</p>';
            }
            return;
        }
        
        // Show success message
        if (statusDiv) {
            statusDiv.innerHTML = '<p style="color: #198754; margin: 0; padding: 10px; background: #d1e7dd; border-radius: 3px;">✅ อัปโหลดสลิปสำเร็จ: ' + file.name + '</p>';
        }
        
        // Enable place order button
        var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
        if (placeOrderBtn) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'สั่งซื้อ';
            placeOrderBtn.style.backgroundColor = '';
        }
        
        // Slip upload successful
    }
    
    // Disable Place Order button initially for PromptPay
    function disablePlaceOrderInitially() {
        var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
        if (placeOrderBtn && !placeOrderBtn.hasAttribute('data-promptpay-controlled')) {
            placeOrderBtn.setAttribute('data-promptpay-controlled', 'true');
            placeOrderBtn.disabled = true;
            placeOrderBtn.textContent = 'กรุณาเลือกวิธีการชำระเงิน';
            placeOrderBtn.style.backgroundColor = '#ccc';
        }
    }
    
    // Start the process
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            disablePlaceOrderInitially();
            waitForBlocks();
        });
    } else {
        disablePlaceOrderInitially();
        waitForBlocks();
    }
    
    // Also try after a delay to catch late-loading blocks
    setTimeout(function() {
        disablePlaceOrderInitially();
        waitForBlocks();
    }, 2000);
    setTimeout(function() {
        disablePlaceOrderInitially();
        waitForBlocks();
    }, 5000);
    </script>
    
    <style>
    #promptpay-blocks-injection {
        animation: blocks-glow 2s ease-in-out infinite alternate;
    }
    
    @keyframes blocks-glow {
        0% { 
            border-color: #00aa44; 
            box-shadow: 0 0 5px rgba(0, 170, 68, 0.3);
        }
        100% { 
            border-color: #00dd55; 
            box-shadow: 0 0 15px rgba(0, 170, 68, 0.6);
        }
    }
    </style>
    <?php
}


