/**
 * PromptPay n8n Gateway Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Manual verification buttons
    $('.manual-verify').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var orderId = button.data('order-id');
        
        if (!confirm('Are you sure you want to manually approve this payment?')) {
            return;
        }
        
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'promptpay_manual_verify',
                order_id: orderId,
                nonce: $('#promptpay_admin_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showAdminMessage(response.data.message, 'success');
                    button.closest('tr').fadeOut(500);
                } else {
                    showAdminMessage(response.data.message, 'error');
                    button.prop('disabled', false).text('Approve');
                }
            },
            error: function() {
                showAdminMessage('An error occurred. Please try again.', 'error');
                button.prop('disabled', false).text('Approve');
            }
        });
    });

    // Manual rejection buttons
    $('.manual-reject').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var orderId = button.data('order-id');
        
        if (!confirm('Are you sure you want to reject this payment?')) {
            return;
        }
        
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'promptpay_manual_reject',
                order_id: orderId,
                nonce: $('#promptpay_admin_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showAdminMessage(response.data.message, 'success');
                    button.closest('tr').fadeOut(500);
                } else {
                    showAdminMessage(response.data.message, 'error');
                    button.prop('disabled', false).text('Reject');
                }
            },
            error: function() {
                showAdminMessage('An error occurred. Please try again.', 'error');
                button.prop('disabled', false).text('Reject');
            }
        });
    });

    // Show admin message
    function showAdminMessage(message, type) {
        var messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        var messageHtml = '<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        $('.wrap h1').after(messageHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 5000);
    }

    // Add nonce field for AJAX requests
    if (!$('#promptpay_admin_nonce').length) {
        $('body').append('<input type="hidden" id="promptpay_admin_nonce" value="' + promptpay_admin_nonce + '" />');
    }

    // Auto-refresh dashboard stats every 30 seconds
    if ($('.promptpay-dashboard').length) {
        setInterval(function() {
            // Only refresh if user is still on the page
            if (document.hasFocus()) {
                location.reload();
            }
        }, 30000);
    }

    // Enhance webhook events table
    $('.promptpay-webhook-events details').on('toggle', function() {
        if (this.open) {
            $(this).find('pre').css('max-height', '200px').css('overflow-y', 'auto');
        }
    });

    // Add search functionality to tables
    if ($('.wp-list-table').length) {
        addTableSearch();
    }

    function addTableSearch() {
        var searchHtml = '<div class="tablenav top"><div class="alignleft actions">' +
                        '<input type="search" id="table-search" placeholder="Search orders..." style="margin-right: 10px;" />' +
                        '<button type="button" id="search-submit" class="button">Search</button>' +
                        '<button type="button" id="search-clear" class="button">Clear</button>' +
                        '</div></div>';
        
        $('.wp-list-table').before(searchHtml);

        $('#search-submit, #table-search').on('click keyup', function(e) {
            if (e.type === 'keyup' && e.keyCode !== 13) return;
            
            var searchTerm = $('#table-search').val().toLowerCase();
            var table = $('.wp-list-table tbody');
            
            table.find('tr').each(function() {
                var row = $(this);
                var text = row.text().toLowerCase();
                
                if (text.indexOf(searchTerm) > -1 || searchTerm === '') {
                    row.show();
                } else {
                    row.hide();
                }
            });
        });

        $('#search-clear').on('click', function() {
            $('#table-search').val('');
            $('.wp-list-table tbody tr').show();
        });
    }
});
