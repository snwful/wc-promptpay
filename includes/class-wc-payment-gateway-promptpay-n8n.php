<?php
/**
 * PromptPay n8n Payment Gateway Class
 *
 * @package PromptPay_N8N_Gateway
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptPay n8n Payment Gateway
 */
class WC_Payment_Gateway_PromptPay_N8N extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'promptpay_n8n';
        $this->icon               = PROMPTPAY_N8N_PLUGIN_URL . 'assets/images/promptpay-logo.png';
        $this->has_fields         = true;
        $this->method_title       = __( 'PromptPay n8n Gateway', 'promptpay-n8n-gateway' );
        $this->method_description = __( 'Accept payments via PromptPay QR code with n8n webhook verification.', 'promptpay-n8n-gateway' );
        
        // Set gateway supports
        $this->supports = array(
            'products',
            'refunds'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->promptpay_id       = $this->get_option( 'promptpay_id' );
        $this->n8n_webhook_url    = $this->get_option( 'n8n_webhook_url' );
        $this->max_upload_attempts = $this->get_option( 'max_upload_attempts', 3 );
        $this->shared_secret_key  = $this->get_option( 'shared_secret_key' );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'promptpay-n8n-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PromptPay Payment', 'promptpay-n8n-gateway' ),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __( 'Title', 'promptpay-n8n-gateway' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'promptpay-n8n-gateway' ),
                'default'     => __( 'PromptPay', 'promptpay-n8n-gateway' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'promptpay-n8n-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'promptpay-n8n-gateway' ),
                'default'     => __( 'Pay with PromptPay by scanning the QR code with your mobile banking app.', 'promptpay-n8n-gateway' ),
                'desc_tip'    => true,
            ),
            'promptpay_id' => array(
                'title'       => __( 'PromptPay ID', 'promptpay-n8n-gateway' ),
                'type'        => 'text',
                'description' => __( 'Enter your PromptPay ID (phone number or National ID).', 'promptpay-n8n-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => '0864639798'
            ),
            'n8n_webhook_url' => array(
                'title'       => __( 'n8n Webhook URL', 'promptpay-n8n-gateway' ),
                'type'        => 'url',
                'description' => __( 'Enter the n8n webhook URL for payment verification.', 'promptpay-n8n-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => 'https://your-n8n-instance.com/webhook/promptpay-verify'
            ),
            'max_upload_attempts' => array(
                'title'       => __( 'Max Upload Attempts', 'promptpay-n8n-gateway' ),
                'type'        => 'number',
                'description' => __( 'Maximum number of payment slip upload attempts allowed per order.', 'promptpay-n8n-gateway' ),
                'default'     => 3,
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'min' => 1,
                    'max' => 10
                )
            ),
            'shared_secret_key' => array(
                'title'       => __( 'Shared Secret Key', 'promptpay-n8n-gateway' ),
                'type'        => 'password',
                'description' => __( 'Secret key for securing the webhook callback from n8n.', 'promptpay-n8n-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Check if this gateway is available in the user's country
     *
     * @return bool
     */
    public function is_available() {
        // Basic availability check
        if ( 'yes' !== $this->enabled ) {
            return false;
        }

        // For testing purposes, let's make it always available when enabled
        // Remove the cart check for now to debug
        // if ( ! WC()->cart || WC()->cart->is_empty() ) {
        //     return false;
        // }

        // Don't require webhook URL for basic availability (admin can configure later)
        // if ( empty( $this->n8n_webhook_url ) ) {
        //     return false;
        // }

        // Always return true when enabled for debugging
        return true;
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }

        // Get current order total
        $total = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;
        
        if ( $total > 0 && ! empty( $this->promptpay_id ) ) {
            echo '<div id="promptpay-qr-section" style="margin: 20px 0;">';
            echo '<h4>' . esc_html__( 'วิธีการชำระเงิน', 'promptpay-n8n-gateway' ) . '</h4>';
            
            // Generate QR code
            $qr_generator = new PromptPay_N8N_QR_Generator();
            $qr_code_data = $qr_generator->generate_qr_data( $this->promptpay_id, $total );
            $qr_code_url = $qr_generator->generate_qr_code_url( $qr_code_data );
            
            echo '<div class="promptpay-qr-container" style="text-align: center; margin: 20px 0;">';
            echo '<img src="' . esc_url( $qr_code_url ) . '" alt="' . esc_attr__( 'PromptPay QR Code', 'promptpay-n8n-gateway' ) . '" style="max-width: 300px; height: auto;" />';
            echo '<p><strong>' . esc_html__( 'ยอดชำระ:', 'promptpay-n8n-gateway' ) . ' ฿' . number_format( $total, 2 ) . '</strong></p>';
            echo '</div>';

            // Payment instructions
            echo '<div class="promptpay-instructions">';
            echo '<h5>' . esc_html__( 'วิธีการชำระเงิน:', 'promptpay-n8n-gateway' ) . '</h5>';
            echo '<ol>';
            echo '<li>' . esc_html__( 'เปิดแอปโมบายแบงก์กิ้งของคุณ', 'promptpay-n8n-gateway' ) . '</li>';
            echo '<li>' . esc_html__( 'สแกน QR Code ด้านบน', 'promptpay-n8n-gateway' ) . '</li>';
            echo '<li>' . esc_html__( 'ตรวจสอบยอดเงินให้ถูกต้อง', 'promptpay-n8n-gateway' ) . '</li>';
            echo '<li>' . esc_html__( 'ยืนยันการโอนเงิน', 'promptpay-n8n-gateway' ) . '</li>';
            echo '<li>' . esc_html__( 'อัปโหลดสลิปการโอนเงิน', 'promptpay-n8n-gateway' ) . '</li>';
            echo '</ol>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'promptpay-n8n-gateway' ), 'error' );
            return array(
                'result'   => 'failure',
                'redirect' => ''
            );
        }

        // Mark as awaiting slip upload
        $order->update_status( 'awaiting-slip', __( 'Order placed. Awaiting payment slip upload.', 'promptpay-n8n-gateway' ) );

        // Store gateway-specific data
        $order->update_meta_data( '_promptpay_n8n_upload_attempts', 0 );
        $order->update_meta_data( '_promptpay_n8n_max_attempts', $this->max_upload_attempts );
        $order->save();

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    /**
     * Output for the order received page
     *
     * @param int $order_id
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        
        if ( ! $order || $order->get_payment_method() !== $this->id ) {
            return;
        }

        $order_status = $order->get_status();
        $upload_attempts = (int) $order->get_meta( '_promptpay_n8n_upload_attempts', true );
        $max_attempts = (int) $order->get_meta( '_promptpay_n8n_max_attempts', true );

        echo '<div class="promptpay-thankyou-section">';

        if ( 'awaiting-slip' === $order_status ) {
            $this->display_payment_instructions( $order );
            $this->display_upload_form( $order_id, $upload_attempts, $max_attempts );
        } elseif ( 'pending-verification' === $order_status ) {
            echo '<div class="woocommerce-message">';
            echo '<p>' . esc_html__( 'Your payment slip has been uploaded successfully. We are verifying your payment and will update your order status shortly.', 'promptpay-n8n-gateway' ) . '</p>';
            echo '</div>';
        } elseif ( in_array( $order_status, array( 'processing', 'completed' ) ) ) {
            echo '<div class="woocommerce-message">';
            echo '<p>' . esc_html__( 'Payment verified successfully! Thank you for your order.', 'promptpay-n8n-gateway' ) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Display payment instructions
     *
     * @param WC_Order $order
     */
    private function display_payment_instructions( $order ) {
        $total = $order->get_total();
        
        echo '<div class="promptpay-payment-section">';
        echo '<h3>' . esc_html__( 'วิธีการชำระเงิน', 'promptpay-n8n-gateway' ) . '</h3>';
        
        if ( ! empty( $this->promptpay_id ) ) {
            // Generate QR code for this specific order
            $qr_generator = new PromptPay_N8N_QR_Generator();
            $qr_code_data = $qr_generator->generate_qr_data( $this->promptpay_id, $total );
            $qr_code_url = $qr_generator->generate_qr_code_url( $qr_code_data );
            
            echo '<div class="promptpay-qr-container" style="text-align: center; margin: 20px 0; padding: 20px; border: 2px dashed #ffa500; background-color: #fff8e1;">';
            echo '<img src="' . esc_url( $qr_code_url ) . '" alt="' . esc_attr__( 'PromptPay QR Code', 'promptpay-n8n-gateway' ) . '" style="max-width: 300px; height: auto;" />';
            echo '<p><strong>' . esc_html__( 'ยอดชำระ:', 'promptpay-n8n-gateway' ) . ' ฿' . number_format( $total, 2 ) . '</strong></p>';
            echo '</div>';
        }

        // Payment instructions
        echo '<div class="promptpay-instructions" style="margin: 20px 0;">';
        echo '<h4>' . esc_html__( 'วิธีการชำระเงิน:', 'promptpay-n8n-gateway' ) . '</h4>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'เปิดแอปโมบายแบงก์กิ้งของคุณ', 'promptpay-n8n-gateway' ) . '</li>';
        echo '<li>' . esc_html__( 'สแกน QR Code ด้านบน', 'promptpay-n8n-gateway' ) . '</li>';
        echo '<li>' . esc_html__( 'ตรวจสอบยอดเงินให้ถูกต้อง', 'promptpay-n8n-gateway' ) . '</li>';
        echo '<li>' . esc_html__( 'ยืนยันการโอนเงิน', 'promptpay-n8n-gateway' ) . '</li>';
        echo '<li>' . esc_html__( 'อัปโหลดสลิปการโอนเงิน', 'promptpay-n8n-gateway' ) . '</li>';
        echo '</ol>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display upload form
     *
     * @param int $order_id
     * @param int $upload_attempts
     * @param int $max_attempts
     */
    private function display_upload_form( $order_id, $upload_attempts, $max_attempts ) {
        if ( $upload_attempts >= $max_attempts ) {
            echo '<div class="woocommerce-error">';
            echo '<p>' . sprintf( 
                esc_html__( 'Maximum upload attempts (%d) reached. Please contact support for assistance.', 'promptpay-n8n-gateway' ), 
                $max_attempts 
            ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="promptpay-upload-section" style="margin: 30px 0; padding: 20px; border: 1px dashed #ccc; background-color: #f9f9f9;">';
        echo '<h4>' . esc_html__( 'อัปโหลดสลิปการโอนเงิน:', 'promptpay-n8n-gateway' ) . '</h4>';
        
        echo '<form id="promptpay-slip-upload-form" enctype="multipart/form-data">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '" />';
        echo '<input type="hidden" name="nonce" value="' . wp_create_nonce( 'promptpay_n8n_nonce' ) . '" />';
        
        echo '<div class="upload-field" style="margin: 15px 0;">';
        echo '<label for="payment_slip">' . esc_html__( 'เลือกไฟล์สลิป:', 'promptpay-n8n-gateway' ) . '</label><br>';
        echo '<input type="file" id="payment_slip" name="payment_slip" accept=".jpg,.jpeg,.png,.pdf" required style="margin: 10px 0;" />';
        echo '<p class="description">' . esc_html__( 'รองรับไฟล์: JPG, PNG, PDF (ขนาดไม่เกิน 5MB)', 'promptpay-n8n-gateway' ) . '</p>';
        echo '</div>';
        
        echo '<button type="submit" class="button alt" id="upload-slip-btn">' . esc_html__( 'อัปโหลดสลิป', 'promptpay-n8n-gateway' ) . '</button>';
        echo '<div id="upload-progress" style="display: none; margin: 10px 0;"></div>';
        echo '<div id="upload-result" style="margin: 10px 0;"></div>';
        echo '</form>';
        
        if ( $upload_attempts > 0 ) {
            echo '<p class="upload-attempts">' . sprintf( 
                esc_html__( 'Upload attempts: %d/%d', 'promptpay-n8n-gateway' ), 
                $upload_attempts, 
                $max_attempts 
            ) . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Add content to the WC emails
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->id !== $order->get_payment_method() || ! in_array( $order->get_status(), array( 'awaiting-slip', 'pending-verification' ) ) ) {
            return;
        }

        if ( $plain_text ) {
            echo esc_html__( 'Please upload your payment slip to complete the order.', 'promptpay-n8n-gateway' ) . "\n\n";
        } else {
            echo '<h2>' . esc_html__( 'Payment Instructions', 'promptpay-n8n-gateway' ) . '</h2>';
            echo '<p>' . esc_html__( 'Please upload your payment slip to complete the order.', 'promptpay-n8n-gateway' ) . '</p>';
        }
    }
}
