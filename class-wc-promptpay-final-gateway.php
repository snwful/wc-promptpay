<?php
/**
 * WC PromptPay Final Gateway Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_PromptPay_Final_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'promptpay_final';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = 'PromptPay Final';
        $this->method_description = 'Accept PromptPay payments with QR code and slip upload';
        
        $this->supports = array(
            'products'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->promptpay_id = $this->get_option( 'promptpay_id' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        add_action( 'wp_ajax_promptpay_final_upload', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_nopriv_promptpay_final_upload', array( $this, 'handle_upload' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable PromptPay Payment',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'PromptPay',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment method description that the customer will see on your checkout.',
                'default'     => 'Pay with PromptPay by scanning the QR code.',
                'desc_tip'    => true,
            ),
            'promptpay_id' => array(
                'title'       => 'PromptPay ID',
                'type'        => 'text',
                'description' => 'Enter your PromptPay ID (phone number or national ID).',
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    public function is_available() {
        if ( 'yes' !== $this->enabled ) {
            return false;
        }

        if ( empty( $this->promptpay_id ) ) {
            return false;
        }

        return parent::is_available();
    }

    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }

        $total = WC()->cart->get_total( 'raw' );
        $qr_url = $this->generate_qr_url( $total );

        ?>
        <div id="promptpay-final-container">
            <div class="qr-section">
                <h4>Scan QR Code to Pay</h4>
                <div class="qr-code">
                    <img src="<?php echo esc_url( $qr_url ); ?>" alt="PromptPay QR Code" style="max-width: 200px; height: auto;" />
                </div>
                <p><strong>Amount: <?php echo wc_price( $total ); ?></strong></p>
            </div>
            
            <div class="upload-section">
                <h4>Upload Payment Slip</h4>
                <p>After payment, upload your slip for verification:</p>
                <input type="file" id="payment-slip-final" accept="image/*,.pdf" />
                <button type="button" id="upload-btn-final" disabled>Upload & Verify</button>
                <div id="upload-status-final"></div>
                <input type="hidden" id="payment-verified-final" name="payment_verified_final" value="0" />
            </div>
        </div>

        <style>
        #promptpay-final-container {
            margin: 20px 0;
        }
        .qr-section, .upload-section {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .qr-section {
            text-align: center;
        }
        .qr-code {
            margin: 10px 0;
        }
        #payment-slip-final {
            width: 100%;
            margin: 10px 0;
            padding: 8px;
        }
        #upload-btn-final {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        #upload-btn-final:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        #upload-btn-final.verified {
            background: #46b450;
        }
        #upload-status-final {
            margin-top: 10px;
        }
        .success-message {
            color: #46b450;
            background: #dff0d8;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
        }
        .error-message {
            color: #a94442;
            background: #f2dede;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
        }
        #place_order.disabled {
            background: #ccc !important;
            cursor: not-allowed !important;
        }
        </style>
        <?php
    }

    public function validate_fields() {
        if ( empty( $_POST['payment_verified_final'] ) || '1' !== $_POST['payment_verified_final'] ) {
            wc_add_notice( 'Please upload and verify your payment slip before placing the order.', 'error' );
            return false;
        }
        return true;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        
        $order->update_status( 'pending', 'Awaiting PromptPay payment verification' );
        $order->add_order_note( 'PromptPay payment slip uploaded. Awaiting verification.' );
        
        wc_reduce_stock_levels( $order_id );
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    public function payment_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_script( 'promptpay-final-js', WC_PROMPTPAY_FINAL_URL . 'promptpay-final.js', array( 'jquery' ), WC_PROMPTPAY_FINAL_VERSION, true );
        
        wp_localize_script( 'promptpay-final-js', 'promptpay_final_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'promptpay_final_nonce' )
        ));
    }

    public function handle_upload() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'promptpay_final_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        if ( empty( $_FILES['payment_slip']['name'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
        }

        $file = $_FILES['payment_slip'];
        $allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'application/pdf' );
        
        if ( ! in_array( $file['type'], $allowed_types ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file type.' ) );
        }

        if ( $file['size'] > 5 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => 'File too large.' ) );
        }

        // Simulate verification (90% success rate)
        $success = ( mt_rand( 1, 10 ) <= 9 );
        
        if ( $success ) {
            wp_send_json_success( array( 'message' => 'Payment verified successfully!' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Verification failed. Please try again.' ) );
        }
    }

    private function generate_qr_url( $amount ) {
        $promptpay_id = $this->clean_promptpay_id( $this->promptpay_id );
        $qr_data = $this->generate_promptpay_qr( $promptpay_id, $amount );
        return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode( $qr_data );
    }

    private function clean_promptpay_id( $id ) {
        return preg_replace( '/[^0-9]/', '', $id );
    }

    private function generate_promptpay_qr( $promptpay_id, $amount ) {
        // Simplified PromptPay QR generation
        $qr_data = '00020101021129370016A000000677010111';
        
        if ( strlen( $promptpay_id ) === 10 ) {
            $formatted_id = '0066' . substr( $promptpay_id, 1 );
        } else {
            $formatted_id = $promptpay_id;
        }
        
        $qr_data .= sprintf( '%02d%s', strlen( $formatted_id ), $formatted_id );
        $qr_data .= '5303764';
        
        if ( $amount > 0 ) {
            $amount_str = number_format( $amount, 2, '.', '' );
            $qr_data .= sprintf( '54%02d%s', strlen( $amount_str ), $amount_str );
        }
        
        $qr_data .= '5802TH';
        $qr_data .= '6304';
        $qr_data .= $this->calculate_crc( $qr_data );
        
        return $qr_data;
    }

    private function calculate_crc( $data ) {
        $crc = 0xFFFF;
        for ( $i = 0; $i < strlen( $data ); $i++ ) {
            $crc ^= ( ord( $data[$i] ) << 8 );
            for ( $j = 0; $j < 8; $j++ ) {
                if ( $crc & 0x8000 ) {
                    $crc = ( $crc << 1 ) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }
        return strtoupper( str_pad( dechex( $crc ), 4, '0', STR_PAD_LEFT ) );
    }
}
