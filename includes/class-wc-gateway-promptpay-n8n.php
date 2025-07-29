<?php
/**
 * PromptPay n8n Gateway - Gateway Class
 *
 * @version 1.0.1
 * @since   1.0.0
 * @author  Lumi-dev
 * @package promptpay-n8n-gateway
 */

add_action( 'plugins_loaded', 'init_wc_gateway_promptpay_n8n_class' );

if ( ! function_exists( 'init_wc_gateway_promptpay_n8n_class' ) ) {

	/**
	 * Load the class for creating PromptPay gateway once plugins are loaded.
	 */
	function init_wc_gateway_promptpay_n8n_class() {

		if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_Gateway_PromptPay_N8N' ) ) {

			/**
			 * WC_Gateway_PromptPay_N8N class.
			 *
			 * @version 1.0.1
			 * @since   1.0.0
			 */
			class WC_Gateway_PromptPay_N8N extends WC_Payment_Gateway {

				/**
				 * Check WC version for Backward compatibility.
				 *
				 * @var string
				 */
				public $is_wc_version_below_3 = null;

				/**
				 * The instructions for the payment gateway.
				 *
				 * @var string
				 */
				public $instructions = null;

				/**
				 * The instructions in email for the payment gateway.
				 *
				 * @var string
				 */
				public $instructions_in_email = null;

				/**
				 * The minimum amount needed for payment gateway.
				 *
				 * @var int
				 */
				public $min_amount = 0;

				/**
				 * The default order status when payment gateway is used.
				 *
				 * @var string
				 */
				public $default_order_status = null;

				/**
				 * PromptPay ID.
				 *
				 * @var string
				 */
				public $promptpay_id = null;

				/**
				 * n8n Webhook URL.
				 *
				 * @var string
				 */
				public $n8n_webhook_url = null;

				/**
				 * Constructor.
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 */
				public function __construct() {
					$this->is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
					return true;
				}

				/**
				 * Initialize the gateway.
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 */
				public function init() {
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
					$this->enabled                = $this->get_option( 'enabled' );
					$this->title                  = $this->get_option( 'title' );
					$this->description            = $this->get_option( 'description' );
					$this->instructions           = $this->get_option( 'instructions' );
					$this->instructions_in_email  = $this->get_option( 'instructions_in_email' );
					$this->min_amount             = $this->get_option( 'min_amount', 0 );
					$this->default_order_status   = $this->get_option( 'default_order_status', 'on-hold' );
					$this->promptpay_id           = $this->get_option( 'promptpay_id' );
					$this->n8n_webhook_url        = $this->get_option( 'n8n_webhook_url' );

					// Actions
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
					add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
					add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
				}

				/**
				 * Initialize Gateway Settings Form Fields.
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 */
				public function init_form_fields() {
					$this->form_fields = array(
						'enabled' => array(
							'title'   => __( 'Enable/Disable', 'woocommerce' ),
							'type'    => 'checkbox',
							'label'   => __( 'Enable PromptPay n8n Gateway', 'promptpay-n8n-gateway' ),
							'default' => 'yes'
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
						'instructions' => array(
							'title'       => __( 'Instructions', 'promptpay-n8n-gateway' ),
							'type'        => 'textarea',
							'description' => __( 'Instructions that will be added to the thank you page.', 'promptpay-n8n-gateway' ),
							'default'     => __( 'Please scan the QR code and upload your payment slip.', 'promptpay-n8n-gateway' ),
							'desc_tip'    => true,
						),
						'instructions_in_email' => array(
							'title'       => __( 'Instructions in Email', 'promptpay-n8n-gateway' ),
							'type'        => 'textarea',
							'description' => __( 'Instructions that will be added to emails.', 'promptpay-n8n-gateway' ),
							'default'     => __( 'Please scan the QR code and upload your payment slip.', 'promptpay-n8n-gateway' ),
							'desc_tip'    => true,
						),
						'min_amount' => array(
							'title'       => __( 'Minimum Amount', 'promptpay-n8n-gateway' ),
							'type'        => 'number',
							'description' => __( 'Minimum order amount for this payment method (0 for no minimum).', 'promptpay-n8n-gateway' ),
							'default'     => 0,
							'desc_tip'    => true,
						),
						'default_order_status' => array(
							'title'       => __( 'Default Order Status', 'promptpay-n8n-gateway' ),
							'type'        => 'select',
							'description' => __( 'Order status after payment is processed.', 'promptpay-n8n-gateway' ),
							'default'     => 'on-hold',
							'options'     => array(
								'pending'    => __( 'Pending payment', 'woocommerce' ),
								'processing' => __( 'Processing', 'woocommerce' ),
								'on-hold'    => __( 'On hold', 'woocommerce' ),
								'completed'  => __( 'Completed', 'woocommerce' ),
							),
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
				 * Check if this gateway is available.
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 * @return  bool
				 */
				public function is_available() {
					// Always return true for now to debug
					// Later we can add proper checks
					return true;
				}

				/**
				 * Payment form on checkout page.
				 *
				 * @version 1.0.1
				 * @since   1.0.0
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
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 * @param   int $order_id Order ID.
				 * @return  array
				 */
				public function process_payment( $order_id ) {
					$order = wc_get_order( $order_id );

					// Mark order with the configured status
					$order->update_status( $this->default_order_status, __( 'Awaiting PromptPay payment slip upload.', 'promptpay-n8n-gateway' ) );

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
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 * @param   int $order_id Order ID.
				 */
				public function thankyou_page( $order_id ) {
					if ( $this->instructions ) {
						echo wpautop( wptexturize( $this->instructions ) );
					}
					
					echo '<div id="promptpay-thankyou">';
					echo '<h3>' . __( 'Payment Instructions', 'promptpay-n8n-gateway' ) . '</h3>';
					echo '<p>' . __( 'Please scan the QR code below to make payment via PromptPay.', 'promptpay-n8n-gateway' ) . '</p>';
					echo '<p><strong>' . __( 'Note: QR code and slip upload functionality will be added in the next update.', 'promptpay-n8n-gateway' ) . '</strong></p>';
					echo '</div>';
				}

				/**
				 * Add content to the WC emails.
				 *
				 * @version 1.0.1
				 * @since   1.0.0
				 * @param   WC_Order $order Order object.
				 * @param   bool     $sent_to_admin Sent to admin.
				 * @param   bool     $plain_text Email format: plain text or HTML.
				 */
				public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
					if ( $this->instructions_in_email && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( $this->default_order_status ) ) {
						echo wpautop( wptexturize( $this->instructions_in_email ) ) . PHP_EOL;
					}
				}
			}

			/**
			 * Add WC Gateway Classes.
			 *
			 * @param array $methods Gateway Methods.
			 * @return array
			 * @version 1.0.1
			 * @since   1.0.0
			 */
			function add_wc_gateway_promptpay_n8n_classes( $methods ) {
				$gateway = new WC_Gateway_PromptPay_N8N();
				$gateway->init();
				$methods[] = $gateway;
				return $methods;
			}
			add_filter( 'woocommerce_payment_gateways', 'add_wc_gateway_promptpay_n8n_classes' );
		}
	}
}
