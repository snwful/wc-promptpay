jQuery(document).ready(function($) {
    'use strict';

    var paymentVerified = false;

    // Handle file selection
    $('#promptpay_slip_clean').on('change', function() {
        var file = this.files[0];
        var uploadBtn = $('#upload_slip_btn_clean');
        
        if (file) {
            // Validate file type
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (allowedTypes.indexOf(file.type) === -1) {
                alert('Invalid file type. Please upload JPG, PNG, or PDF.');
                $(this).val('');
                uploadBtn.prop('disabled', true);
                return;
            }
            
            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum 5MB allowed.');
                $(this).val('');
                uploadBtn.prop('disabled', true);
                return;
            }
            
            uploadBtn.prop('disabled', false);
        } else {
            uploadBtn.prop('disabled', true);
        }
    });

    // Handle slip upload
    $('#upload_slip_btn_clean').on('click', function(e) {
        e.preventDefault();
        
        var fileInput = $('#promptpay_slip_clean')[0];
        var file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a file first.');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'promptpay_clean_upload_slip');
        formData.append('payment_slip', file);
        formData.append('nonce', promptpay_clean_params.nonce);
        
        var statusDiv = $('#upload_status_clean');
        var uploadBtn = $(this);
        
        // Show uploading status
        uploadBtn.prop('disabled', true).text(promptpay_clean_params.messages.uploading);
        statusDiv.html('<div class="woocommerce-info">' + promptpay_clean_params.messages.verifying + '</div>');
        
        $.ajax({
            url: promptpay_clean_params.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    paymentVerified = true;
                    $('#payment_verified_clean').val('1');
                    statusDiv.html('<div class="woocommerce-message">' + response.data.message + '</div>');
                    uploadBtn.text(promptpay_clean_params.messages.verified).addClass('verified');
                    
                    // Enable place order button
                    enablePlaceOrderButton();
                } else {
                    paymentVerified = false;
                    $('#payment_verified_clean').val('0');
                    statusDiv.html('<div class="woocommerce-error">' + response.data.message + '</div>');
                    uploadBtn.prop('disabled', false).text('Upload & Verify');
                }
            },
            error: function() {
                paymentVerified = false;
                $('#payment_verified_clean').val('0');
                statusDiv.html('<div class="woocommerce-error">' + promptpay_clean_params.messages.error + '</div>');
                uploadBtn.prop('disabled', false).text('Upload & Verify');
            }
        });
    });

    // Handle payment method change
    $(document).on('change', 'input[name="payment_method"]', function() {
        if ($(this).val() === 'promptpay_clean') {
            // Disable place order until payment is verified
            if (!paymentVerified) {
                disablePlaceOrderButton();
            }
        } else {
            // Enable place order for other payment methods
            enablePlaceOrderButton();
        }
    });

    // Initial check when page loads
    if ($('input[name="payment_method"]:checked').val() === 'promptpay_clean') {
        if (!paymentVerified) {
            disablePlaceOrderButton();
        }
    }

    // Handle checkout form submission
    $(document.body).on('checkout_error', function() {
        // Re-enable upload button if checkout fails
        if ($('input[name="payment_method"]:checked').val() === 'promptpay_clean') {
            $('#upload_slip_btn_clean').prop('disabled', false);
        }
    });

    function enablePlaceOrderButton() {
        $('#place_order')
            .prop('disabled', false)
            .removeClass('disabled')
            .text($('#place_order').data('original-text') || 'Place order');
    }

    function disablePlaceOrderButton() {
        var placeOrderBtn = $('#place_order');
        
        // Store original text if not already stored
        if (!placeOrderBtn.data('original-text')) {
            placeOrderBtn.data('original-text', placeOrderBtn.text());
        }
        
        placeOrderBtn
            .prop('disabled', true)
            .addClass('disabled')
            .text('Please upload payment slip first');
    }

    // Handle checkout updates (when shipping/billing changes)
    $(document.body).on('updated_checkout', function() {
        // Reset verification status on checkout update
        if ($('input[name="payment_method"]:checked').val() === 'promptpay_clean') {
            if (!paymentVerified) {
                disablePlaceOrderButton();
            }
        }
    });

    // Debug logging
    console.log('PromptPay Clean: Checkout script loaded');
    
    // Log payment method changes for debugging
    $(document).on('change', 'input[name="payment_method"]', function() {
        console.log('PromptPay Clean: Payment method changed to:', $(this).val());
    });
});
