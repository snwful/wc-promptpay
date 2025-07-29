<?php
namespace WooPromptPay\Webhook;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Webhook Handler Class
 * 
 * Handles incoming webhooks from n8n to update order status
 */
class PP_Webhook_Handler {

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route( 'ppn8n/v1', '/confirm', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_rest_request' ],
            'permission_callback' => '__return_true', // We handle validation inside
            'args' => [
                'order_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    }
                ],
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => [ 'success', 'failed', 'pending' ]
                ],
                'order_key' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'transaction_id' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'message' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'amount' => [
                    'required' => false,
                    'type' => 'number'
                ]
            ]
        ] );
    }

    /**
     * Handle REST API request
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response object
     */
    public function handle_rest_request( $request ) {
        $response = $this->process_webhook_data( $request->get_json_params() );
        return rest_ensure_response( $response );
    }

    /**
     * Handle direct webhook request (pretty URL)
     */
    public function handle_request() {
        // Set JSON content type
        header( 'Content-Type: application/json; charset=utf-8' );
        
        // Only allow POST requests
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            http_response_code( 405 );
            echo wp_json_encode( [ 
                'success' => false, 
                'message' => 'Method not allowed' 
            ] );
            return;
        }
        
        // Get raw POST data
        $raw_input = file_get_contents( 'php://input' );
        $data = json_decode( $raw_input, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            http_response_code( 400 );
            echo wp_json_encode( [ 
                'success' => false, 
                'message' => 'Invalid JSON data' 
            ] );
            return;
        }
        
        // Process webhook data
        $response = $this->process_webhook_data( $data );
        
        // Set appropriate HTTP status code
        if ( ! $response['success'] ) {
            http_response_code( isset( $response['http_code'] ) ? $response['http_code'] : 400 );
        }
        
        echo wp_json_encode( $response );
    }

    /**
     * Process webhook data and update order
     * 
     * @param array $data Webhook data
     * @return array Response data
     */
    private function process_webhook_data( $data ) {
        // Validate required fields
        if ( empty( $data['order_id'] ) || empty( $data['status'] ) ) {
            return [
                'success' => false,
                'message' => 'Missing required fields: order_id and status',
                'http_code' => 400
            ];
        }
        
        $order_id = absint( $data['order_id'] );
        $status = sanitize_text_field( $data['status'] );
        $order_key = sanitize_text_field( $data['order_key'] ?? '' );
        $transaction_id = sanitize_text_field( $data['transaction_id'] ?? '' );
        $message = sanitize_text_field( $data['message'] ?? '' );
        $amount = floatval( $data['amount'] ?? 0 );
        
        // Validate status
        if ( ! in_array( $status, [ 'success', 'failed', 'pending' ], true ) ) {
            return [
                'success' => false,
                'message' => 'Invalid status. Must be: success, failed, or pending',
                'http_code' => 400
            ];
        }
        
        // Get order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return [
                'success' => false,
                'message' => 'Order not found',
                'http_code' => 404
            ];
        }
        
        // Verify order key if provided (additional security)
        if ( ! empty( $order_key ) && $order->get_order_key() !== $order_key ) {
            return [
                'success' => false,
                'message' => 'Invalid order key',
                'http_code' => 403
            ];
        }
        
        // Check if order uses PromptPay payment method
        if ( $order->get_payment_method() !== 'promptpay_n8n' ) {
            return [
                'success' => false,
                'message' => 'Order does not use PromptPay payment method',
                'http_code' => 400
            ];
        }
        
        // Anti-spam: Check if webhook was already processed
        $last_webhook_status = $order->get_meta( '_pp_last_webhook_status', true );
        $last_webhook_time = $order->get_meta( '_pp_last_webhook_time', true );
        
        // Prevent duplicate processing within 60 seconds
        if ( $last_webhook_status === $status && 
             $last_webhook_time && 
             ( time() - $last_webhook_time ) < 60 ) {
            return [
                'success' => true,
                'message' => 'Webhook already processed recently',
                'duplicate' => true
            ];
        }
        
        // Process based on status
        $result = $this->update_order_status( $order, $status, $transaction_id, $message, $amount );
        
        // Update webhook meta
        $order->update_meta_data( '_pp_last_webhook_status', $status );
        $order->update_meta_data( '_pp_last_webhook_time', time() );
        $order->update_meta_data( '_pp_webhook_data', $data );
        $order->save();
        
        return $result;
    }

    /**
     * Update order status based on webhook data
     * 
     * @param \WC_Order $order          Order object
     * @param string    $status         Webhook status
     * @param string    $transaction_id Transaction ID
     * @param string    $message        Status message
     * @param float     $amount         Payment amount
     * @return array Response data
     */
    private function update_order_status( $order, $status, $transaction_id = '', $message = '', $amount = 0 ) {
        $order_id = $order->get_id();
        
        switch ( $status ) {
            case 'success':
                return $this->handle_successful_payment( $order, $transaction_id, $message, $amount );
                
            case 'failed':
                return $this->handle_failed_payment( $order, $message );
                
            case 'pending':
                return $this->handle_pending_payment( $order, $message );
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown status: ' . $status,
                    'http_code' => 400
                ];
        }
    }

    /**
     * Handle successful payment
     * 
     * @param \WC_Order $order          Order object
     * @param string    $transaction_id Transaction ID
     * @param string    $message        Success message
     * @param float     $amount         Payment amount
     * @return array Response data
     */
    private function handle_successful_payment( $order, $transaction_id = '', $message = '', $amount = 0 ) {
        // Check if order is already paid
        if ( $order->is_paid() ) {
            return [
                'success' => true,
                'message' => 'Order is already paid',
                'already_paid' => true
            ];
        }
        
        // Validate amount if provided
        if ( $amount > 0 ) {
            $order_amount = floatval( $order->get_total() );
            if ( abs( $order_amount - $amount ) > 0.01 ) { // Allow 1 cent difference for rounding
                $order->add_order_note( sprintf(
                    __( 'Payment amount mismatch detected. Order amount: %s, Received amount: %s', 'woo-promptpay-n8n' ),
                    wc_price( $order_amount ),
                    wc_price( $amount )
                ) );
            }
        }
        
        // Set transaction ID if provided
        if ( ! empty( $transaction_id ) ) {
            $order->set_transaction_id( $transaction_id );
        }
        
        // Mark payment as complete
        $order->payment_complete( $transaction_id );
        
        // Add success note
        $note = __( 'Payment confirmed via n8n webhook.', 'woo-promptpay-n8n' );
        if ( ! empty( $message ) ) {
            $note .= ' ' . sprintf( __( 'Message: %s', 'woo-promptpay-n8n' ), $message );
        }
        if ( ! empty( $transaction_id ) ) {
            $note .= ' ' . sprintf( __( 'Transaction ID: %s', 'woo-promptpay-n8n' ), $transaction_id );
        }
        
        $order->add_order_note( $note );
        
        // Save order
        $order->save();
        
        // Log webhook event
        \WooPromptPay\Admin\PP_Admin_Dashboard::log_webhook_event( 
            $order->get_id(), 
            'success', 
            $message, 
            $transaction_id 
        );
        
        // Trigger action for other plugins/themes
        do_action( 'ppn8n_payment_confirmed', $order, $transaction_id, $message );
        
        return [
            'success' => true,
            'message' => 'Payment confirmed successfully',
            'order_status' => $order->get_status()
        ];
    }

    /**
     * Handle failed payment
     * 
     * @param \WC_Order $order   Order object
     * @param string    $message Failure message
     * @return array Response data
     */
    private function handle_failed_payment( $order, $message = '' ) {
        // Update order status to failed
        $order->update_status( 'failed', __( 'Payment failed via n8n webhook.', 'woo-promptpay-n8n' ) );
        
        // Add failure note
        $note = __( 'Payment verification failed via n8n webhook.', 'woo-promptpay-n8n' );
        if ( ! empty( $message ) ) {
            $note .= ' ' . sprintf( __( 'Reason: %s', 'woo-promptpay-n8n' ), $message );
        }
        
        $order->add_order_note( $note );
        
        // Save order
        $order->save();
        
        // Log webhook event
        \WooPromptPay\Admin\PP_Admin_Dashboard::log_webhook_event( 
            $order->get_id(), 
            'failed', 
            $message 
        );
        
        // Trigger action for other plugins/themes
        do_action( 'ppn8n_payment_failed', $order, $message );
        
        return [
            'success' => true,
            'message' => 'Payment marked as failed',
            'order_status' => $order->get_status()
        ];
    }

    /**
     * Handle pending payment
     * 
     * @param \WC_Order $order   Order object
     * @param string    $message Pending message
     * @return array Response data
     */
    private function handle_pending_payment( $order, $message = '' ) {
        // Add pending note
        $note = __( 'Payment verification in progress via n8n webhook.', 'woo-promptpay-n8n' );
        if ( ! empty( $message ) ) {
            $note .= ' ' . sprintf( __( 'Status: %s', 'woo-promptpay-n8n' ), $message );
        }
        
        $order->add_order_note( $note );
        
        // Save order
        $order->save();
        
        // Log webhook event
        \WooPromptPay\Admin\PP_Admin_Dashboard::log_webhook_event( 
            $order->get_id(), 
            'pending', 
            $message 
        );
        
        // Trigger action for other plugins/themes
        do_action( 'ppn8n_payment_pending', $order, $message );
        
        return [
            'success' => true,
            'message' => 'Payment status updated to pending',
            'order_status' => $order->get_status()
        ];
    }

    /**
     * Get webhook logs for debugging
     * 
     * @param int $limit Number of logs to retrieve
     * @return array Webhook logs
     */
    public function get_webhook_logs( $limit = 50 ) {
        global $wpdb;
        
        $logs = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID, p.post_date, pm1.meta_value as webhook_status, pm2.meta_value as webhook_time, pm3.meta_value as webhook_data
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_pp_last_webhook_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_pp_last_webhook_time'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_pp_webhook_data'
            WHERE p.post_type = 'shop_order'
            AND pm1.meta_value IS NOT NULL
            ORDER BY pm2.meta_value DESC
            LIMIT %d
        ", $limit ) );
        
        return $logs;
    }

    /**
     * Test webhook endpoint
     * 
     * @return array Test result
     */
    public function test_webhook() {
        $test_data = [
            'order_id' => 999999, // Non-existent order ID
            'status' => 'success',
            'message' => 'Test webhook',
            'test' => true
        ];
        
        $result = $this->process_webhook_data( $test_data );
        
        return [
            'endpoint_accessible' => true,
            'test_result' => $result,
            'timestamp' => current_time( 'c' )
        ];
    }
}
