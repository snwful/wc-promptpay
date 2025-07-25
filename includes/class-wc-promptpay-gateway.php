<?php
/**
 * WC PromptPay Gateway Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PromptPay_Gateway extends WC_Payment_Gateway {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'promptpay';
        $this->icon = WC_PROMPTPAY_PLUGIN_URL . 'assets/images/promptpay-logo.png';
        $this->has_fields = false;
        $this->method_title = __('PromptPay', 'wc-promptpay');
        $this->method_description = __('Accept payments via PromptPay QR code with automatic verification through n8n webhook.', 'wc-promptpay');
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->promptpay_id = $this->get_option('promptpay_id');
        $this->promptpay_type = $this->get_option('promptpay_type');
        $this->extra_message = $this->get_option('extra_message');
        $this->include_amount = $this->get_option('include_amount');
        $this->n8n_webhook_url = $this->get_option('n8n_webhook_url');
        $this->webhook_secret = $this->get_option('webhook_secret');
        $this->auto_complete = $this->get_option('auto_complete');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-promptpay'),
                'type' => 'checkbox',
                'label' => __('Enable PromptPay Payment', 'wc-promptpay'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'wc-promptpay'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-promptpay'),
                'default' => __('PromptPay', 'wc-promptpay'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'wc-promptpay'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'wc-promptpay'),
                'default' => __('Pay with PromptPay by scanning the QR code with your mobile banking app.', 'wc-promptpay'),
                'desc_tip' => true,
            ),
            'promptpay_settings' => array(
                'title' => __('PromptPay Settings', 'wc-promptpay'),
                'type' => 'title',
                'description' => __('Configure your PromptPay account details.', 'wc-promptpay'),
            ),
            'promptpay_id' => array(
                'title' => __('PromptPay ID', 'wc-promptpay'),
                'type' => 'text',
                'description' => __('Enter your PromptPay ID (phone number, citizen ID, or e-wallet ID).', 'wc-promptpay'),
                'desc_tip' => true,
            ),
            'promptpay_type' => array(
                'title' => __('PromptPay ID Type', 'wc-promptpay'),
                'type' => 'select',
                'description' => __('Select the type of your PromptPay ID.', 'wc-promptpay'),
                'default' => 'phone',
                'options' => array(
                    'phone' => __('Phone Number', 'wc-promptpay'),
                    'citizen' => __('Citizen ID', 'wc-promptpay'),
                    'company' => __('Company Tax ID', 'wc-promptpay'),
                    'ewallet' => __('E-Wallet ID', 'wc-promptpay'),
                    'kshop' => __('K Shop ID', 'wc-promptpay'),
                ),
                'desc_tip' => true,
            ),
            'include_amount' => array(
                'title' => __('Include Amount in QR Code', 'wc-promptpay'),
                'type' => 'checkbox',
                'label' => __('Include the order amount in the QR code', 'wc-promptpay'),
                'default' => 'yes',
                'description' => __('When enabled, the QR code will include the exact amount to be paid.', 'wc-promptpay'),
            ),
            'extra_message' => array(
                'title' => __('Extra Message', 'wc-promptpay'),
                'type' => 'textarea',
                'description' => __('Additional message to display on the payment page.', 'wc-promptpay'),
                'desc_tip' => true,
            ),
            'webhook_settings' => array(
                'title' => __('n8n Webhook Settings', 'wc-promptpay'),
                'type' => 'title',
                'description' => __('Configure n8n webhook for automatic payment verification.', 'wc-promptpay'),
            ),
            'n8n_webhook_url' => array(
                'title' => __('n8n Webhook URL', 'wc-promptpay'),
                'type' => 'url',
                'description' => __('Enter the n8n webhook URL for payment verification.', 'wc-promptpay'),
                'desc_tip' => true,
            ),
            'webhook_secret' => array(
                'title' => __('Webhook Secret Key', 'wc-promptpay'),
                'type' => 'password',
                'description' => __('Secret key for webhook signature verification.', 'wc-promptpay'),
                'desc_tip' => true,
            ),
            'auto_complete' => array(
                'title' => __('Auto Complete Orders', 'wc-promptpay'),
                'type' => 'checkbox',
                'label' => __('Automatically complete orders when payment is confirmed', 'wc-promptpay'),
                'default' => 'no',
                'description' => __('When enabled, orders will be marked as completed instead of processing.', 'wc-promptpay'),
            ),
        );
    }
    
    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result' => 'failure',
                'messages' => __('Order not found.', 'wc-promptpay')
            );
        }
        
        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting PromptPay payment', 'wc-promptpay'));
        
        // Reduce stock levels
        wc_reduce_stock_levels($order_id);
        
        // Remove cart
        WC()->cart->empty_cart();
        
        // Send webhook to n8n if configured
        $this->send_webhook_notification($order);
        
        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
    
    /**
     * Send webhook notification to n8n
     */
    private function send_webhook_notification($order) {
        if (empty($this->n8n_webhook_url)) {
            return;
        }
        
        $webhook_data = array(
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'promptpay_id' => $this->promptpay_id,
            'promptpay_type' => $this->promptpay_type,
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'timestamp' => current_time('timestamp'),
            'callback_url' => home_url('/wc-promptpay/webhook/' . $order->get_id()),
        );
        
        // Add signature if secret is configured
        if (!empty($this->webhook_secret)) {
            $webhook_data['signature'] = hash_hmac('sha256', json_encode($webhook_data), $this->webhook_secret);
        }
        
        // Send async request
        wp_remote_post($this->n8n_webhook_url, array(
            'body' => json_encode($webhook_data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'blocking' => false, // Non-blocking request
        ));
        
        // Add order note
        $order->add_order_note(__('PromptPay payment verification request sent to n8n.', 'wc-promptpay'));
    }
    
    /**
     * Output for the order received page
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
        
        $this->display_qr_code($order_id);
    }
    
    /**
     * Display QR code for payment
     */
    public function display_qr_code($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }
        
        $qr_generator = new WC_PromptPay_QR_Generator();
        $qr_data = $qr_generator->generate_qr_data($order, $this->promptpay_id, $this->promptpay_type, $this->include_amount === 'yes');
        $qr_image_url = $qr_generator->generate_qr_image($order_id, $qr_data);
        
        if ($qr_image_url) {
            echo '<div class="wc-promptpay-qr-section">';
            echo '<h3>' . __('Scan QR Code to Pay', 'wc-promptpay') . '</h3>';
            
            if (!empty($this->extra_message)) {
                echo '<p>' . wp_kses_post($this->extra_message) . '</p>';
            }
            
            echo '<div class="wc-promptpay-qr-code">';
            echo '<img src="' . esc_url($qr_image_url) . '" alt="' . __('PromptPay QR Code', 'wc-promptpay') . '" style="max-width: 300px; height: auto;" />';
            echo '</div>';
            
            echo '<div class="wc-promptpay-qr-actions">';
            echo '<a href="' . esc_url(home_url('/wc-promptpay/qr/' . $order_id)) . '" class="button" download>' . __('Download QR Code', 'wc-promptpay') . '</a>';
            echo '</div>';
            
            echo '<div class="wc-promptpay-payment-info">';
            echo '<p><strong>' . __('Amount:', 'wc-promptpay') . '</strong> ' . wc_price($order->get_total()) . '</p>';
            echo '<p><strong>' . __('Order ID:', 'wc-promptpay') . '</strong> ' . $order->get_order_number() . '</p>';
            echo '</div>';
            
            echo '</div>';
            
            // Add some basic styling
            echo '<style>
                .wc-promptpay-qr-section {
                    text-align: center;
                    margin: 20px 0;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
                .wc-promptpay-qr-code {
                    margin: 20px 0;
                }
                .wc-promptpay-qr-actions {
                    margin: 15px 0;
                }
                .wc-promptpay-payment-info {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                }
            </style>';
        }
    }
    
    /**
     * Add content to the WC emails
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }
    
    /**
     * Validate admin options
     */
    public function validate_promptpay_id_field($key, $value) {
        if (empty($value)) {
            WC_Admin_Settings::add_error(__('PromptPay ID is required.', 'wc-promptpay'));
            return '';
        }
        
        // Basic validation based on type
        $type = $this->get_option('promptpay_type');
        switch ($type) {
            case 'phone':
                if (!preg_match('/^0[0-9]{9}$/', $value)) {
                    WC_Admin_Settings::add_error(__('Phone number must be 10 digits starting with 0.', 'wc-promptpay'));
                }
                break;
            case 'citizen':
                if (!preg_match('/^[0-9]{13}$/', $value)) {
                    WC_Admin_Settings::add_error(__('Citizen ID must be 13 digits.', 'wc-promptpay'));
                }
                break;
            case 'company':
                if (!preg_match('/^[0-9]{13}$/', $value)) {
                    WC_Admin_Settings::add_error(__('Company Tax ID must be 13 digits.', 'wc-promptpay'));
                }
                break;
        }
        
        return $value;
    }
}
