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
                <input type="radio" id="promptpay-blocks-radio" name="radio-control-wc-payment-method-options" value="promptpay_n8n" class="wc-block-components-radio-control__input" data-payment-method="promptpay_n8n">
                <label for="promptpay-blocks-radio" class="wc-block-components-radio-control__label">
                    <span class="wc-block-components-radio-control__label-group">
                        <span class="wc-block-components-radio-control__text">
                            <span class="wc-block-components-payment-method-label__text">💳 PromptPay - ฿${window.wcBlocksData?.cartTotals?.total_price || '<?php echo $formatted_total; ?>'}</span>
                        </span>
                    </span>
                </label>
                <div id="promptpay-blocks-content" class="wc-block-components-radio-control__option-layout promptpay-glow-box" style="display: none; margin-top: 15px; padding: 20px; border: 2px solid #F5A623; border-radius: 8px; background: #fff8f0; animation: promptpay-glow 2s ease-in-out infinite alternate;">
                    ${getPromptPayContentHTML()}
                </div>
            </div>
            `;
        } else {
            // Standalone payment section when no other methods exist
            promptpayHTML = `
            <div class="promptpay-standalone-section promptpay-glow-box" id="promptpay-blocks-injection" style="margin: 20px 0; padding: 20px; border: 2px solid #F5A623; border-radius: 8px; background: #fff8f0; animation: promptpay-glow 2s ease-in-out infinite alternate;">
                <h3 style="margin: 0 0 20px; color: #333; font-size: 18px;">💳 วิธีการชำระเงิน</h3>
                <div class="promptpay-payment-option" style="margin-bottom: 15px;">
                    <input type="radio" id="promptpay-blocks-radio" name="payment_method" value="promptpay_n8n" checked style="margin-right: 10px;" data-payment-method="promptpay_n8n">
                    <label for="promptpay-blocks-radio" style="font-weight: bold; color: #333;">PromptPay - ฿${window.wcBlocksData?.cartTotals?.total_price || '<?php echo $formatted_total; ?>'}</label>
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
                <div style="border: 2px dashed #F5A623; border-radius: 8px; padding: 20px; background: white;">
                    <label for="promptpay-blocks-slip" style="display: block; margin-bottom: 10px; font-weight: bold; color: #333;">📎 อัปโหลดสลิปการโอนเงิน:</label>
                    <input type="file" id="promptpay-blocks-slip" accept="image/*,.pdf" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
                    <button type="button" id="promptpay-upload-btn" style="width: 100%; padding: 12px; background: #F5A623; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-bottom: 10px;" disabled>📤 อัปโหลดสลิป</button>
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
                
                // Set payment method immediately when selected
                setPromptPayAsPaymentMethod();
                
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
                
                // Remove PromptPay payment method
                removePromptPayPaymentMethod();
                
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
                var uploadBtn = document.getElementById('promptpay-upload-btn');
                if (this.files && this.files.length > 0) {
                    uploadBtn.disabled = false;
                    uploadBtn.style.backgroundColor = '#F5A623';
                    uploadBtn.textContent = '📤 อัปโหลดสลิป';
                } else {
                    uploadBtn.disabled = true;
                    uploadBtn.style.backgroundColor = '#ccc';
                }
            });
        }
        
        // Handle upload button click
        var uploadBtn = document.getElementById('promptpay-upload-btn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', handleSlipUpload);
        }
    }
    
    function handleSlipUpload() {
        var fileInput = document.getElementById('promptpay-blocks-slip');
        var statusDiv = document.getElementById('promptpay-upload-status');
        var uploadBtn = document.getElementById('promptpay-upload-btn');
        
        if (!fileInput.files || fileInput.files.length === 0) {
            statusDiv.innerHTML = '<p style="color: #dc3545; margin: 0;">⚠️ กรุณาเลือกไฟล์</p>';
            return;
        }
        
        var file = fileInput.files[0];
        
        // Validate file
        var validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!validTypes.includes(file.type)) {
            statusDiv.innerHTML = '<p style="color: #dc3545; margin: 0;">⚠️ ไฟล์ต้องเป็น JPG, PNG หรือ PDF</p>';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            statusDiv.innerHTML = '<p style="color: #dc3545; margin: 0;">⚠️ ไฟล์มีขนาดใหญ่เกิน 5MB</p>';
            return;
        }
        
        // Show uploading status
        uploadBtn.disabled = true;
        uploadBtn.textContent = '🔄 กำลังอัปโหลด...';
        uploadBtn.style.backgroundColor = '#6c757d';
        statusDiv.innerHTML = '<p style="color: #007cba; margin: 0;">🔄 กำลังส่งไฟล์ไปยัง n8n...</p>';
        
        // Simulate n8n upload (replace with actual n8n call)
        setTimeout(function() {
            // Simulate successful verification
            statusDiv.innerHTML = '<p style="color: #28a745; margin: 0;">✅ อัปโหลดสำเร็จ! การชำระเงินได้รับการยืนยัน</p>';
            uploadBtn.textContent = '✅ ยืนยันแล้ว';
            uploadBtn.style.backgroundColor = '#28a745';
            
            // Change glow color to green
            var glowBox = document.querySelector('.promptpay-glow-box');
            if (glowBox) {
                glowBox.style.border = '2px solid #28a745';
                glowBox.style.background = '#f0fff0';
                glowBox.style.animation = 'promptpay-glow-success 2s ease-in-out infinite alternate';
            }
            
            // Enable place order button
            var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.textContent = 'สั่งซื้อ';
                placeOrderBtn.style.backgroundColor = '';
            }
            
            // Set payment method for WooCommerce
            var hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'payment_method';
            hiddenInput.value = 'promptpay_n8n';
            hiddenInput.id = 'promptpay-payment-method-input';
            
            // Remove existing hidden input if any
            var existing = document.getElementById('promptpay-payment-method-input');
            if (existing) existing.remove();
            
            // Add to form
            var form = document.querySelector('form.wc-block-checkout__form, form');
            if (form) form.appendChild(hiddenInput);
            
        }, 2000); // Simulate 2 second upload time
    }
    
    // Helper functions for payment method management
    function setPromptPayAsPaymentMethod() {
        // Set payment method for WooCommerce Blocks
        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'payment_method';
        hiddenInput.value = 'promptpay_n8n';
        hiddenInput.id = 'promptpay-payment-method-input';
        
        // Remove existing hidden input if any
        var existing = document.getElementById('promptpay-payment-method-input');
        if (existing) existing.remove();
        
        // Add to form
        var form = document.querySelector('form.wc-block-checkout__form, form');
        if (form) form.appendChild(hiddenInput);
        
        // Also try to set in WooCommerce Blocks data if available
        if (window.wc && window.wc.wcBlocksData) {
            window.wc.wcBlocksData.selectedPaymentMethod = 'promptpay_n8n';
        }
        
        // Dispatch custom event for WooCommerce Blocks
        var event = new CustomEvent('wc-blocks-payment-method-selected', {
            detail: { paymentMethodName: 'promptpay_n8n' }
        });
        document.dispatchEvent(event);
    }
    
    function removePromptPayPaymentMethod() {
        // Remove hidden input
        var existing = document.getElementById('promptpay-payment-method-input');
        if (existing) existing.remove();
        
        // Clear WooCommerce Blocks data if available
        if (window.wc && window.wc.wcBlocksData) {
            window.wc.wcBlocksData.selectedPaymentMethod = '';
        }
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
    /* PromptPay Glow Animations */
    @keyframes promptpay-glow {
        0% { 
            border-color: #F5A623; 
            box-shadow: 0 0 10px rgba(245, 166, 35, 0.3);
        }
        100% { 
            border-color: #FF8C00; 
            box-shadow: 0 0 20px rgba(255, 140, 0, 0.5);
        }
    }
    
    @keyframes promptpay-glow-success {
        0% { 
            border-color: #28a745; 
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
        }
        100% { 
            border-color: #00dd55; 
            box-shadow: 0 0 20px rgba(0, 221, 85, 0.5);
        }
    }
    
    /* Upload Button Styles */
    #promptpay-upload-btn {
        transition: all 0.3s ease;
    }
    
    #promptpay-upload-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    #promptpay-upload-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* PromptPay Container Responsive */
    @media (max-width: 768px) {
        .promptpay-glow-box {
            padding: 15px !important;
        }
        
        #promptpay-blocks-content div[style*="width: 200px"] {
            width: 150px !important;
            height: 150px !important;
        }
    }
    </style>
    <?php
}

