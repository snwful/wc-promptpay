<?php
/**
 * AJAX Handler for PromptPay n8n Gateway
 *
 * @package PromptPay_N8N_Gateway
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX Handler Class
 */
class PromptPay_N8N_Ajax_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_promptpay_upload_slip', array( $this, 'handle_slip_upload' ) );
        add_action( 'wp_ajax_nopriv_promptpay_upload_slip', array( $this, 'handle_slip_upload' ) );
    }

    /**
     * Handle payment slip upload
     */
    public function handle_slip_upload() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'promptpay_n8n_nonce' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Get order ID
        $order_id = absint( $_POST['order_id'] );
        if ( ! $order_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid order ID.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Get order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array(
                'message' => __( 'Order not found.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Check if order uses PromptPay payment method
        if ( $order->get_payment_method() !== 'promptpay_n8n' ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid payment method for this order.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Check order status
        if ( ! in_array( $order->get_status(), array( 'awaiting-slip', 'pending-verification' ) ) ) {
            wp_send_json_error( array(
                'message' => __( 'Order is not in a valid state for slip upload.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Check upload attempts
        $upload_attempts = (int) $order->get_meta( '_promptpay_n8n_upload_attempts', true );
        $max_attempts = (int) $order->get_meta( '_promptpay_n8n_max_attempts', true );
        
        if ( $upload_attempts >= $max_attempts ) {
            wp_send_json_error( array(
                'message' => sprintf( 
                    __( 'Maximum upload attempts (%d) reached.', 'promptpay-n8n-gateway' ), 
                    $max_attempts 
                )
            ) );
        }

        // Validate file upload
        if ( ! isset( $_FILES['payment_slip'] ) || $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( array(
                'message' => __( 'No file uploaded or upload error occurred.', 'promptpay-n8n-gateway' )
            ) );
        }

        $file = $_FILES['payment_slip'];
        
        // Validate file type
        $allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'application/pdf' );
        if ( ! in_array( $file['type'], $allowed_types ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid file type. Only JPG, PNG, and PDF files are allowed.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ( $file['size'] > $max_size ) {
            wp_send_json_error( array(
                'message' => __( 'File size too large. Maximum size is 5MB.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Upload file
        $upload_result = $this->upload_slip_file( $file, $order_id );
        if ( is_wp_error( $upload_result ) ) {
            wp_send_json_error( array(
                'message' => $upload_result->get_error_message()
            ) );
        }

        // Update upload attempts
        $order->update_meta_data( '_promptpay_n8n_upload_attempts', $upload_attempts + 1 );
        $order->update_meta_data( '_promptpay_n8n_slip_file', $upload_result['file_path'] );
        $order->update_meta_data( '_promptpay_n8n_slip_url', $upload_result['file_url'] );
        $order->save();

        // Send to n8n webhook
        $webhook_result = $this->send_to_n8n_webhook( $order, $upload_result );
        
        if ( is_wp_error( $webhook_result ) ) {
            // Log the error but don't fail the upload
            $order->add_order_note( 
                sprintf( 
                    __( 'Payment slip uploaded but webhook failed: %s', 'promptpay-n8n-gateway' ), 
                    $webhook_result->get_error_message() 
                ) 
            );
            
            wp_send_json_success( array(
                'message' => __( 'Payment slip uploaded successfully, but verification system is temporarily unavailable. We will process your payment manually.', 'promptpay-n8n-gateway' )
            ) );
        }

        // Update order status
        $order->update_status( 'pending-verification', __( 'Payment slip uploaded. Awaiting verification from n8n system.', 'promptpay-n8n-gateway' ) );

        wp_send_json_success( array(
            'message' => __( 'Payment slip uploaded successfully! We will verify your payment shortly.', 'promptpay-n8n-gateway' )
        ) );
    }

    /**
     * Upload slip file
     *
     * @param array $file File data from $_FILES
     * @param int $order_id Order ID
     * @return array|WP_Error Upload result or error
     */
    private function upload_slip_file( $file, $order_id ) {
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-slips';
        
        if ( ! file_exists( $promptpay_dir ) ) {
            wp_mkdir_p( $promptpay_dir );
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n<Files *.php>\ndeny from all\n</Files>";
            file_put_contents( $promptpay_dir . '/.htaccess', $htaccess_content );
        }

        // Generate unique filename
        $file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $filename = 'slip_' . $order_id . '_' . time() . '_' . wp_generate_password( 8, false ) . '.' . $file_extension;
        $file_path = $promptpay_dir . '/' . $filename;
        $file_url = $upload_dir['baseurl'] . '/promptpay-slips/' . $filename;

        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new WP_Error( 'upload_failed', __( 'Failed to save uploaded file.', 'promptpay-n8n-gateway' ) );
        }

        // Set proper file permissions
        chmod( $file_path, 0644 );

        return array(
            'file_path' => $file_path,
            'file_url' => $file_url,
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'file_type' => $file['type']
        );
    }

    /**
     * Send slip data to n8n webhook
     *
     * @param WC_Order $order Order object
     * @param array $upload_result Upload result data
     * @return array|WP_Error Webhook response or error
     */
    private function send_to_n8n_webhook( $order, $upload_result ) {
        // Get gateway settings
        $gateway = new WC_Payment_Gateway_PromptPay_N8N();
        $webhook_url = $gateway->get_option( 'n8n_webhook_url' );
        
        if ( empty( $webhook_url ) ) {
            return new WP_Error( 'no_webhook_url', __( 'n8n webhook URL not configured.', 'promptpay-n8n-gateway' ) );
        }

        // Prepare data for n8n
        $webhook_data = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_email' => $order->get_billing_email(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'slip_file_url' => $upload_result['file_url'],
            'slip_filename' => $upload_result['filename'],
            'upload_timestamp' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
            'callback_url' => rest_url( 'promptpay-gateway/v1/callback' )
        );

        // Prepare multipart form data
        $boundary = wp_generate_password( 24, false );
        $body = '';

        // Add form fields
        foreach ( $webhook_data as $key => $value ) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= $value . "\r\n";
        }

        // Add file data
        if ( file_exists( $upload_result['file_path'] ) ) {
            $file_content = file_get_contents( $upload_result['file_path'] );
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"slip_file\"; filename=\"{$upload_result['filename']}\"\r\n";
            $body .= "Content-Type: {$upload_result['file_type']}\r\n\r\n";
            $body .= $file_content . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        // Send request
        $response = wp_remote_post( $webhook_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'User-Agent' => 'PromptPay-n8n-Gateway/' . PROMPTPAY_N8N_VERSION
            ),
            'body' => $body
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Log webhook call
        $this->log_webhook_call( $order->get_id(), $webhook_data, $response_code, $response_body );

        if ( $response_code !== 200 ) {
            return new WP_Error( 
                'webhook_error', 
                sprintf( 
                    __( 'Webhook returned error code %d: %s', 'promptpay-n8n-gateway' ), 
                    $response_code, 
                    $response_body 
                ) 
            );
        }

        return array(
            'response_code' => $response_code,
            'response_body' => $response_body
        );
    }

    /**
     * Log webhook call
     *
     * @param int $order_id Order ID
     * @param array $webhook_data Data sent to webhook
     * @param int $response_code HTTP response code
     * @param string $response_body Response body
     */
    private function log_webhook_call( $order_id, $webhook_data, $response_code, $response_body ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'promptpay_webhook_logs';

        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'webhook_data' => wp_json_encode( $webhook_data ),
                'status' => $response_code === 200 ? 'success' : 'failed',
                'response_data' => wp_json_encode( array(
                    'response_code' => $response_code,
                    'response_body' => $response_body
                ) ),
                'created_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }
}
