jQuery(document).ready(function($) {
    'use strict';

    var paymentVerified = false;

    // Handle file selection
    $('#payment-slip-final').on('change', function() {
        var file = this.files[0];
        var uploadBtn = $('#upload-btn-final');
        
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
    $('#upload-btn-final').on('click', function(e) {
        e.preventDefault();
        
        var fileInput = $('#payment-slip-final')[0];
        var file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a file first.');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'promptpay_final_upload');
        formData.append('payment_slip', file);
        formData.append('nonce', promptpay_final_params.nonce);
        
        var statusDiv = $('#upload-status-final');
        var uploadBtn = $(this);
        
        // Show uploading status
        uploadBtn.prop('disabled', true).text('Uploading...');
        statusDiv.html('<div class="info-message">Verifying payment...</div>');
        
        $.ajax({
            url: promptpay_final_params.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    paymentVerified = true;
                    $('#payment-verified-final').val('1');
                    statusDiv.html('<div class="success-message">' + response.data.message + '</div>');
                    uploadBtn.text('Verified!').addClass('verified');
                    
                    // Enable place order button
                    enablePlaceOrderButton();
                } else {
                    paymentVerified = false;
                    $('#payment-verified-final').val('0');
                    statusDiv.html('<div class="error-message">' + response.data.message + '</div>');
                    uploadBtn.prop('disabled', false).text('Upload & Verify');
                }
            },
            error: function() {
                paymentVerified = false;
                $('#payment-verified-final').val('0');
                statusDiv.html('<div class="error-message">Upload failed. Please try again.</div>');
                uploadBtn.prop('disabled', false).text('Upload & Verify');
            }
        });
    });

    // Handle payment method change
    $(document).on('change', 'input[name="payment_method"]', function() {
        console.log('Payment method changed to:', $(this).val());
        
        if ($(this).val() === 'promptpay_final') {
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
    setTimeout(function() {
        var selectedMethod = $('input[name="payment_method"]:checked').val();
        console.log('Initial payment method:', selectedMethod);
        
        if (selectedMethod === 'promptpay_final') {
            if (!paymentVerified) {
                disablePlaceOrderButton();
            }
        }
    }, 500);

    // Handle checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('Checkout updated');
        
        setTimeout(function() {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            if (selectedMethod === 'promptpay_final' && !paymentVerified) {
                disablePlaceOrderButton();
            }
        }, 100);
    });

    function enablePlaceOrderButton() {
        var placeOrderBtn = $('#place_order');
        placeOrderBtn
            .prop('disabled', false)
            .removeClass('disabled')
            .text(placeOrderBtn.data('original-text') || 'Place order');
        
        console.log('Place order button enabled');
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
        
        console.log('Place order button disabled');
    }

    // Debug logging
    console.log('PromptPay Final: Script loaded successfully');
});
