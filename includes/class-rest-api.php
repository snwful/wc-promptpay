<?php
/**
 * REST API Handler for PromptPay n8n Gateway
 *
 * @package PromptPay_N8N_Gateway
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API Class
 */
class PromptPay_N8N_REST_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route( 'promptpay-gateway/v1', '/callback', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_callback' ),
            'permission_callback' => array( $this, 'verify_callback_permission' ),
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    }
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array( 'success', 'failed', 'pending' )
                )
            )
        ) );

        register_rest_route( 'promptpay-gateway/v1', '/status/(?P<order_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_order_status' ),
            'permission_callback' => array( $this, 'verify_status_permission' ),
            'args' => array(
                'order_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    }
                )
            )
        ) );
    }

    /**
     * Verify callback permission using shared secret key
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function verify_callback_permission( $request ) {
        // Get gateway settings
        $gateway = new WC_Payment_Gateway_PromptPay_N8N();
        $shared_secret = $gateway->get_option( 'shared_secret_key' );

        if ( empty( $shared_secret ) ) {
            return new WP_Error( 
                'no_secret_key', 
                __( 'Shared secret key not configured.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 500 ) 
            );
        }

        // Check for secret key in headers
        $provided_secret = $request->get_header( 'X-PromptPay-Secret' );
        if ( empty( $provided_secret ) ) {
            // Also check in Authorization header
            $auth_header = $request->get_header( 'Authorization' );
            if ( $auth_header && strpos( $auth_header, 'Bearer ' ) === 0 ) {
                $provided_secret = substr( $auth_header, 7 );
            }
        }

        if ( empty( $provided_secret ) ) {
            return new WP_Error( 
                'missing_secret', 
                __( 'Missing authentication secret.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 401 ) 
            );
        }

        // Verify secret key
        if ( ! hash_equals( $shared_secret, $provided_secret ) ) {
            return new WP_Error( 
                'invalid_secret', 
                __( 'Invalid authentication secret.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 403 ) 
            );
        }

        return true;
    }

    /**
     * Verify status check permission
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function verify_status_permission( $request ) {
        // Allow logged-in users or valid nonce
        if ( is_user_logged_in() ) {
            return true;
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Handle payment verification callback from n8n
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_callback( $request ) {
        $order_id = $request->get_param( 'order_id' );
        $status = $request->get_param( 'status' );
        $amount_paid = $request->get_param( 'amount_paid' );
        $transaction_id = $request->get_param( 'transaction_id' );
        $reason = $request->get_param( 'reason' );
        $verification_data = $request->get_param( 'verification_data' );

        // Get order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 
                'order_not_found', 
                __( 'Order not found.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 404 ) 
            );
        }

        // Verify order uses PromptPay payment method
        if ( $order->get_payment_method() !== 'promptpay_n8n' ) {
            return new WP_Error( 
                'invalid_payment_method', 
                __( 'Order does not use PromptPay payment method.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 400 ) 
            );
        }

        // Log the callback
        $this->log_callback( $order_id, $request->get_params() );

        // Process based on status
        switch ( $status ) {
            case 'success':
                $this->process_successful_payment( $order, $amount_paid, $transaction_id, $verification_data );
                break;

            case 'failed':
                $this->process_failed_payment( $order, $reason );
                break;

            case 'pending':
                $this->process_pending_payment( $order, $reason );
                break;

            default:
                return new WP_Error( 
                    'invalid_status', 
                    __( 'Invalid payment status.', 'promptpay-n8n-gateway' ), 
                    array( 'status' => 400 ) 
                );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Callback processed successfully.', 'promptpay-n8n-gateway' ),
            'order_id' => $order_id,
            'new_status' => $order->get_status()
        ), 200 );
    }

    /**
     * Process successful payment
     *
     * @param WC_Order $order
     * @param float $amount_paid
     * @param string $transaction_id
     * @param array $verification_data
     */
    private function process_successful_payment( $order, $amount_paid, $transaction_id, $verification_data ) {
        // Verify amount if provided
        if ( $amount_paid && abs( $order->get_total() - floatval( $amount_paid ) ) > 0.01 ) {
            $order->add_order_note( sprintf(
                __( 'Payment amount mismatch. Expected: %s, Received: %s', 'promptpay-n8n-gateway' ),
                wc_price( $order->get_total() ),
                wc_price( $amount_paid )
            ) );
        }

        // Set transaction ID if provided
        if ( $transaction_id ) {
            $order->set_transaction_id( sanitize_text_field( $transaction_id ) );
        }

        // Add verification data as meta
        if ( $verification_data ) {
            $order->update_meta_data( '_promptpay_n8n_verification_data', $verification_data );
        }

        // Mark payment as complete
        $order->payment_complete( $transaction_id );

        // Add success note
        $note = __( 'Payment successfully verified by n8n system.', 'promptpay-n8n-gateway' );
        if ( $amount_paid ) {
            $note .= ' ' . sprintf( __( 'Amount: %s', 'promptpay-n8n-gateway' ), wc_price( $amount_paid ) );
        }
        if ( $transaction_id ) {
            $note .= ' ' . sprintf( __( 'Transaction ID: %s', 'promptpay-n8n-gateway' ), $transaction_id );
        }
        
        $order->add_order_note( $note );

        // Send customer notification
        WC()->mailer()->customer_completed_order( $order );

        // Trigger action for other plugins
        do_action( 'promptpay_n8n_payment_verified', $order, $verification_data );
    }

    /**
     * Process failed payment
     *
     * @param WC_Order $order
     * @param string $reason
     */
    private function process_failed_payment( $order, $reason ) {
        // Update order status to failed
        $order->update_status( 'failed', sprintf(
            __( 'Payment verification failed. Reason: %s', 'promptpay-n8n-gateway' ),
            $reason ? sanitize_text_field( $reason ) : __( 'Unknown', 'promptpay-n8n-gateway' )
        ) );

        // Check if customer can retry upload
        $upload_attempts = (int) $order->get_meta( '_promptpay_n8n_upload_attempts', true );
        $max_attempts = (int) $order->get_meta( '_promptpay_n8n_max_attempts', true );

        if ( $upload_attempts < $max_attempts ) {
            // Allow retry - change status back to awaiting slip
            $order->update_status( 'awaiting-slip', sprintf(
                __( 'Payment verification failed (%s). Customer can retry upload. Attempts: %d/%d', 'promptpay-n8n-gateway' ),
                $reason ? sanitize_text_field( $reason ) : __( 'Unknown', 'promptpay-n8n-gateway' ),
                $upload_attempts,
                $max_attempts
            ) );
        }

        // Trigger action for other plugins
        do_action( 'promptpay_n8n_payment_failed', $order, $reason );
    }

    /**
     * Process pending payment
     *
     * @param WC_Order $order
     * @param string $reason
     */
    private function process_pending_payment( $order, $reason ) {
        // Keep in pending verification status
        $order->add_order_note( sprintf(
            __( 'Payment verification is still pending. Reason: %s', 'promptpay-n8n-gateway' ),
            $reason ? sanitize_text_field( $reason ) : __( 'Manual review required', 'promptpay-n8n-gateway' )
        ) );

        // Trigger action for other plugins
        do_action( 'promptpay_n8n_payment_pending', $order, $reason );
    }

    /**
     * Get order status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_order_status( $request ) {
        $order_id = $request->get_param( 'order_id' );
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 
                'order_not_found', 
                __( 'Order not found.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 404 ) 
            );
        }

        // Check if user has permission to view this order
        if ( ! current_user_can( 'manage_woocommerce' ) && $order->get_customer_id() !== get_current_user_id() ) {
            return new WP_Error( 
                'insufficient_permission', 
                __( 'You do not have permission to view this order.', 'promptpay-n8n-gateway' ), 
                array( 'status' => 403 ) 
            );
        }

        $upload_attempts = (int) $order->get_meta( '_promptpay_n8n_upload_attempts', true );
        $max_attempts = (int) $order->get_meta( '_promptpay_n8n_max_attempts', true );
        $slip_url = $order->get_meta( '_promptpay_n8n_slip_url', true );

        return new WP_REST_Response( array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'upload_attempts' => $upload_attempts,
            'max_attempts' => $max_attempts,
            'slip_uploaded' => ! empty( $slip_url ),
            'created_date' => $order->get_date_created()->format( 'Y-m-d H:i:s' )
        ), 200 );
    }

    /**
     * Log callback data
     *
     * @param int $order_id
     * @param array $callback_data
     */
    private function log_callback( $order_id, $callback_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'promptpay_webhook_logs';

        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'webhook_data' => wp_json_encode( $callback_data ),
                'status' => 'callback_received',
                'response_data' => wp_json_encode( array(
                    'callback_status' => $callback_data['status'] ?? 'unknown',
                    'processed_at' => current_time( 'mysql' )
                ) ),
                'created_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }
}
