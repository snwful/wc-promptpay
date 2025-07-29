<?php
/**
 * PromptPay n8n Gateway Core
 *
 * @version 1.0.0
 * @since   1.0.0
 * @author  Lumi-dev
 * @package promptpay-n8n-gateway
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PromptPay_N8N_Core' ) ) :

	/**
	 * PromptPay_N8N_Core Class
	 *
	 * @class   PromptPay_N8N_Core
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	class PromptPay_N8N_Core {

		/**
		 * Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function __construct() {
			// Load payment gateway class
			require_once 'class-wc-gateway-promptpay-n8n.php';
			
			// Add payment gateway to WooCommerce
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_payment_gateway' ) );
		}

		/**
		 * Add payment gateway to WooCommerce.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @param   array $methods Payment methods.
		 * @return  array
		 */
		public function add_payment_gateway( $methods ) {
			$methods[] = 'WC_Gateway_PromptPay_N8N';
			return $methods;
		}
	}

endif;

return new PromptPay_N8N_Core();
