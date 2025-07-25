<?php
/**
 * WC PromptPay Webhook Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PromptPay_Webhook {
    
    /**
     * Handle webhook request
     */
    public function handle_request() {
        // Get request method
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method !== 'POST') {
            $this->send_response(405, array('error' => 'Method not allowed'));
            return;
        }
        
        // Get raw POST data
        $raw_data = file_get_contents('php://input');
        
        if (empty($raw_data)) {
            $this->send_response(400, array('error' => 'No data received'));
            return;
        }
        
        // Decode JSON data
        $data = json_decode($raw_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->send_response(400, array('error' => 'Invalid JSON data'));
            return;
        }
        
        // Validate required fields
        if (!isset($data['order_id']) || !isset($data['status'])) {
            $this->send_response(400, array('error' => 'Missing required fields'));
            return;
        }
        
        // Get order
        $order_id = intval($data['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            $this->send_response(404, array('error' => 'Order not found'));
            return;
        }
        
        // Verify webhook signature if secret is configured
        if (!$this->verify_signature($raw_data, $order)) {
            $this->send_response(401, array('error' => 'Invalid signature'));
            return;
        }
        
        // Process payment status
        $this->process_payment_status($order, $data);
        
        // Send success response
        $this->send_response(200, array('success' => true, 'message' => 'Webhook processed successfully'));
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_signature($raw_data, $order) {
        $gateway = new WC_PromptPay_Gateway();
        $secret = $gateway->get_option('webhook_secret');
        
        // If no secret is configured, skip verification
        if (empty($secret)) {
            return true;
        }
        
        // Get signature from headers
        $signature = null;
        $headers = getallheaders();
        
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-signature') {
                $signature = $value;
                break;
            }
        }
        
        if (empty($signature)) {
            return false;
        }
        
        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $raw_data, $secret);
        
        // Compare signatures
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Process payment status
     */
    private function process_payment_status($order, $data) {
        $status = sanitize_text_field($data['status']);
        $message = isset($data['message']) ? sanitize_text_field($data['message']) : '';
        $transaction_id = isset($data['transaction_id']) ? sanitize_text_field($data['transaction_id']) : '';
        $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
        
        // Log webhook data
        $order->add_order_note(sprintf(
            __('PromptPay webhook received: Status=%s, Message=%s', 'wc-promptpay'),
            $status,
            $message
        ));
        
        switch ($status) {
            case 'success':
            case 'paid':
            case 'completed':
                $this->handle_successful_payment($order, $data);
                break;
                
            case 'failed':
            case 'error':
                $this->handle_failed_payment($order, $data);
                break;
                
            case 'pending':
            case 'processing':
                $this->handle_pending_payment($order, $data);
                break;
                
            default:
                $order->add_order_note(sprintf(
                    __('Unknown payment status received: %s', 'wc-promptpay'),
                    $status
                ));
        }
    }
    
    /**
     * Handle successful payment
     */
    private function handle_successful_payment($order, $data) {
        // Check if order is already paid
        if ($order->is_paid()) {
            return;
        }
        
        $gateway = new WC_PromptPay_Gateway();
        $auto_complete = $gateway->get_option('auto_complete') === 'yes';
        
        // Set transaction ID if provided
        if (!empty($data['transaction_id'])) {
            $order->set_transaction_id(sanitize_text_field($data['transaction_id']));
        }
        
        // Validate amount if provided
        if (isset($data['amount']) && $data['amount'] > 0) {
            $expected_amount = floatval($order->get_total());
            $received_amount = floatval($data['amount']);
            
            if (abs($expected_amount - $received_amount) > 0.01) {
                $order->add_order_note(sprintf(
                    __('Warning: Amount mismatch. Expected: %s, Received: %s', 'wc-promptpay'),
                    wc_price($expected_amount),
                    wc_price($received_amount)
                ));
            }
        }
        
        // Update order status
        if ($auto_complete) {
            $order->update_status('completed', __('Payment completed via PromptPay.', 'wc-promptpay'));
        } else {
            $order->payment_complete();
        }
        
        // Add success note
        $note = __('PromptPay payment confirmed successfully.', 'wc-promptpay');
        if (!empty($data['message'])) {
            $note .= ' ' . sprintf(__('Message: %s', 'wc-promptpay'), sanitize_text_field($data['message']));
        }
        $order->add_order_note($note);
        
        // Save order
        $order->save();
        
        // Trigger action for other plugins
        do_action('wc_promptpay_payment_complete', $order, $data);
    }
    
    /**
     * Handle failed payment
     */
    private function handle_failed_payment($order, $data) {
        $order->update_status('failed', __('PromptPay payment failed.', 'wc-promptpay'));
        
        $note = __('PromptPay payment failed.', 'wc-promptpay');
        if (!empty($data['message'])) {
            $note .= ' ' . sprintf(__('Reason: %s', 'wc-promptpay'), sanitize_text_field($data['message']));
        }
        $order->add_order_note($note);
        
        // Trigger action for other plugins
        do_action('wc_promptpay_payment_failed', $order, $data);
    }
    
    /**
     * Handle pending payment
     */
    private function handle_pending_payment($order, $data) {
        $note = __('PromptPay payment is being processed.', 'wc-promptpay');
        if (!empty($data['message'])) {
            $note .= ' ' . sprintf(__('Status: %s', 'wc-promptpay'), sanitize_text_field($data['message']));
        }
        $order->add_order_note($note);
        
        // Trigger action for other plugins
        do_action('wc_promptpay_payment_pending', $order, $data);
    }
    
    /**
     * Send JSON response
     */
    private function send_response($status_code, $data) {
        status_header($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Get all headers (fallback for servers that don't support getallheaders)
     */
    private function get_all_headers() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
