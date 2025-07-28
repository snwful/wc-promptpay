/**
 * PromptPay n8n Admin Dashboard JavaScript
 */

(function($) {
    'use strict';

    // Admin object
    window.ppn8nAdmin = {
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },

        // Bind events
        bindEvents: function() {
            // Tab switching
            $('.nav-tab').on('click', this.switchTab);
            
            // Search functionality
            $('#paid-orders-search').on('keypress', function(e) {
                if (e.which === 13) {
                    ppn8nAdmin.searchPaidOrders();
                }
            });
            
            $('#pending-slips-search').on('keypress', function(e) {
                if (e.which === 13) {
                    ppn8nAdmin.searchPendingSlips();
                }
            });
            
            // Filter functionality
            $('#webhook-status-filter').on('change', this.filterWebhookEvents);
            
            // Auto-refresh every 30 seconds
            setInterval(this.autoRefresh, 30000);
        },

        // Switch tabs
        switchTab: function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.tab-pane').removeClass('active');
            $('#' + tab).addClass('active');
            
            // Load tab content if needed
            ppn8nAdmin.loadTabContent(tab.replace('-', '_'));
        },

        // Search paid orders
        searchPaidOrders: function() {
            var search = $('#paid-orders-search').val();
            this.loadTabContent('paid_orders', { search: search });
        },

        // Search pending slips
        searchPendingSlips: function() {
            var search = $('#pending-slips-search').val();
            this.loadTabContent('pending_slips', { search: search });
        },

        // Filter webhook events
        filterWebhookEvents: function() {
            var status = $('#webhook-status-filter').val();
            ppn8nAdmin.loadTabContent('webhook_events', { status: status });
        },

        // Load tab content
        loadTabContent: function(section, params) {
            var containerId = section.replace('_', '-') + '-content';
            var container = $('#' + containerId);
            
            if (!container.length) {
                console.error('Container not found:', containerId);
                return;
            }
            
            // Show loading state
            this.showLoading(container);
            
            // Build URL
            var url = ppn8n_admin.rest_url + 'dashboard-data?section=' + section;
            if (params) {
                for (var key in params) {
                    if (params[key]) {
                        url += '&' + key + '=' + encodeURIComponent(params[key]);
                    }
                }
            }
            
            // Make request
            $.ajax({
                url: url,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': ppn8n_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        ppn8nAdmin.updateTabContent(section, response.data);
                    } else {
                        ppn8nAdmin.showError(container, response.message || ppn8n_admin.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    ppn8nAdmin.showError(container, ppn8n_admin.strings.error);
                }
            });
        },

        // Update tab content
        updateTabContent: function(section, data) {
            var containerId = section.replace('_', '-') + '-content';
            var container = $('#' + containerId);
            
            if (data.html) {
                container.html(data.html);
            } else {
                // Generate HTML based on data
                var html = this.generateTableHTML(section, data);
                container.html(html);
            }
        },

        // Generate table HTML
        generateTableHTML: function(section, data) {
            if (!data || data.length === 0) {
                return '<div class="ppn8n-empty">' + ppn8n_admin.strings.no_data + '</div>';
            }
            
            var html = '<table class="wp-list-table widefat fixed striped">';
            
            switch (section) {
                case 'paid_orders':
                    html += this.generatePaidOrdersTable(data);
                    break;
                case 'pending_slips':
                    html += this.generatePendingSlipsTable(data);
                    break;
                case 'webhook_events':
                    html += this.generateWebhookEventsTimeline(data);
                    break;
            }
            
            html += '</table>';
            return html;
        },

        // Generate paid orders table
        generatePaidOrdersTable: function(orders) {
            var html = '<thead><tr>';
            html += '<th>Order ID</th>';
            html += '<th>Customer</th>';
            html += '<th>Amount</th>';
            html += '<th>Transaction ID</th>';
            html += '<th>Payment Time</th>';
            html += '</tr></thead><tbody>';
            
            orders.forEach(function(order) {
                html += '<tr>';
                html += '<td><a href="' + ppn8n_admin.admin_url + 'post.php?post=' + order.order_id + '&action=edit">#' + order.order_number + '</a></td>';
                html += '<td><strong>' + order.customer_name + '</strong><br><small>' + order.customer_email + '</small></td>';
                html += '<td>' + order.amount_formatted + '</td>';
                html += '<td><code>' + (order.transaction_id || 'N/A') + '</code></td>';
                html += '<td>' + order.payment_time + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            return html;
        },

        // Generate pending slips table
        generatePendingSlipsTable: function(slips) {
            var html = '<thead><tr>';
            html += '<th>Order ID</th>';
            html += '<th>Customer</th>';
            html += '<th>Amount</th>';
            html += '<th>Upload Attempts</th>';
            html += '<th>Created</th>';
            html += '</tr></thead><tbody>';
            
            slips.forEach(function(slip) {
                html += '<tr>';
                html += '<td><a href="' + ppn8n_admin.admin_url + 'post.php?post=' + slip.order_id + '&action=edit">#' + slip.order_number + '</a></td>';
                html += '<td><strong>' + slip.customer_name + '</strong><br><small>' + slip.customer_email + '</small></td>';
                html += '<td>' + slip.amount_formatted + '</td>';
                html += '<td>' + slip.attempts_used + ' / ' + slip.max_attempts;
                if (slip.attempts_remaining <= 1) {
                    html += ' <span class="ppn8n-warning">⚠️</span>';
                }
                html += '</td>';
                html += '<td>' + slip.created_time + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            return html;
        },

        // Generate webhook events timeline
        generateWebhookEventsTimeline: function(events) {
            var html = '';
            
            events.forEach(function(event) {
                var statusClass = 'status-' + event.status;
                html += '<div class="ppn8n-timeline-item ' + statusClass + '">';
                html += '<div class="ppn8n-timeline-header">';
                html += '<strong>Order #' + event.order_number + '</strong>';
                html += '<span class="ppn8n-status-' + event.status + '">' + event.status.charAt(0).toUpperCase() + event.status.slice(1) + '</span>';
                html += '<span class="ppn8n-timeline-time">' + event.timestamp + '</span>';
                html += '</div>';
                
                if (event.message) {
                    html += '<div class="ppn8n-timeline-message">' + event.message + '</div>';
                }
                
                if (event.transaction_id) {
                    html += '<div class="ppn8n-timeline-transaction">';
                    html += '<strong>Transaction ID:</strong> <code>' + event.transaction_id + '</code>';
                    html += '</div>';
                }
                
                html += '<div class="ppn8n-timeline-meta">';
                html += '<a href="' + ppn8n_admin.admin_url + 'post.php?post=' + event.order_id + '&action=edit">View Order</a>';
                html += '</div>';
                html += '</div>';
            });
            
            return html;
        },

        // Show loading state
        showLoading: function(container) {
            container.html('<div class="ppn8n-loading">' + ppn8n_admin.strings.loading + '</div>');
        },

        // Show error state
        showError: function(container, message) {
            container.html('<div class="ppn8n-error">' + message + '</div>');
        },

        // Load initial data
        loadInitialData: function() {
            // Load active tab content
            var activeTab = $('.nav-tab-active').data('tab');
            if (activeTab) {
                this.loadTabContent(activeTab.replace('-', '_'));
            }
        },

        // Auto refresh
        autoRefresh: function() {
            // Only refresh if user is on the page and tab is visible
            if (document.hidden || !document.hasFocus()) {
                return;
            }
            
            var activeTab = $('.nav-tab-active').data('tab');
            if (activeTab) {
                ppn8nAdmin.loadTabContent(activeTab.replace('-', '_'));
            }
        },

        // Utility: Format currency
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('th-TH', {
                style: 'currency',
                currency: 'THB'
            }).format(amount);
        },

        // Utility: Format date
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('th-TH', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof ppn8n_admin !== 'undefined') {
            ppn8nAdmin.init();
        }
    });

})(jQuery);
