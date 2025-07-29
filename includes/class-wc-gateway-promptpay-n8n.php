<?php
/**
 * WooCommerce PromptPay n8n Gateway
 *
 * @version 1.0.0
 * @since   1.0.0
 * @author  Lumi-dev
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_PromptPay_N8N Class
 */
class WC_Gateway_PromptPay_N8N extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		
		// Gateway setup
		$this->id                 = 'promptpay_n8n';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'PromptPay n8n Gateway', 'promptpay-n8n-gateway' );
		$this->method_description = __( 'Accept payments via PromptPay QR code with n8n webhook verification.', 'promptpay-n8n-gateway' );
		$this->supports           = array( 'products' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->enabled      = $this->get_option( 'enabled' );
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PromptPay n8n Gateway', 'promptpay-n8n-gateway' ),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'PromptPay', 'promptpay-n8n-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Pay with PromptPay by scanning QR code and uploading payment slip.', 'promptpay-n8n-gateway' ),
				'desc_tip'    => true,
			),
			'promptpay_id' => array(
				'title'       => __( 'PromptPay ID', 'promptpay-n8n-gateway' ),
				'type'        => 'text',
				'description' => __( 'Enter your PromptPay ID (phone number or citizen ID).', 'promptpay-n8n-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'n8n_webhook_url' => array(
				'title'       => __( 'n8n Webhook URL', 'promptpay-n8n-gateway' ),
				'type'        => 'url',
				'description' => __( 'Enter your n8n webhook URL for payment verification.', 'promptpay-n8n-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Payment form on checkout page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}
		
		// Simple payment form for now
		echo '<div id="promptpay-payment-form">';
		echo '<p>' . __( 'You will be able to scan QR code and upload payment slip after placing the order.', 'promptpay-n8n-gateway' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Process the payment and return the result.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the payment slip)
		$order->update_status( 'on-hold', __( 'Awaiting PromptPay payment slip upload.', 'promptpay-n8n-gateway' ) );

		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
		);
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page( $order_id ) {
		echo '<div id="promptpay-thankyou">';
		echo '<h3>' . __( 'Payment Instructions', 'promptpay-n8n-gateway' ) . '</h3>';
		echo '<p>' . __( 'Please scan the QR code below to make payment via PromptPay.', 'promptpay-n8n-gateway' ) . '</p>';
		echo '<p><strong>' . __( 'Note: QR code and slip upload functionality will be added in the next update.', 'promptpay-n8n-gateway' ) . '</strong></p>';
		echo '</div>';
	}

	/**
	 * Add content to the WC emails.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			echo '<p>' . __( 'Please make payment via PromptPay and upload your payment slip.', 'promptpay-n8n-gateway' ) . '</p>';
		}
	}
}
