<?php
namespace WooPromptPay\Gateway;

use WooPromptPay\Helpers\PP_QR_Generator;
use WooPromptPay\Handlers\PP_Upload_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptPay Payment Gateway Class
 * 
 * Handles WooCommerce payment gateway integration for PromptPay
 */
class PP_Payment_Gateway extends \WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'promptpay_n8n';
        $this->icon               = '';
        $this->has_fields         = true; // Enable fields for pre-checkout verification
        $this->method_title       = __( 'PromptPay (n8n)', 'woo-promptpay-n8n' );
        $this->method_description = __( 'Accept payments via PromptPay QR code with n8n webhook confirmation.', 'woo-promptpay-n8n' );
        
        // Gateway supports
        $this->supports = [
            'products'
        ];
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title            = $this->get_option( 'title' );
        $this->description      = $this->get_option( 'description' );
        $this->enabled          = $this->get_option( 'enabled' );
        $this->promptpay_id     = $this->get_option( 'promptpay_id' );
        $this->n8n_webhook_url  = $this->get_option( 'n8n_webhook_url' );
        $this->max_attempts     = $this->get_option( 'max_attempts', 3 );
        
        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
        add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
        
        // Add checkout scripts and styles
        add_action( 'wp_enqueue_scripts', [ $this, 'checkout_scripts' ] );
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'woo-promptpay-n8n' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PromptPay Payment', 'woo-promptpay-n8n' ),
                'default' => 'yes'
            ],
            'title' => [
                'title'       => __( 'Title', 'woo-promptpay-n8n' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woo-promptpay-n8n' ),
                'default'     => __( 'PromptPay', 'woo-promptpay-n8n' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'woo-promptpay-n8n' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-promptpay-n8n' ),
                'default'     => __( 'Pay with PromptPay by scanning the QR code with your mobile banking app.', 'woo-promptpay-n8n' ),
                'desc_tip'    => true,
            ],
            'promptpay_id' => [
                'title'       => __( 'PromptPay ID', 'woo-promptpay-n8n' ),
                'type'        => 'text',
                'description' => __( 'Enter your PromptPay ID (phone number, citizen ID, or e-wallet ID).', 'woo-promptpay-n8n' ),
                'desc_tip'    => true,
            ],
            'n8n_webhook_url' => [
                'title'       => __( 'n8n Webhook URL', 'woo-promptpay-n8n' ),
                'type'        => 'url',
                'description' => __( 'Enter the n8n webhook URL for payment confirmation.', 'woo-promptpay-n8n' ),
                'desc_tip'    => true,
            ],
            'max_attempts' => [
                'title'       => __( 'Max Upload Attempts', 'woo-promptpay-n8n' ),
                'type'        => 'number',
                'description' => __( 'Maximum number of slip upload attempts per order.', 'woo-promptpay-n8n' ),
                'default'     => 3,
                'custom_attributes' => [
                    'min' => 1,
                    'max' => 10
                ],
                'desc_tip'    => true,
            ],
        ];
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        if ( 'yes' !== $this->enabled ) {
            return false;
        }
        
        if ( empty( $this->promptpay_id ) ) {
            return false;
        }
        
        return parent::is_available();
    }
    
    /**
     * Display payment fields on checkout
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        
        // Get cart total for QR code
        $total = WC()->cart->get_total( 'raw' );
        
        echo '<div id="promptpay-checkout-section" style="margin-top: 15px;">';
        
        // Display QR code
        echo '<div class="promptpay-qr-checkout">';
        echo '<h4>' . __( 'Scan QR Code to Pay', 'woo-promptpay-n8n' ) . '</h4>';
        
        $qr_generator = new PP_QR_Generator();
        $qr_code_url = $qr_generator->generate_qr_code( $this->promptpay_id, $total );
        
        if ( $qr_code_url ) {
            echo '<div style="text-align: center; margin: 15px 0;">';
            echo '<img src="' . esc_url( $qr_code_url ) . '" alt="PromptPay QR Code" style="max-width: 200px; height: auto;" />';
            echo '<p><strong>' . __( 'Amount:', 'woo-promptpay-n8n' ) . ' ฿' . number_format( $total, 2 ) . '</strong></p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Payment verification section
        echo '<div class="promptpay-verification-checkout">';
        echo '<h4>' . __( 'Payment Verification', 'woo-promptpay-n8n' ) . '</h4>';
        echo '<p>' . __( 'After making the payment, upload your payment slip to verify the transaction:', 'woo-promptpay-n8n' ) . '</p>';
        
        echo '<div class="form-row form-row-wide">';
        echo '<label for="payment_slip">' . __( 'Payment Slip', 'woo-promptpay-n8n' ) . ' <span class="required">*</span></label>';
        echo '<input type="file" id="payment_slip" name="payment_slip" accept="image/*,.pdf" required />';
        echo '<small>' . __( 'Upload your payment slip (JPG, PNG, or PDF, max 5MB)', 'woo-promptpay-n8n' ) . '</small>';
        echo '</div>';
        
        echo '<div id="verification-status" style="margin-top: 10px;"></div>';
        echo '<input type="hidden" id="payment_verified" name="payment_verified" value="0" />';
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Validate payment fields
     */
    public function validate_fields() {
        if ( empty( $_FILES['payment_slip']['name'] ) ) {
            wc_add_notice( __( 'Payment slip is required.', 'woo-promptpay-n8n' ), 'error' );
            return false;
        }
        
        if ( empty( $_POST['payment_verified'] ) || '1' !== $_POST['payment_verified'] ) {
            wc_add_notice( __( 'Please wait for payment verification to complete before placing the order.', 'woo-promptpay-n8n' ), 'error' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Process the payment and return the result
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            return [
                'result'   => 'failure',
                'messages' => __( 'Order not found.', 'woo-promptpay-n8n' )
            ];
        }
        
        // Process uploaded slip if payment was verified
        if ( ! empty( $_FILES['payment_slip']['name'] ) && '1' === $_POST['payment_verified'] ) {
            $upload_handler = new PP_Upload_Handler();
            $upload_result = $upload_handler->handle_upload( $_FILES['payment_slip'], $order_id );
            
            if ( $upload_result['success'] ) {
                $order->add_order_note( __( 'Payment slip uploaded during checkout and verified.', 'woo-promptpay-n8n' ) );
                // Mark as processing since payment was verified
                $order->update_status( 'processing', __( 'PromptPay payment verified and confirmed.', 'woo-promptpay-n8n' ) );
            } else {
                $order->update_status( 'pending', __( 'Awaiting PromptPay payment confirmation.', 'woo-promptpay-n8n' ) );
            }
        } else {
            // Mark as pending payment
            $order->update_status( 'pending', __( 'Awaiting PromptPay payment confirmation.', 'woo-promptpay-n8n' ) );
        }
        
        // Initialize upload attempt counter
        $order->update_meta_data( '_pp_attempt_count', 0 );
        $order->save();
        
        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );
        
        // Remove cart
        WC()->cart->empty_cart();
        
        // Return success and redirect to the thank you page
        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        ];
    }

    /**
     * Output for the order received page (Thank You page)
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order || $order->get_payment_method() !== $this->id ) {
            return;
        }
        
        $this->display_payment_instructions( $order );
    }
    
    /**
     * Display payment instructions with QR code and upload form
     */
    private function display_payment_instructions( $order ) {
        $order_id = $order->get_id();
        $amount = $order->get_total();
        $attempt_count = (int) $order->get_meta( '_pp_attempt_count', true );
        $remaining_attempts = max( 0, $this->max_attempts - $attempt_count );
        
        // Generate QR code
        $qr_data_uri = PP_QR_Generator::generate_qr_data_uri( $this->promptpay_id, $amount );
        
        echo '<div class="woocommerce-order-details">';
        echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'PromptPay Payment', 'woo-promptpay-n8n' ) . '</h2>';
        
        // Payment instructions
        echo '<div class="promptpay-instructions">';
        echo '<p>' . esc_html__( 'Please follow these steps to complete your payment:', 'woo-promptpay-n8n' ) . '</p>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Scan the QR code below with your mobile banking app', 'woo-promptpay-n8n' ) . '</li>';
        echo '<li>' . esc_html__( 'Complete the payment transfer', 'woo-promptpay-n8n' ) . '</li>';
        echo '<li>' . esc_html__( 'Upload your payment slip using the form below', 'woo-promptpay-n8n' ) . '</li>';
        echo '</ol>';
        echo '</div>';
        
        // QR Code display
        echo '<div class="promptpay-qr-section" style="text-align: center; margin: 20px 0;">';
        echo '<h3>' . esc_html__( 'Scan QR Code to Pay', 'woo-promptpay-n8n' ) . '</h3>';
        if ( $qr_data_uri ) {
            echo '<img src="' . esc_attr( $qr_data_uri ) . '" alt="PromptPay QR Code" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 10px;" />';
        } else {
            echo '<p>' . esc_html__( 'QR Code could not be generated. Please contact support.', 'woo-promptpay-n8n' ) . '</p>';
        }
        echo '<p><strong>' . esc_html__( 'Amount:', 'woo-promptpay-n8n' ) . '</strong> ' . wc_price( $amount ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Order ID:', 'woo-promptpay-n8n' ) . '</strong> ' . $order->get_order_number() . '</p>';
        echo '</div>';
        
        // Upload form
        if ( $remaining_attempts > 0 ) {
            $this->display_upload_form( $order );
        } else {
            echo '<div class="woocommerce-error">';
            echo esc_html__( 'You have reached the maximum number of upload attempts for this order.', 'woo-promptpay-n8n' );
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add some basic CSS
        $this->add_inline_styles();
    }
    
    /**
     * Display slip upload form
     */
    private function display_upload_form( $order ) {
        $order_id = $order->get_id();
        $amount = $order->get_total();
        $nonce = wp_create_nonce( 'ppn8n_upload_' . $order_id );
        $attempt_count = (int) $order->get_meta( '_pp_attempt_count', true );
        $remaining_attempts = max( 0, $this->max_attempts - $attempt_count );
        
        echo '<div class="promptpay-upload-section">';
        echo '<h3>' . esc_html__( 'Upload Payment Slip', 'woo-promptpay-n8n' ) . '</h3>';
        echo '<p>' . sprintf( 
            esc_html__( 'Upload attempts remaining: %d', 'woo-promptpay-n8n' ), 
            $remaining_attempts 
        ) . '</p>';
        
        echo '<form id="ppn8n-slip-form" method="post" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="ppn8n_upload_slip" />';
        echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '" />';
        echo '<input type="hidden" name="amount" value="' . esc_attr( $amount ) . '" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr( $nonce ) . '" />';
        
        echo '<table class="form-table">';
        
        // Order ID field (auto-filled, readonly)
        echo '<tr>';
        echo '<th><label for="display_order_id">' . esc_html__( 'Order ID', 'woo-promptpay-n8n' ) . '</label></th>';
        echo '<td><input type="text" id="display_order_id" value="' . esc_attr( $order->get_order_number() ) . '" readonly style="background-color: #f9f9f9;" /></td>';
        echo '</tr>';
        
        // Amount field (auto-filled, readonly)
        echo '<tr>';
        echo '<th><label for="display_amount">' . esc_html__( 'Amount', 'woo-promptpay-n8n' ) . '</label></th>';
        echo '<td><input type="text" id="display_amount" value="' . esc_attr( number_format( $amount, 2 ) ) . '" readonly style="background-color: #f9f9f9;" /></td>';
        echo '</tr>';
        
        // File upload field
        echo '<tr>';
        echo '<th><label for="slip_file">' . esc_html__( 'Payment Slip', 'woo-promptpay-n8n' ) . '</label></th>';
        echo '<td>';
        echo '<input type="file" id="slip_file" name="slip_file" accept="image/*,application/pdf" required />';
        echo '<p class="description">' . esc_html__( 'Supported formats: JPEG, PNG, PDF (Max size: 5MB)', 'woo-promptpay-n8n' ) . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p>';
        echo '<button type="submit" class="button alt" id="submit-slip">' . esc_html__( 'Upload Payment Slip', 'woo-promptpay-n8n' ) . '</button>';
        echo '</p>';
        
        echo '</form>';
        
        echo '<div id="upload-result" style="margin-top: 20px;"></div>';
        
        echo '</div>';
        
        // Add JavaScript for form handling
        $this->add_upload_script();
    }
    
    /**
     * Add JavaScript for handling slip upload
     */
    private function add_upload_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#ppn8n-slip-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitBtn = $('#submit-slip');
                var $result = $('#upload-result');
                
                // Disable submit button
                $submitBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Uploading...', 'woo-promptpay-n8n' ) ); ?>');
                
                // Clear previous results
                $result.html('');
                
                // Create FormData object
                var formData = new FormData(this);
                
                // Send AJAX request
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="woocommerce-message">' + response.data.message + '</div>');
                            // Reload page after successful upload
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="woocommerce-error">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="woocommerce-error"><?php echo esc_js( __( 'Upload failed. Please try again.', 'woo-promptpay-n8n' ) ); ?></div>');
                    },
                    complete: function() {
                        // Re-enable submit button
                        $submitBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Upload Payment Slip', 'woo-promptpay-n8n' ) ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add inline styles
     */
    private function add_inline_styles() {
        ?>
        <style type="text/css">
        .promptpay-instructions {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .promptpay-qr-section {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .promptpay-upload-section {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .promptpay-upload-section .form-table th {
            width: 150px;
            font-weight: bold;
        }
        .promptpay-upload-section .form-table td input[type="text"],
        .promptpay-upload-section .form-table td input[type="file"] {
            width: 100%;
            max-width: 400px;
        }
        </style>
        <?php
    }

    /**
     * Enqueue checkout scripts and styles
     */
    public function checkout_scripts() {
        if ( is_admin() || ! is_checkout() ) {
            return;
        }
        
        wp_enqueue_script( 'jquery' );
        
        // Add inline script for checkout functionality
        wp_add_inline_script( 'jquery', $this->get_checkout_script() );
        
        // Add inline styles
        wp_add_inline_style( 'woocommerce-general', $this->get_checkout_styles() );
    }
    
    /**
     * Get checkout JavaScript
     */
    private function get_checkout_script() {
        ob_start();
        ?>
        jQuery(document).ready(function($) {
            var paymentVerified = false;
            
            // Handle payment slip upload
            $(document).on('change', '#payment_slip', function() {
                var file = this.files[0];
                if (!file) return;
                
                // Validate file
                var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                var maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedTypes.includes(file.type)) {
                    alert('<?php echo esc_js( __( 'Please upload a valid image (JPG, PNG) or PDF file.', 'woo-promptpay-n8n' ) ); ?>');
                    $(this).val('');
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('<?php echo esc_js( __( 'File size must be less than 5MB.', 'woo-promptpay-n8n' ) ); ?>');
                    $(this).val('');
                    return;
                }
                
                // Show uploading status
                $('#verification-status').html('<div class="woocommerce-info"><?php echo esc_js( __( 'Uploading and verifying payment slip...', 'woo-promptpay-n8n' ) ); ?></div>');
                
                // Simulate upload and verification process
                uploadAndVerifySlip(file);
            });
            
            // Upload and verify slip function
            function uploadAndVerifySlip(file) {
                var formData = new FormData();
                formData.append('payment_slip', file);
                formData.append('action', 'ppn8n_verify_payment');
                formData.append('nonce', '<?php echo wp_create_nonce( 'ppn8n_verify_payment' ); ?>');
                
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            paymentVerified = true;
                            $('#payment_verified').val('1');
                            $('#verification-status').html('<div class="woocommerce-message">' + response.data.message + '</div>');
                            
                            // Enable place order button
                            $('#place_order').prop('disabled', false);
                        } else {
                            paymentVerified = false;
                            $('#payment_verified').val('0');
                            $('#verification-status').html('<div class="woocommerce-error">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        paymentVerified = false;
                        $('#payment_verified').val('0');
                        $('#verification-status').html('<div class="woocommerce-error"><?php echo esc_js( __( 'Verification failed. Please try again.', 'woo-promptpay-n8n' ) ); ?></div>');
                    }
                });
            }
            
            // Handle payment method change
            $(document).on('change', 'input[name="payment_method"]', function() {
                if ($(this).val() === 'promptpay_n8n') {
                    // Disable place order until payment is verified
                    $('#place_order').prop('disabled', true);
                } else {
                    // Enable place order for other payment methods
                    $('#place_order').prop('disabled', false);
                }
            });
            
            // Initial check
            if ($('input[name="payment_method"]:checked').val() === 'promptpay_n8n') {
                $('#place_order').prop('disabled', true);
            }
        });
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get checkout styles
     */
    private function get_checkout_styles() {
        return '
        .promptpay-qr-checkout {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        .promptpay-verification-checkout {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .promptpay-verification-checkout .form-row {
            margin-bottom: 15px;
        }
        .promptpay-verification-checkout label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }
        .promptpay-verification-checkout input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .promptpay-verification-checkout small {
            color: #666;
            font-size: 12px;
        }
        #verification-status {
            margin-top: 10px;
        }
        ';
    }
    
    /**
     * Add content to the WC emails
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'pending' ) ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
        }
    }
}
