<?php
/**
 * PromptPay n8n Gateway - Core Class
 *
 * @version 1.0.1
 * @since   1.0.0
 * @author  Lumi-dev
 * @package promptpay-n8n-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'PromptPay_N8N_Core' ) ) :

	/**
	 * PromptPay N8N Gateway Core Class.
	 */
	class PromptPay_N8N_Core {

		/**
		 * Constructor.
		 *
		 * @version 1.0.1
		 * @since   1.0.0
		 */
		public function __construct() {
			if ( 'yes' === get_option( 'promptpay_n8n_gateway_enabled', 'yes' ) ) {
				// Include custom payment gateway class.
				require_once 'class-wc-gateway-promptpay-n8n.php';
			}
		}

	}

endif;

return new PromptPay_N8N_Core();
