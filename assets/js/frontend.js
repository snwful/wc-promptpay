/**
 * PromptPay n8n Gateway Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Handle slip upload form submission
    $('#promptpay-slip-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var fileInput = $('#payment_slip');
        var submitBtn = $('#upload-slip-btn');
        var progressDiv = $('#upload-progress');
        var resultDiv = $('#upload-result');
        
        // Validate file
        if (!fileInput[0].files.length) {
            showMessage(promptpay_n8n_ajax.messages.invalid_file, 'error');
            return;
        }
        
        var file = fileInput[0].files[0];
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        var maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            showMessage(promptpay_n8n_ajax.messages.invalid_file, 'error');
            return;
        }
        
        if (file.size > maxSize) {
            showMessage(promptpay_n8n_ajax.messages.file_too_large, 'error');
            return;
        }
        
        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'promptpay_upload_slip');
        formData.append('order_id', form.find('input[name="order_id"]').val());
        formData.append('nonce', form.find('input[name="nonce"]').val());
        formData.append('payment_slip', file);
        
        // Show progress
        submitBtn.prop('disabled', true).text('กำลังอัปโหลด...');
        progressDiv.show().html('<div class="upload-spinner">กำลังอัปโหลดและส่งข้อมูลไปยังระบบตรวจสอบ...</div>');
        resultDiv.empty();
        
        // Upload file
        $.ajax({
            url: promptpay_n8n_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 60000, // 60 seconds timeout
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    form.hide();
                    
                    // Reload page after 3 seconds to show updated status
                    setTimeout(function() {
                        window.location.reload();
                    }, 3000);
                } else {
                    showMessage(response.data.message || promptpay_n8n_ajax.messages.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = promptpay_n8n_ajax.messages.error;
                
                if (status === 'timeout') {
                    errorMessage = 'การอัปโหลดใช้เวลานานเกินไป กรุณาลองใหม่อีกครั้ง';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                
                showMessage(errorMessage, 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('อัปโหลดสลิป');
                progressDiv.hide();
            }
        });
    });
    
    // File input change handler
    $('#payment_slip').on('change', function() {
        var file = this.files[0];
        var resultDiv = $('#upload-result');
        
        if (file) {
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            var maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!allowedTypes.includes(file.type)) {
                showMessage(promptpay_n8n_ajax.messages.invalid_file, 'error');
                $(this).val('');
                return;
            }
            
            if (file.size > maxSize) {
                showMessage(promptpay_n8n_ajax.messages.file_too_large, 'error');
                $(this).val('');
                return;
            }
            
            // Show file info
            var fileSize = (file.size / 1024 / 1024).toFixed(2);
            resultDiv.html('<div class="file-info" style="color: #666; font-size: 14px; margin-top: 10px;">ไฟล์ที่เลือก: ' + file.name + ' (' + fileSize + ' MB)</div>');
        }
    });
    
    // Show message function
    function showMessage(message, type) {
        var resultDiv = $('#upload-result');
        var className = type === 'success' ? 'woocommerce-message' : 'woocommerce-error';
        
        resultDiv.html('<div class="' + className + '" style="margin: 10px 0; padding: 10px; border-radius: 4px;"><p>' + message + '</p></div>');
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: resultDiv.offset().top - 100
        }, 500);
    }
    
    // Auto-refresh order status for pending orders
    if ($('.promptpay-thankyou-section').length && window.location.href.indexOf('order-received') > -1) {
        var orderStatusCheck = setInterval(function() {
            // Check if order is still in pending status
            if ($('.woocommerce-message:contains("verifying")').length) {
                // Refresh page every 30 seconds to check for status updates
                setTimeout(function() {
                    window.location.reload();
                }, 30000);
            } else {
                clearInterval(orderStatusCheck);
            }
        }, 30000);
    }
    
    // QR code click to copy functionality (if needed)
    $('.promptpay-qr-container img').on('click', function() {
        // Optional: Add functionality to copy PromptPay ID or show larger QR code
        $(this).css('transform', 'scale(1.1)');
        setTimeout(function() {
            $('.promptpay-qr-container img').css('transform', 'scale(1)');
        }, 200);
    });
    
    // Add loading animation for QR code
    $('.promptpay-qr-container img').on('load', function() {
        $(this).fadeIn(300);
    }).on('error', function() {
        $(this).attr('alt', 'QR Code could not be loaded').css('background', '#f0f0f0');
    });
});
