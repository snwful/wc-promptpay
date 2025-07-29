<?php
namespace WooPromptPay\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * File Upload Handler Class
 * 
 * Handles secure file uploads for payment slips with validation and anti-spam protection
 */
class PP_Upload_Handler {

    /**
     * Maximum file size in bytes (5MB)
     */
    const MAX_FILE_SIZE = 5 * 1024 * 1024;
    
    /**
     * Allowed MIME types
     */
    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'application/pdf'
    ];
    
    /**
     * Allowed file extensions
     */
    const ALLOWED_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'pdf' ];

    /**
     * Handle AJAX slip upload request
     */
    public static function handle_ajax() {
        // Verify request method
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request method.', 'woo-promptpay-n8n' ) ], 405 );
        }
        
        // Get and validate input data
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $amount = floatval( $_POST['amount'] ?? 0 );
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        
        // Verify nonce for CSRF protection
        if ( ! wp_verify_nonce( $nonce, 'ppn8n_upload_' . $order_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'woo-promptpay-n8n' ) ], 403 );
        }
        
        // Validate order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Order not found.', 'woo-promptpay-n8n' ) ], 404 );
        }
        
        // Check if order uses PromptPay payment method
        if ( $order->get_payment_method() !== 'promptpay_n8n' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid payment method for this order.', 'woo-promptpay-n8n' ) ], 400 );
        }
        
        // Anti-spam: Check upload attempts
        $attempt_count = (int) $order->get_meta( '_pp_attempt_count', true );
        $gateway = new \WooPromptPay\Gateway\PP_Payment_Gateway();
        $max_attempts = $gateway->get_option( 'max_attempts', 3 );
        
        if ( $attempt_count >= $max_attempts ) {
            wp_send_json_error( [ 'message' => sprintf( 
                __( 'Maximum upload attempts (%d) reached for this order.', 'woo-promptpay-n8n' ), 
                $max_attempts 
            ) ], 429 );
        }
        
        // Validate file upload
        if ( empty( $_FILES['slip_file'] ) || $_FILES['slip_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => __( 'No file uploaded or upload error occurred.', 'woo-promptpay-n8n' ) ], 400 );
        }
        
        $file = $_FILES['slip_file'];
        
        // Validate file
        $validation_result = self::validate_uploaded_file( $file );
        if ( is_wp_error( $validation_result ) ) {
            wp_send_json_error( [ 'message' => $validation_result->get_error_message() ], 400 );
        }
        
        // Handle file upload
        $upload_result = self::handle_file_upload( $file, $order_id );
        if ( is_wp_error( $upload_result ) ) {
            wp_send_json_error( [ 'message' => $upload_result->get_error_message() ], 500 );
        }
        
        // Update order meta
        $attempt_count++;
        $order->update_meta_data( '_pp_attempt_count', $attempt_count );
        $order->update_meta_data( '_pp_slip_url', esc_url_raw( $upload_result['url'] ) );
        $order->update_meta_data( '_pp_slip_path', $upload_result['file'] );
        $order->update_meta_data( '_pp_upload_timestamp', current_time( 'timestamp' ) );
        
        // Add order note
        $order->add_order_note( sprintf( 
            __( 'Payment slip uploaded successfully. File: %s (Attempt %d of %d)', 'woo-promptpay-n8n' ),
            basename( $upload_result['file'] ),
            $attempt_count,
            $max_attempts
        ) );
        
        $order->save();
        
        // Send data to n8n webhook
        self::send_to_n8n_webhook( $order, $upload_result );
        
        // Return success response
        wp_send_json_success( [ 
            'message' => __( 'Payment slip uploaded successfully. We will verify your payment shortly.', 'woo-promptpay-n8n' ),
            'attempt_count' => $attempt_count,
            'remaining_attempts' => max( 0, $max_attempts - $attempt_count )
        ] );
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file $_FILES array element
     * @return true|\WP_Error True if valid, WP_Error if invalid
     */
    private static function validate_uploaded_file( $file ) {
        // Check file size
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error( 
                'file_too_large', 
                sprintf( __( 'File size exceeds maximum allowed size of %s.', 'woo-promptpay-n8n' ), size_format( self::MAX_FILE_SIZE ) )
            );
        }
        
        // Check file extension
        $file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $file_extension, self::ALLOWED_EXTENSIONS, true ) ) {
            return new \WP_Error( 
                'invalid_file_type', 
                sprintf( __( 'Invalid file type. Allowed types: %s', 'woo-promptpay-n8n' ), implode( ', ', self::ALLOWED_EXTENSIONS ) )
            );
        }
        
        // Check MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        
        if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
            return new \WP_Error( 
                'invalid_mime_type', 
                __( 'Invalid file format detected. Please upload a valid image or PDF file.', 'woo-promptpay-n8n' )
            );
        }
        
        // Additional security check for images
        if ( strpos( $mime_type, 'image/' ) === 0 ) {
            $image_info = getimagesize( $file['tmp_name'] );
            if ( $image_info === false ) {
                return new \WP_Error( 
                    'invalid_image', 
                    __( 'Invalid image file. Please upload a valid image.', 'woo-promptpay-n8n' )
                );
            }
        }
        
        return true;
    }
    
    /**
     * Handle secure file upload
     * 
     * @param array $file     $_FILES array element
     * @param int   $order_id Order ID
     * @return array|\WP_Error Upload result or WP_Error on failure
     */
    private static function handle_file_upload( $file, $order_id ) {
        // Set up upload directory
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-slips';
        
        // Create directory if it doesn't exist
        if ( ! file_exists( $promptpay_dir ) ) {
            wp_mkdir_p( $promptpay_dir );
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents( $promptpay_dir . '/.htaccess', $htaccess_content );
        }
        
        // Generate unique filename
        $file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $filename = 'slip-' . $order_id . '-' . time() . '-' . wp_generate_password( 8, false ) . '.' . $file_extension;
        
        // Set up upload overrides
        $upload_overrides = [
            'test_form' => false,
            'upload_error_handler' => [ __CLASS__, 'upload_error_handler' ]
        ];
        
        // Handle upload using WordPress function
        add_filter( 'upload_dir', [ __CLASS__, 'custom_upload_dir' ] );
        $uploaded_file = wp_handle_upload( $file, $upload_overrides );
        remove_filter( 'upload_dir', [ __CLASS__, 'custom_upload_dir' ] );
        
        if ( isset( $uploaded_file['error'] ) ) {
            return new \WP_Error( 'upload_failed', $uploaded_file['error'] );
        }
        
        return $uploaded_file;
    }
    
    /**
     * Custom upload directory for PromptPay slips
     * 
     * @param array $dirs Upload directory information
     * @return array Modified directory information
     */
    public static function custom_upload_dir( $dirs ) {
        $dirs['subdir'] = '/promptpay-slips';
        $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
        
        return $dirs;
    }
    
    /**
     * Custom upload error handler
     * 
     * @param array  $file   File array
     * @param string $message Error message
     * @return array Modified file array with error
     */
    public static function upload_error_handler( $file, $message ) {
        return [ 'error' => $message ];
    }
    
    /**
     * Send upload data to n8n webhook
     * 
     * @param \WC_Order $order        Order object
     * @param array     $upload_result Upload result
     */
    private static function send_to_n8n_webhook( $order, $upload_result ) {
        $gateway = new \WooPromptPay\Gateway\PP_Payment_Gateway();
        $webhook_url = $gateway->get_option( 'n8n_webhook_url' );
        
        if ( empty( $webhook_url ) ) {
            return;
        }
        
        // Prepare webhook payload
        $payload = [
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_key' => $order->get_order_key(),
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'slip_url' => $upload_result['url'],
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'upload_timestamp' => current_time( 'c' ),
            'callback_url' => rest_url( 'ppn8n/v1/confirm' ),
            'webhook_url' => home_url( '/pp-n8n/webhook/' ),
        ];
        
        // Add order note about webhook
        $order->add_order_note( __( 'Payment slip data sent to n8n for verification.', 'woo-promptpay-n8n' ) );
        
        // Send async webhook request
        wp_remote_post( $webhook_url, [
            'body' => wp_json_encode( $payload ),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'WooPromptPayN8N/' . WPPN8N_VERSION
            ],
            'timeout' => 30,
            'blocking' => false, // Non-blocking for better performance
            'sslverify' => true
        ] );
    }
    
    /**
     * Clean up old slip files (can be called via cron)
     * 
     * @param int $days_old Delete files older than this many days
     */
    public static function cleanup_old_files( $days_old = 30 ) {
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-slips';
        
        if ( ! file_exists( $promptpay_dir ) ) {
            return;
        }
        
        $files = glob( $promptpay_dir . '/slip-*' );
        $cutoff_time = time() - ( $days_old * DAY_IN_SECONDS );
        
        foreach ( $files as $file ) {
            if ( filemtime( $file ) < $cutoff_time ) {
                unlink( $file );
            }
        }
    }
    
    /**
     * Get upload statistics for admin
     * 
     * @return array Upload statistics
     */
    public static function get_upload_stats() {
        global $wpdb;
        
        $stats = [];
        
        // Total uploads today
        $today = date( 'Y-m-d' );
        $stats['uploads_today'] = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_pp_upload_timestamp' 
            AND FROM_UNIXTIME(meta_value, '%%Y-%%m-%%d') = %s
        ", $today ) );
        
        // Total uploads this month
        $this_month = date( 'Y-m' );
        $stats['uploads_this_month'] = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_pp_upload_timestamp' 
            AND FROM_UNIXTIME(meta_value, '%%Y-%%m') = %s
        ", $this_month ) );
        
        // Average uploads per day (last 30 days)
        $stats['avg_uploads_per_day'] = $wpdb->get_var( "
            SELECT COUNT(*) / 30
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_pp_upload_timestamp' 
            AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        " );
        
        return $stats;
    }
}
