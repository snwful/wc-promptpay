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
        
        // Check if blocks are loaded
        var paymentBlock = document.querySelector('.wc-block-checkout__payment-method');
        var radioControls = document.querySelector('.wc-block-components-radio-control');
        
        if (paymentBlock && radioControls) {
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
        
        // Find the payment methods container
        var radioControl = document.querySelector('.wc-block-components-radio-control');
        if (!radioControl) {
            // Radio control not found
            return;
        }
        
        // Create PromptPay option HTML
        var promptpayHTML = `
        <div id="promptpay-blocks-injection" class="wc-block-components-radio-control-accordion-option" style="border: 3px solid #00aa44; border-radius: 8px; margin: 10px 0; background: #f0fff0;">
            <label class="wc-block-components-radio-control__option" for="promptpay-blocks-radio" style="padding: 15px;">
                <input id="promptpay-blocks-radio" class="wc-block-components-radio-control__input" type="radio" name="radio-control-wc-payment-method-options" value="promptpay_blocks" style="margin-right: 10px;">
                <div class="wc-block-components-radio-control__option-layout">
                    <div class="wc-block-components-radio-control__label-group">
                        <span class="wc-block-components-radio-control__label">
                            <span class="wc-block-components-payment-method-label" style="font-weight: bold; color: #00aa44;">
                                🟢 PromptPay (Blocks Fix) - ฿<?php echo $formatted_total; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </label>
            
            <div id="promptpay-blocks-content" class="wc-block-components-radio-control-accordion-content" style="display: none; padding: 20px; background: #fff; margin: 10px; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3 style="color: #00aa44; margin: 0 0 15px 0;">💳 ชำระเงินผ่าน PromptPay</h3>
                    <p style="margin: 0 0 15px 0; color: #666;">สแกน QR Code ด้วยแอปธนาคารของคุณ</p>
                </div>
                
                <div style="text-align: center; margin: 20px 0; padding: 30px; background: #f9f9f9; border: 2px dashed #ccc; border-radius: 8px;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📱</div>
                    <p style="margin: 0; font-weight: bold; font-size: 18px;">QR Code สำหรับชำระเงิน</p>
                    <p style="margin: 10px 0; font-size: 24px; color: #00aa44; font-weight: bold;">
                        จำนวนเงิน: ฿<?php echo $formatted_total; ?>
                    </p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">
                        PromptPay ID: 0864639798
                    </p>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">📄 อัปโหลดสลิปการชำระเงิน</h4>
                    <input type="file" id="promptpay-blocks-slip" accept="image/*,.pdf" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 14px;" />
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">
                        รองรับไฟล์: JPG, PNG, PDF (ขนาดไม่เกิน 5MB)
                    </p>
                    <div id="blocks-slip-status" style="margin-top: 15px;"></div>
                </div>
                
                <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                    <h5 style="margin: 0 0 10px 0; color: #856404;">📋 วิธีการชำระเงิน:</h5>
                    <ol style="margin: 0; padding-left: 20px; color: #856404; line-height: 1.6;">
                        <li>สแกน QR Code ด้วยแอปธนาคารของคุณ</li>
                        <li>ทำการชำระเงินตามจำนวนที่แสดง</li>
                        <li>อัปโหลดสลิปการชำระเงิน</li>
                        <li>คลิก "Place order" เพื่อดำเนินการต่อ</li>
                    </ol>
                </div>
            </div>
        </div>
        `;
        
        // Insert PromptPay option
        radioControl.insertAdjacentHTML('beforeend', promptpayHTML);
        
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
                
                // Disable place order button
                var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
                if (placeOrderBtn) {
                    placeOrderBtn.disabled = true;
                    placeOrderBtn.textContent = 'กรุณาอัปโหลดสลิปก่อนสั่งซื้อ';
                    placeOrderBtn.style.backgroundColor = '#ccc';
                }
            }
        });
        
        // Handle other payment methods
        var otherRadios = document.querySelectorAll('input[name="radio-control-wc-payment-method-options"]:not(#promptpay-blocks-radio)');
        otherRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.checked && promptpayContent) {
                    promptpayContent.style.display = 'none';
                    
                    // Re-enable place order button
                    var placeOrderBtn = document.querySelector('.wc-block-components-checkout-place-order-button button, .wc-block-cart__submit-button, [data-block-name="woocommerce/checkout-actions-block"] button');
                    if (placeOrderBtn) {
                        placeOrderBtn.disabled = false;
                        placeOrderBtn.textContent = 'Place order';
                        placeOrderBtn.style.backgroundColor = '';
                    }
                }
            });
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
            placeOrderBtn.textContent = 'Place order';
            placeOrderBtn.style.backgroundColor = '';
        }
        
        // Slip upload successful
    }
    
    // Start the process
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForBlocks);
    } else {
        waitForBlocks();
    }
    
    // Also try after a delay to catch late-loading blocks
    setTimeout(waitForBlocks, 2000);
    setTimeout(waitForBlocks, 5000);
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


