<?php
namespace WooPromptPay\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Dashboard Class
 * 
 * Handles the admin dashboard for PromptPay n8n plugin
 */
class PP_Admin_Dashboard {

    /**
     * Webhook log table name
     */
    private $webhook_log_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->webhook_log_table = $wpdb->prefix . 'ppn8n_webhook_log';
        
        // Initialize hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'admin_init' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Only show to users with manage_woocommerce capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Add main menu page
        add_menu_page(
            __( 'PromptPay n8n', 'woo-promptpay-n8n' ),
            __( 'PromptPay n8n', 'woo-promptpay-n8n' ),
            'manage_woocommerce',
            'promptpay-n8n',
            [ $this, 'dashboard_page' ],
            'dashicons-money-alt',
            56
        );

        // Add dashboard submenu
        add_submenu_page(
            'promptpay-n8n',
            __( 'Dashboard', 'woo-promptpay-n8n' ),
            __( 'Dashboard', 'woo-promptpay-n8n' ),
            'manage_woocommerce',
            'promptpay-n8n',
            [ $this, 'dashboard_page' ]
        );
    }

    /**
     * Admin init - Create webhook log table
     */
    public function admin_init() {
        $this->maybe_create_webhook_log_table();
    }

    /**
     * Create webhook log table if it doesn't exist
     */
    private function maybe_create_webhook_log_table() {
        global $wpdb;
        
        $table_name = $this->webhook_log_table;
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                order_id bigint(20) NOT NULL,
                status varchar(20) NOT NULL,
                message text,
                transaction_id varchar(100),
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY order_id (order_id),
                KEY status (status),
                KEY timestamp (timestamp)
            ) $charset_collate;";
            
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route( 'ppn8n/v1', '/dashboard-data', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_dashboard_data' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_woocommerce' );
            }
        ] );
    }

    /**
     * Dashboard page callback
     */
    public function dashboard_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'woo-promptpay-n8n' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <!-- Dashboard Stats -->
            <div class="ppn8n-stats-grid">
                <?php $this->render_dashboard_stats(); ?>
            </div>

            <!-- Dashboard Tabs -->
            <div class="ppn8n-dashboard-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#paid-orders" class="nav-tab nav-tab-active" data-tab="paid-orders">
                        <?php esc_html_e( 'Paid Orders', 'woo-promptpay-n8n' ); ?>
                    </a>
                    <a href="#pending-slips" class="nav-tab" data-tab="pending-slips">
                        <?php esc_html_e( 'Pending Slips', 'woo-promptpay-n8n' ); ?>
                    </a>
                    <a href="#webhook-events" class="nav-tab" data-tab="webhook-events">
                        <?php esc_html_e( 'Webhook Events', 'woo-promptpay-n8n' ); ?>
                    </a>
                </nav>

                <!-- Tab Content -->
                <div class="tab-content">
                    <div id="paid-orders" class="tab-pane active">
                        <?php $this->render_paid_orders_table(); ?>
                    </div>
                    <div id="pending-slips" class="tab-pane">
                        <?php $this->render_pending_slips_table(); ?>
                    </div>
                    <div id="webhook-events" class="tab-pane">
                        <?php $this->render_webhook_events_timeline(); ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .ppn8n-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .ppn8n-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        .ppn8n-stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .ppn8n-timeline-item {
            border-left: 3px solid #0073aa;
            padding-left: 15px;
            margin-bottom: 20px;
            position: relative;
        }
        .ppn8n-timeline-item.status-success { border-left-color: #46b450; }
        .ppn8n-timeline-item.status-failed { border-left-color: #dc3232; }
        .ppn8n-timeline-item.status-pending { border-left-color: #ffb900; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-pane').removeClass('active');
                $('#' + tab).addClass('active');
            });
        });
        </script>
        <?php
    }

    /**
     * Render dashboard statistics
     */
    private function render_dashboard_stats() {
        $stats = $this->get_dashboard_statistics();
        
        ?>
        <div class="ppn8n-stat-card">
            <div class="ppn8n-stat-number"><?php echo esc_html( $stats['total_paid_orders'] ); ?></div>
            <div class="ppn8n-stat-label"><?php esc_html_e( 'Total Paid Orders', 'woo-promptpay-n8n' ); ?></div>
        </div>
        
        <div class="ppn8n-stat-card">
            <div class="ppn8n-stat-number"><?php echo esc_html( $stats['pending_slips'] ); ?></div>
            <div class="ppn8n-stat-label"><?php esc_html_e( 'Pending Slips', 'woo-promptpay-n8n' ); ?></div>
        </div>
        
        <div class="ppn8n-stat-card">
            <div class="ppn8n-stat-number"><?php echo esc_html( $stats['today_payments'] ); ?></div>
            <div class="ppn8n-stat-label"><?php esc_html_e( 'Today\'s Payments', 'woo-promptpay-n8n' ); ?></div>
        </div>
        
        <div class="ppn8n-stat-card">
            <div class="ppn8n-stat-number"><?php echo wc_price( $stats['total_revenue'] ); ?></div>
            <div class="ppn8n-stat-label"><?php esc_html_e( 'Total Revenue', 'woo-promptpay-n8n' ); ?></div>
        </div>
        <?php
    }

    /**
     * Get dashboard data via REST API
     */
    public function get_dashboard_data( $request ) {
        return rest_ensure_response( [
            'success' => true,
            'data' => $this->get_dashboard_statistics()
        ] );
    }

    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        // Use HPOS-compatible functions
        $total_paid_orders = 0;
        $pending_slips = 0;
        $total_revenue = 0;
        
        // Get orders using HPOS-compatible method
        $paid_orders = wc_get_orders([
            'payment_method' => 'promptpay_n8n',
            'status' => ['processing', 'completed'],
            'limit' => -1,
            'return' => 'ids'
        ]);
        $total_paid_orders = count($paid_orders);
        
        $pending_orders = wc_get_orders([
            'payment_method' => 'promptpay_n8n',
            'status' => 'pending',
            'limit' => -1,
            'return' => 'ids'
        ]);
        $pending_slips = count($pending_orders);
        
        // Calculate total revenue
        foreach ($paid_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $total_revenue += $order->get_total();
            }
        }
        
        // Today's payments from webhook log
        global $wpdb;
        $today = date( 'Y-m-d' );
        $today_payments = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$this->webhook_log_table}
            WHERE status = 'success'
            AND DATE(timestamp) = %s
        ", $today ) ) ?: 0;
        
        return [
            'total_paid_orders' => (int) $total_paid_orders,
            'pending_slips' => (int) $pending_slips,
            'today_payments' => (int) $today_payments,
            'total_revenue' => (float) $total_revenue
        ];
    }

    /**
     * Render paid orders table
     */
    private function render_paid_orders_table() {
        $paid_orders = $this->get_paid_orders();
        
        if ( empty( $paid_orders ) ) {
            echo '<p>' . esc_html__( 'No paid orders found.', 'woo-promptpay-n8n' ) . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Order ID', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Customer', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Transaction ID', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Payment Time', 'woo-promptpay-n8n' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $paid_orders as $order_data ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_data['order_id'] . '&action=edit' ) ); ?>">
                            #<?php echo esc_html( $order_data['order_number'] ); ?>
                        </a>
                    </td>
                    <td>
                        <strong><?php echo esc_html( $order_data['customer_name'] ); ?></strong><br>
                        <small><?php echo esc_html( $order_data['customer_email'] ); ?></small>
                    </td>
                    <td><?php echo wc_price( $order_data['amount'] ); ?></td>
                    <td><code><?php echo esc_html( $order_data['transaction_id'] ); ?></code></td>
                    <td><?php echo esc_html( $order_data['payment_time'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render pending slips table
     */
    private function render_pending_slips_table() {
        $pending_slips = $this->get_pending_slips();
        
        if ( empty( $pending_slips ) ) {
            echo '<p>' . esc_html__( 'No pending slips found.', 'woo-promptpay-n8n' ) . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Order ID', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Customer', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Upload Attempts', 'woo-promptpay-n8n' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'woo-promptpay-n8n' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $pending_slips as $slip_data ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $slip_data['order_id'] . '&action=edit' ) ); ?>">
                            #<?php echo esc_html( $slip_data['order_number'] ); ?>
                        </a>
                    </td>
                    <td>
                        <strong><?php echo esc_html( $slip_data['customer_name'] ); ?></strong><br>
                        <small><?php echo esc_html( $slip_data['customer_email'] ); ?></small>
                    </td>
                    <td><?php echo wc_price( $slip_data['amount'] ); ?></td>
                    <td><?php echo esc_html( $slip_data['attempts_used'] ); ?> / <?php echo esc_html( $slip_data['max_attempts'] ); ?></td>
                    <td><?php echo esc_html( $slip_data['created_time'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render webhook events timeline
     */
    private function render_webhook_events_timeline() {
        $webhook_events = $this->get_webhook_events();
        
        if ( empty( $webhook_events ) ) {
            echo '<p>' . esc_html__( 'No webhook events found.', 'woo-promptpay-n8n' ) . '</p>';
            return;
        }
        
        foreach ( $webhook_events as $event ) :
            $status_class = 'status-' . esc_attr( $event['status'] );
            ?>
            <div class="ppn8n-timeline-item <?php echo $status_class; ?>">
                <div class="ppn8n-timeline-header">
                    <strong>Order #<?php echo esc_html( $event['order_number'] ); ?></strong>
                    <span class="ppn8n-status-<?php echo esc_attr( $event['status'] ); ?>">
                        <?php echo esc_html( ucfirst( $event['status'] ) ); ?>
                    </span>
                    <span class="ppn8n-timeline-time"><?php echo esc_html( $event['timestamp'] ); ?></span>
                </div>
                
                <?php if ( ! empty( $event['message'] ) ) : ?>
                <div class="ppn8n-timeline-message">
                    <?php echo esc_html( $event['message'] ); ?>
                </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $event['transaction_id'] ) ) : ?>
                <div class="ppn8n-timeline-transaction">
                    <strong><?php esc_html_e( 'Transaction ID:', 'woo-promptpay-n8n' ); ?></strong>
                    <code><?php echo esc_html( $event['transaction_id'] ); ?></code>
                </div>
                <?php endif; ?>
            </div>
            <?php
        endforeach;
    }

    /**
     * Get paid orders data
     */
    private function get_paid_orders( $limit = 20 ) {
        // Use HPOS-compatible WooCommerce function
        $wc_orders = wc_get_orders([
            'payment_method' => 'promptpay_n8n',
            'status' => ['processing', 'completed'],
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $orders = [];
        foreach ( $wc_orders as $order ) {
            if ( ! $order instanceof \WC_Order ) {
                continue;
            }
            
            $orders[] = [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'customer_email' => $order->get_billing_email(),
                'amount' => $order->get_total(),
                'transaction_id' => $order->get_transaction_id() ?: 'N/A',
                'payment_time' => $order->get_date_created()->date_i18n( 'F j, Y g:i A' )
            ];
        }
        
        return $orders;
    }

    /**
     * Get pending slips data
     */
    private function get_pending_slips( $limit = 20 ) {
        // Use HPOS-compatible WooCommerce function
        $wc_orders = wc_get_orders([
            'payment_method' => 'promptpay_n8n',
            'status' => 'pending',
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $slips = [];
        foreach ( $wc_orders as $order ) {
            if ( ! $order instanceof \WC_Order ) {
                continue;
            }
            
            $attempts_used = (int) $order->get_meta( '_pp_attempt_count', true );
            $max_attempts = 3; // Default from gateway settings
            
            $slips[] = [
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'customer_email' => $order->get_billing_email(),
                'amount' => $order->get_total(),
                'attempts_used' => $attempts_used,
                'max_attempts' => $max_attempts,
                'attempts_remaining' => max( 0, $max_attempts - $attempts_used ),
                'created_time' => $order->get_date_created()->date_i18n( 'F j, Y g:i A' )
            ];
        }
        
        return $slips;
    }

    /**
     * Get webhook events data
     */
    private function get_webhook_events( $limit = 20 ) {
        global $wpdb;
        
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT 
                wl.order_id,
                wl.status,
                wl.message,
                wl.transaction_id,
                wl.timestamp
            FROM {$this->webhook_log_table} wl
            ORDER BY wl.timestamp DESC
            LIMIT %d
        ", $limit ), ARRAY_A );
        
        $events = [];
        foreach ( $results as $row ) {
            $events[] = [
                'order_id' => $row['order_id'],
                'order_number' => $row['order_id'],
                'status' => $row['status'],
                'message' => $row['message'],
                'transaction_id' => $row['transaction_id'],
                'timestamp' => mysql2date( 'F j, Y g:i A', $row['timestamp'] )
            ];
        }
        
        return $events;
    }

    /**
     * Log webhook event to database
     */
    public static function log_webhook_event( $order_id, $status, $message = '', $transaction_id = '' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ppn8n_webhook_log';
        
        $wpdb->insert(
            $table_name,
            [
                'order_id' => $order_id,
                'status' => $status,
                'message' => $message,
                'transaction_id' => $transaction_id,
                'timestamp' => current_time( 'mysql' )
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );
    }
}
