<?php
/**
 * Uninstall WC PromptPay
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('woocommerce_promptpay_settings');

// Clean up QR code files
$upload_dir = wp_upload_dir();
$qr_dir = $upload_dir['basedir'] . '/wc-promptpay-qr/';

if (file_exists($qr_dir)) {
    $files = glob($qr_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($qr_dir);
}

// Clean up any scheduled events
wp_clear_scheduled_hook('wc_promptpay_cleanup_qr_files');

// Flush rewrite rules
flush_rewrite_rules();
