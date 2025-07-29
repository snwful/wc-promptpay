<?php
/**
 * Admin Menu for PromptPay n8n Gateway
 *
 * @package PromptPay_N8N_Gateway
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Menu Class
 */
class PromptPay_N8N_Admin_Menu {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_promptpay_manual_verify', array( $this, 'handle_manual_verification' ) );
        add_action( 'wp_ajax_promptpay_manual_reject', array( $this, 'handle_manual_rejection' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'PromptPay n8n', 'promptpay-n8n-gateway' ),
            __( 'PromptPay n8n', 'promptpay-n8n-gateway' ),
            'manage_woocommerce',
            'promptpay-n8n',
            array( $this, 'admin_page' ),
            'dashicons-money-alt',
            56
        );
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'PromptPay n8n Gateway', 'promptpay-n8n-gateway' ) . '</h1>';
        
        // Tabs
        echo '<nav class="nav-tab-wrapper">';
        $tabs = array(
            'dashboard' => __( 'Dashboard', 'promptpay-n8n-gateway' ),
            'paid-orders' => __( 'Paid Orders', 'promptpay-n8n-gateway' ),
            'pending-slips' => __( 'Pending Slips', 'promptpay-n8n-gateway' ),
            'webhook-events' => __( 'Webhook Events', 'promptpay-n8n-gateway' )
        );
        
        foreach ( $tabs as $tab_key => $tab_name ) {
            $active_class = $active_tab === $tab_key ? 'nav-tab-active' : '';
            echo '<a href="?page=promptpay-n8n&tab=' . esc_attr( $tab_key ) . '" class="nav-tab ' . esc_attr( $active_class ) . '">' . esc_html( $tab_name ) . '</a>';
        }
        echo '</nav>';
        
        // Tab content
        switch ( $active_tab ) {
            case 'dashboard':
                $this->render_dashboard_tab();
                break;
            case 'paid-orders':
                $this->render_paid_orders_tab();
                break;
            case 'pending-slips':
                $this->render_pending_slips_tab();
                break;
            case 'webhook-events':
                $this->render_webhook_events_tab();
                break;
        }
        
        echo '</div>';
    }

    /**
     * Render dashboard tab
     */
    private function render_dashboard_tab() {
        $stats = $this->get_dashboard_stats();
        
        echo '<div class="promptpay-dashboard" style="margin-top: 20px;">';
        
        // Stats boxes
        echo '<div class="promptpay-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">';
        
        // Total Paid Orders
        echo '<div class="promptpay-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center;">';
        echo '<div class="stat-number" style="font-size: 2.5em; font-weight: bold; color: #135e96; margin-bottom: 10px;">' . esc_html( $stats['total_paid_orders'] ) . '</div>';
        echo '<div class="stat-label" style="color: #666; font-size: 14px;">' . esc_html__( 'Total Paid Orders', 'promptpay-n8n-gateway' ) . '</div>';
        echo '</div>';
        
        // Pending Slips
        echo '<div class="promptpay-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center;">';
        echo '<div class="stat-number" style="font-size: 2.5em; font-weight: bold; color: #d63638; margin-bottom: 10px;">' . esc_html( $stats['pending_slips'] ) . '</div>';
        echo '<div class="stat-label" style="color: #666; font-size: 14px;">' . esc_html__( 'Pending Slips', 'promptpay-n8n-gateway' ) . '</div>';
        echo '</div>';
        
        // Today's Payments
        echo '<div class="promptpay-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center;">';
        echo '<div class="stat-number" style="font-size: 2.5em; font-weight: bold; color: #00a32a; margin-bottom: 10px;">' . esc_html( $stats['todays_payments'] ) . '</div>';
        echo '<div class="stat-label" style="color: #666; font-size: 14px;">' . esc_html__( "Today's Payments", 'promptpay-n8n-gateway' ) . '</div>';
        echo '</div>';
        
        // Total Revenue
        echo '<div class="promptpay-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center;">';
        echo '<div class="stat-number" style="font-size: 2.5em; font-weight: bold; color: #f56e28; margin-bottom: 10px;">฿' . esc_html( number_format( $stats['total_revenue'], 2 ) ) . '</div>';
        echo '<div class="stat-label" style="color: #666; font-size: 14px;">' . esc_html__( 'Total Revenue', 'promptpay-n8n-gateway' ) . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Recent activity
        echo '<div class="promptpay-recent-activity" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<h3>' . esc_html__( 'Recent Activity', 'promptpay-n8n-gateway' ) . '</h3>';
        
        $recent_orders = $this->get_recent_orders( 5 );
        if ( $recent_orders ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Order', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Customer', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Amount', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'promptpay-n8n-gateway' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $recent_orders as $order ) {
                echo '<tr>';
                echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
                echo '<td>' . esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $order->get_total() ) ) . '</td>';
                echo '<td><span class="order-status status-' . esc_attr( $order->get_status() ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span></td>';
                echo '<td>' . esc_html( $order->get_date_created()->format( 'Y-m-d H:i' ) ) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No recent orders found.', 'promptpay-n8n-gateway' ) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render paid orders tab
     */
    private function render_paid_orders_tab() {
        $orders = $this->get_paid_orders();
        
        echo '<div class="promptpay-paid-orders" style="margin-top: 20px;">';
        echo '<h3>' . esc_html__( 'Paid Orders', 'promptpay-n8n-gateway' ) . '</h3>';
        
        if ( $orders ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Order ID', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Customer', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Amount', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Transaction ID', 'promptpay-n8n-gateway' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $orders as $order ) {
                echo '<tr>';
                echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
                echo '<td>' . esc_html( $order->get_date_created()->format( 'Y-m-d H:i' ) ) . '</td>';
                echo '<td>' . esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $order->get_total() ) ) . '</td>';
                echo '<td><span class="order-status status-' . esc_attr( $order->get_status() ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span></td>';
                echo '<td>' . esc_html( $order->get_transaction_id() ?: '-' ) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No paid orders found.', 'promptpay-n8n-gateway' ) . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Render pending slips tab
     */
    private function render_pending_slips_tab() {
        $orders = $this->get_pending_orders();
        
        echo '<div class="promptpay-pending-slips" style="margin-top: 20px;">';
        echo '<h3>' . esc_html__( 'Pending Slips', 'promptpay-n8n-gateway' ) . '</h3>';
        
        if ( $orders ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Order ID', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Customer', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Amount', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Slip', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'promptpay-n8n-gateway' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $orders as $order ) {
                $slip_url = $order->get_meta( '_promptpay_n8n_slip_url', true );
                
                echo '<tr>';
                echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
                echo '<td>' . esc_html( $order->get_date_created()->format( 'Y-m-d H:i' ) ) . '</td>';
                echo '<td>' . esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $order->get_total() ) ) . '</td>';
                echo '<td><span class="order-status status-' . esc_attr( $order->get_status() ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span></td>';
                echo '<td>';
                if ( $slip_url ) {
                    echo '<a href="' . esc_url( $slip_url ) . '" target="_blank" class="button button-small">' . esc_html__( 'View Slip', 'promptpay-n8n-gateway' ) . '</a>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td>';
                if ( $slip_url ) {
                    echo '<button class="button button-primary button-small manual-verify" data-order-id="' . esc_attr( $order->get_id() ) . '">' . esc_html__( 'Approve', 'promptpay-n8n-gateway' ) . '</button> ';
                    echo '<button class="button button-secondary button-small manual-reject" data-order-id="' . esc_attr( $order->get_id() ) . '">' . esc_html__( 'Reject', 'promptpay-n8n-gateway' ) . '</button>';
                }
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No pending slips found.', 'promptpay-n8n-gateway' ) . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Render webhook events tab
     */
    private function render_webhook_events_tab() {
        $events = $this->get_webhook_events();
        
        echo '<div class="promptpay-webhook-events" style="margin-top: 20px;">';
        echo '<h3>' . esc_html__( 'Webhook Events', 'promptpay-n8n-gateway' ) . '</h3>';
        
        if ( $events ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Date', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Order ID', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Response', 'promptpay-n8n-gateway' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'promptpay-n8n-gateway' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $events as $event ) {
                $webhook_data = json_decode( $event->webhook_data, true );
                $response_data = json_decode( $event->response_data, true );
                
                echo '<tr>';
                echo '<td>' . esc_html( $event->created_at ) . '</td>';
                echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $event->order_id . '&action=edit' ) ) . '">#' . esc_html( $event->order_id ) . '</a></td>';
                echo '<td><span class="webhook-status status-' . esc_attr( $event->status ) . '">' . esc_html( ucfirst( $event->status ) ) . '</span></td>';
                echo '<td>';
                if ( $response_data ) {
                    echo '<details><summary>' . esc_html__( 'View Response', 'promptpay-n8n-gateway' ) . '</summary>';
                    echo '<pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; font-size: 12px; overflow-x: auto;">' . esc_html( wp_json_encode( $response_data, JSON_PRETTY_PRINT ) ) . '</pre>';
                    echo '</details>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td>';
                echo '<details><summary>' . esc_html__( 'View Data', 'promptpay-n8n-gateway' ) . '</summary>';
                echo '<pre style="background: #f1f1f1; padding: 10px; margin-top: 10px; font-size: 12px; overflow-x: auto;">' . esc_html( wp_json_encode( $webhook_data, JSON_PRETTY_PRINT ) ) . '</pre>';
                echo '</details>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No webhook events found.', 'promptpay-n8n-gateway' ) . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Get dashboard statistics
     *
     * @return array
     */
    private function get_dashboard_stats() {
        $args = array(
            'payment_method' => 'promptpay_n8n',
            'limit' => -1,
            'return' => 'ids'
        );

        // Total paid orders
        $paid_orders = wc_get_orders( array_merge( $args, array(
            'status' => array( 'processing', 'completed' )
        ) ) );

        // Pending slips
        $pending_orders = wc_get_orders( array_merge( $args, array(
            'status' => array( 'awaiting-slip', 'pending-verification' )
        ) ) );

        // Today's payments
        $todays_orders = wc_get_orders( array_merge( $args, array(
            'status' => array( 'processing', 'completed' ),
            'date_created' => date( 'Y-m-d' )
        ) ) );

        // Total revenue
        $total_revenue = 0;
        if ( $paid_orders ) {
            foreach ( $paid_orders as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $total_revenue += $order->get_total();
                }
            }
        }

        return array(
            'total_paid_orders' => count( $paid_orders ),
            'pending_slips' => count( $pending_orders ),
            'todays_payments' => count( $todays_orders ),
            'total_revenue' => $total_revenue
        );
    }

    /**
     * Get recent orders
     *
     * @param int $limit
     * @return WC_Order[]
     */
    private function get_recent_orders( $limit = 10 ) {
        return wc_get_orders( array(
            'payment_method' => 'promptpay_n8n',
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }

    /**
     * Get paid orders
     *
     * @return WC_Order[]
     */
    private function get_paid_orders() {
        return wc_get_orders( array(
            'payment_method' => 'promptpay_n8n',
            'status' => array( 'processing', 'completed' ),
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }

    /**
     * Get pending orders
     *
     * @return WC_Order[]
     */
    private function get_pending_orders() {
        return wc_get_orders( array(
            'payment_method' => 'promptpay_n8n',
            'status' => array( 'awaiting-slip', 'pending-verification' ),
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }

    /**
     * Get webhook events
     *
     * @return array
     */
    private function get_webhook_events() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'promptpay_webhook_logs';
        
        return $wpdb->get_results( 
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 50",
            OBJECT
        );
    }

    /**
     * Handle manual verification
     */
    public function handle_manual_verification() {
        check_ajax_referer( 'promptpay_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'promptpay-n8n-gateway' ) ) );
        }

        $order_id = absint( $_POST['order_id'] );
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'promptpay-n8n-gateway' ) ) );
        }

        // Mark payment as complete
        $order->payment_complete();
        $order->add_order_note( __( 'Payment manually verified by admin.', 'promptpay-n8n-gateway' ) );

        wp_send_json_success( array( 'message' => __( 'Payment verified successfully.', 'promptpay-n8n-gateway' ) ) );
    }

    /**
     * Handle manual rejection
     */
    public function handle_manual_rejection() {
        check_ajax_referer( 'promptpay_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'promptpay-n8n-gateway' ) ) );
        }

        $order_id = absint( $_POST['order_id'] );
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'promptpay-n8n-gateway' ) ) );
        }

        // Update order status to failed
        $order->update_status( 'failed', __( 'Payment manually rejected by admin.', 'promptpay-n8n-gateway' ) );

        wp_send_json_success( array( 'message' => __( 'Payment rejected successfully.', 'promptpay-n8n-gateway' ) ) );
    }
}
