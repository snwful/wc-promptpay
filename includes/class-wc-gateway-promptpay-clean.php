<?php
/**
 * WooCommerce PromptPay Payment Gateway (Clean Version)
 * 
 * Standard WooCommerce Payment Gateway implementation for PromptPay
 * Following WooCommerce best practices and standards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include QR Generator
require_once plugin_dir_path( __FILE__ ) . 'class-promptpay-qr-generator.php';

/**
 * WC_Gateway_PromptPay_Clean Class
 * 
 * Extends WC_Payment_Gateway to create a proper PromptPay payment gateway
 */
class WC_Gateway_PromptPay_Clean extends WC_Payment_Gateway {

    /**
     * PromptPay ID for QR code generation
     * @var string
     */
    public $promptpay_id;

    /**
     * n8n webhook URL for payment verification
     * @var string
     */
    public $n8n_webhook_url;

    /**
     * Maximum upload attempts per order
     * @var int
     */
    public $max_attempts;

    /**
     * Constructor
     */
    public function __construct() {
        // Gateway ID - must be unique
        $this->id = 'promptpay_clean';
        
        // Gateway icon (optional)
        $this->icon = '';
        
        // Set to true if you want payment fields on checkout
        $this->has_fields = true;
        
        // Gateway title and description for admin
        $this->method_title = __( 'PromptPay (Clean)', 'woo-promptpay-clean' );
        $this->method_description = __( 'Accept payments via PromptPay QR code with slip upload verification.', 'woo-promptpay-clean' );
        
        // Gateway supports
        $this->supports = array(
            'products',
            'refunds'
        );

        // Initialize form fields and settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings values
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->promptpay_id = $this->get_option( 'promptpay_id' );
        $this->n8n_webhook_url = $this->get_option( 'n8n_webhook_url' );
        $this->max_attempts = $this->get_option( 'max_attempts', 3 );

        // Save settings hook
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
        // Thank you page hook
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        
        // Email instructions hook
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        
        // AJAX handlers for slip upload
        add_action( 'wp_ajax_promptpay_clean_upload_slip', array( $this, 'handle_slip_upload' ) );
        add_action( 'wp_ajax_nopriv_promptpay_clean_upload_slip', array( $this, 'handle_slip_upload' ) );
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'woo-promptpay-clean' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PromptPay Payment', 'woo-promptpay-clean' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', 'woo-promptpay-clean' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woo-promptpay-clean' ),
                'default'     => __( 'PromptPay', 'woo-promptpay-clean' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'woo-promptpay-clean' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-promptpay-clean' ),
                'default'     => __( 'Pay with PromptPay by scanning the QR code with your mobile banking app.', 'woo-promptpay-clean' ),
                'desc_tip'    => true,
            ),
            'promptpay_id' => array(
                'title'       => __( 'PromptPay ID', 'woo-promptpay-clean' ),
                'type'        => 'text',
                'description' => __( 'Enter your PromptPay ID (phone number or national ID).', 'woo-promptpay-clean' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'n8n_webhook_url' => array(
                'title'       => __( 'n8n Webhook URL', 'woo-promptpay-clean' ),
                'type'        => 'url',
                'description' => __( 'Enter your n8n webhook URL for payment verification.', 'woo-promptpay-clean' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'max_attempts' => array(
                'title'       => __( 'Max Upload Attempts', 'woo-promptpay-clean' ),
                'type'        => 'number',
                'description' => __( 'Maximum number of slip upload attempts per order.', 'woo-promptpay-clean' ),
                'default'     => 3,
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        // Check if gateway is enabled
        if ( 'yes' !== $this->enabled ) {
            return false;
        }

        // Check if PromptPay ID is set (required for production)
        if ( empty( $this->promptpay_id ) ) {
            return false;
        }

        // Check parent availability (currency, etc.)
        return parent::is_available();
    }

    /**
     * Display payment fields on checkout
     */
    public function payment_fields() {
        // Display description
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }

        // Get cart total
        $total = WC()->cart->get_total( 'raw' );
        
        // Display QR code and upload form
        $this->display_qr_code( $total );
        $this->display_upload_form();
    }

    /**
     * Display QR code
     */
    private function display_qr_code( $amount ) {
        // Generate QR code data
        $qr_data = PromptPay_QR_Generator::generate_qr_data( $this->promptpay_id, $amount );
        
        if ( ! $qr_data ) {
            ?>
            <div class="promptpay-qr-container">
                <div class="woocommerce-error">
                    <p><?php _e( 'Invalid PromptPay ID. Please contact the store administrator.', 'woo-promptpay-clean' ); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Generate QR code image URL
        $qr_image_url = PromptPay_QR_Generator::generate_qr_image_url( $qr_data, 250 );
        
        ?>
        <div class="promptpay-qr-container">
            <h4><?php _e( 'Scan QR Code to Pay', 'woo-promptpay-clean' ); ?></h4>
            <div class="qr-code-image">
                <img src="<?php echo esc_url( $qr_image_url ); ?>" alt="PromptPay QR Code" />
            </div>
            <p><strong><?php printf( __( 'Amount: %s', 'woo-promptpay-clean' ), wc_price( $amount ) ); ?></strong></p>
            <p><small><?php _e( 'Open your mobile banking app and scan this QR code to pay', 'woo-promptpay-clean' ); ?></small></p>
        </div>
        <?php
    }

    /**
     * Display slip upload form
     */
    private function display_upload_form() {
        ?>
        <div class="promptpay-upload-container">
            <h4><?php _e( 'Upload Payment Slip', 'woo-promptpay-clean' ); ?></h4>
            <p><?php _e( 'After making payment, please upload your payment slip for verification:', 'woo-promptpay-clean' ); ?></p>
            
            <div class="form-row">
                <label for="promptpay_slip_clean"><?php _e( 'Payment Slip', 'woo-promptpay-clean' ); ?> <span class="required">*</span></label>
                <input type="file" id="promptpay_slip_clean" name="promptpay_slip_clean" accept="image/*,.pdf" required />
                <small><?php _e( 'Accepted formats: JPG, PNG, PDF (Max 5MB)', 'woo-promptpay-clean' ); ?></small>
            </div>
            
            <div class="form-row">
                <button type="button" id="upload_slip_btn_clean" class="button" disabled><?php _e( 'Upload & Verify', 'woo-promptpay-clean' ); ?></button>
            </div>
            
            <div id="upload_status_clean"></div>
            
            <input type="hidden" id="payment_verified_clean" name="payment_verified_clean" value="0" />
        </div>
        <?php
    }

    /**
     * Validate payment fields
     */
    public function validate_fields() {
        // Check if payment slip was uploaded and verified
        if ( empty( $_POST['payment_verified_clean'] ) || '1' !== $_POST['payment_verified_clean'] ) {
            wc_add_notice( __( 'Please upload and verify your payment slip before placing the order.', 'woo-promptpay-clean' ), 'error' );
            return false;
        }
        
        return true;
    }

    /**
     * Process the payment
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Mark as pending payment (awaiting slip verification)
        $order->update_status( 'pending', __( 'Awaiting PromptPay payment verification', 'woo-promptpay-clean' ) );

        // Add order note
        $order->add_order_note( __( 'PromptPay payment slip uploaded. Awaiting verification.', 'woo-promptpay-clean' ) );

        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    /**
     * Output for the order received page (Thank You page)
     */
    public function thankyou_page( $order_id ) {
        if ( $this->instructions ) {
            echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
        }
        $this->display_payment_instructions( $order_id );
    }

    /**
     * Display payment instructions on thank you page
     */
    private function display_payment_instructions( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( $order->has_status( 'pending' ) ) {
            ?>
            <div class="woocommerce-message">
                <h3><?php _e( 'Payment Instructions', 'woo-promptpay-clean' ); ?></h3>
                <p><?php _e( 'Your payment slip has been uploaded and is being verified. You will receive an email confirmation once the payment is approved.', 'woo-promptpay-clean' ); ?></p>
                <p><strong><?php printf( __( 'Order Number: %s', 'woo-promptpay-clean' ), $order->get_order_number() ); ?></strong></p>
                <p><strong><?php printf( __( 'Total Amount: %s', 'woo-promptpay-clean' ), $order->get_formatted_order_total() ); ?></strong></p>
            </div>
            <?php
        }
    }

    /**
     * Add content to the WC emails
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'pending' ) ) {
            echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
        }
    }

    /**
     * Enqueue payment scripts
     */
    public function payment_scripts() {
        // Only load on checkout page
        if ( ! is_checkout() ) {
            return;
        }

        // Enqueue script
        wp_enqueue_script( 
            'wc-promptpay-clean-checkout', 
            plugin_dir_url( __FILE__ ) . '../assets/js/checkout-clean.js', 
            array( 'jquery' ), 
            WC_PROMPTPAY_CLEAN_VERSION, 
            true 
        );

        // Localize script
        wp_localize_script( 'wc-promptpay-clean-checkout', 'promptpay_clean_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'promptpay_clean_upload_nonce' ),
            'messages' => array(
                'uploading' => __( 'Uploading...', 'woo-promptpay-clean' ),
                'verifying' => __( 'Verifying payment...', 'woo-promptpay-clean' ),
                'verified' => __( 'Payment verified successfully!', 'woo-promptpay-clean' ),
                'error' => __( 'Upload failed. Please try again.', 'woo-promptpay-clean' ),
            )
        ));

        // Enqueue styles
        wp_enqueue_style( 
            'wc-promptpay-clean-checkout', 
            plugin_dir_url( __FILE__ ) . '../assets/css/checkout-clean.css', 
            array(), 
            WC_PROMPTPAY_CLEAN_VERSION 
        );
    }

    /**
     * Handle slip upload via AJAX
     */
    public function handle_slip_upload() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'promptpay_clean_upload_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'woo-promptpay-clean' ) ) );
        }

        // Check if file was uploaded
        if ( empty( $_FILES['payment_slip']['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No payment slip uploaded.', 'woo-promptpay-clean' ) ) );
        }

        // Validate file
        $file = $_FILES['payment_slip'];
        $allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'application/pdf' );
        $max_size = 5 * 1024 * 1024; // 5MB

        if ( ! in_array( $file['type'], $allowed_types ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload JPG, PNG, or PDF.', 'woo-promptpay-clean' ) ) );
        }

        if ( $file['size'] > $max_size ) {
            wp_send_json_error( array( 'message' => __( 'File size too large. Maximum 5MB allowed.', 'woo-promptpay-clean' ) ) );
        }

        // Process verification (simulate for now)
        $verification_result = $this->verify_payment_slip( $file );

        if ( $verification_result['success'] ) {
            wp_send_json_success( array( 
                'message' => __( 'Payment verified successfully! You can now place your order.', 'woo-promptpay-clean' ),
                'verified' => true
            ) );
        } else {
            wp_send_json_error( array( 
                'message' => $verification_result['message'] ?: __( 'Payment verification failed. Please check your payment slip.', 'woo-promptpay-clean' )
            ) );
        }
    }

    /**
     * Verify payment slip (integrate with n8n)
     */
    private function verify_payment_slip( $file ) {
        // For demo purposes, simulate verification
        // In production, integrate with n8n webhook
        
        if ( empty( $this->n8n_webhook_url ) ) {
            return array(
                'success' => false,
                'message' => __( 'n8n webhook URL not configured.', 'woo-promptpay-clean' )
            );
        }

        // Simulate processing delay
        sleep( 1 );

        // Simulate 90% success rate for demo
        $success_rate = 0.9;
        $is_verified = ( mt_rand() / mt_getrandmax() ) < $success_rate;

        if ( $is_verified ) {
            return array(
                'success' => true,
                'message' => __( 'Payment slip verified successfully.', 'woo-promptpay-clean' )
            );
        } else {
            return array(
                'success' => false,
                'message' => __( 'Payment verification failed. Please ensure your payment slip is clear and matches the order amount.', 'woo-promptpay-clean' )
            );
        }
    }
}
