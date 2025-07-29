<?php
namespace WooPromptPay\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptPay WooCommerce Blocks Integration
 */
class PP_Blocks_Integration extends AbstractPaymentMethodType {
    
    /**
     * Payment method name
     */
    protected $name = 'promptpay_n8n';
    
    /**
     * Initialize the payment method type
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_promptpay_n8n_settings', [] );
    }
    
    /**
     * Returns if this payment method should be active
     */
    public function is_active() {
        $gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ] ?? null;
        return $gateway && $gateway->is_available();
    }
    
    /**
     * Returns an array of scripts/handles to be registered for this payment method
     */
    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-promptpay-blocks-integration',
            WPPN8N_URL . 'assets/js/blocks-integration.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            WPPN8N_VERSION,
            true
        );
        
        return [ 'wc-promptpay-blocks-integration' ];
    }
    
    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script
     */
    public function get_payment_method_data() {
        $gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ] ?? null;
        
        if ( ! $gateway ) {
            return [];
        }
        
        return [
            'title'       => $gateway->get_title(),
            'description' => $gateway->get_description(),
            'supports'    => $gateway->supports,
            'icon'        => $gateway->get_icon(),
            'promptpay_id' => $gateway->get_option( 'promptpay_id', '' ),
            'enabled'     => $gateway->get_option( 'enabled', 'no' ),
        ];
    }
}
