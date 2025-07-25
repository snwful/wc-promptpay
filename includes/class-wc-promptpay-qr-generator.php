<?php
/**
 * WC PromptPay QR Generator Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_PromptPay_QR_Generator {
    
    /**
     * Generate PromptPay QR data
     */
    public function generate_qr_data($order, $promptpay_id, $promptpay_type, $include_amount = true) {
        $amount = $include_amount ? $order->get_total() : 0;
        
        // Format PromptPay ID based on type
        $formatted_id = $this->format_promptpay_id($promptpay_id, $promptpay_type);
        
        // Generate PromptPay payload
        $payload = $this->generate_promptpay_payload($formatted_id, $amount);
        
        return $payload;
    }
    
    /**
     * Format PromptPay ID based on type
     */
    private function format_promptpay_id($id, $type) {
        switch ($type) {
            case 'phone':
                // Remove leading 0 and add +66
                if (substr($id, 0, 1) === '0') {
                    return '+66' . substr($id, 1);
                }
                return $id;
                
            case 'citizen':
            case 'company':
                // Keep as is for citizen/company ID
                return $id;
                
            case 'ewallet':
            case 'kshop':
                // Keep as is for e-wallet/K Shop
                return $id;
                
            default:
                return $id;
        }
    }
    
    /**
     * Generate PromptPay payload
     */
    private function generate_promptpay_payload($promptpay_id, $amount = 0) {
        $payload = '';
        
        // Payload Format Indicator
        $payload .= $this->format_tlv('00', '01');
        
        // Point of Initiation Method
        $payload .= $this->format_tlv('01', '11'); // Static QR Code
        
        // Merchant Account Information
        $merchant_info = '';
        $merchant_info .= $this->format_tlv('00', 'A000000677010111'); // PromptPay Application ID
        $merchant_info .= $this->format_tlv('01', $promptpay_id);
        $payload .= $this->format_tlv('29', $merchant_info);
        
        // Transaction Currency (THB)
        $payload .= $this->format_tlv('53', '764');
        
        // Transaction Amount
        if ($amount > 0) {
            $payload .= $this->format_tlv('54', number_format($amount, 2, '.', ''));
        }
        
        // Country Code
        $payload .= $this->format_tlv('58', 'TH');
        
        // Merchant Name (optional)
        $payload .= $this->format_tlv('59', get_bloginfo('name'));
        
        // Merchant City (optional)
        $payload .= $this->format_tlv('60', 'Bangkok');
        
        // CRC (will be calculated)
        $payload .= '6304';
        
        // Calculate CRC
        $crc = $this->calculate_crc16($payload);
        $payload = substr($payload, 0, -4) . strtoupper(dechex($crc));
        
        return $payload;
    }
    
    /**
     * Format TLV (Tag-Length-Value)
     */
    private function format_tlv($tag, $value) {
        $length = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $tag . $length . $value;
    }
    
    /**
     * Calculate CRC16
     */
    private function calculate_crc16($data) {
        $crc = 0xFFFF;
        $polynomial = 0x1021;
        
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= (ord($data[$i]) << 8);
            
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        
        return $crc;
    }
    
    /**
     * Generate QR code image
     */
    public function generate_qr_image($order_id, $qr_data) {
        // Check if phpqrcode is available
        if (!$this->load_phpqrcode()) {
            return false;
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/wc-promptpay-qr/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }
        
        // Generate QR code filename
        $filename = 'qr-' . $order_id . '-' . time() . '.png';
        $filepath = $qr_dir . $filename;
        
        try {
            // Generate QR code
            QRcode::png($qr_data, $filepath, QR_ECLEVEL_M, 8, 2);
            
            // Return URL
            return $upload_dir['baseurl'] . '/wc-promptpay-qr/' . $filename;
            
        } catch (Exception $e) {
            error_log('WC PromptPay QR Generation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Download QR code
     */
    public function download_qr_code($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_die(__('Order not found.', 'wc-promptpay'));
        }
        
        // Check if user has permission to view this order
        if (!current_user_can('manage_woocommerce') && 
            (!is_user_logged_in() || $order->get_customer_id() !== get_current_user_id())) {
            wp_die(__('You do not have permission to access this order.', 'wc-promptpay'));
        }
        
        // Get gateway settings
        $gateway = new WC_PromptPay_Gateway();
        
        // Generate QR data
        $qr_data = $this->generate_qr_data($order, $gateway->promptpay_id, $gateway->promptpay_type, $gateway->include_amount === 'yes');
        
        // Check if phpqrcode is available
        if (!$this->load_phpqrcode()) {
            wp_die(__('QR code library not available.', 'wc-promptpay'));
        }
        
        // Generate QR code in memory
        ob_start();
        QRcode::png($qr_data, null, QR_ECLEVEL_M, 8, 2);
        $qr_image = ob_get_contents();
        ob_end_clean();
        
        // Set headers for download
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="promptpay-qr-order-' . $order_id . '.png"');
        header('Content-Length: ' . strlen($qr_image));
        
        echo $qr_image;
    }
    
    /**
     * Load phpqrcode library
     */
    private function load_phpqrcode() {
        $phpqrcode_path = WC_PROMPTPAY_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';
        
        if (file_exists($phpqrcode_path)) {
            require_once $phpqrcode_path;
            return true;
        }
        
        // Try to use WordPress bundled version if available
        if (class_exists('QRcode')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Clean up old QR code files
     */
    public function cleanup_old_qr_files($days = 7) {
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/wc-promptpay-qr/';
        
        if (!file_exists($qr_dir)) {
            return;
        }
        
        $files = glob($qr_dir . 'qr-*.png');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}
