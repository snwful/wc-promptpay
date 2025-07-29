<?php
/**
 * Plugin Name: Custom Payment Gateways for WooCommerce
 * Plugin URI: https://imaginate-solutions.com/downloads/custom-payment-gateways-for-woocommerce/
 * Description: Custom payment gateways for WooCommerce
 * Version: 2.1.0
 * Author: Imaginate Solutions
 * Author URI: https://imaginate-solutions.com
 * Text Domain: custom-payment-gateways-woocommerce
 * Domain Path: /langs
 * Copyright: © 2025 Imaginate Solutions.
 * WC tested up to: 9.8
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package cpgw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Payment_Gateways' ) ) :

	/**
	 * Main Alg_WC_Custom_Payment_Gateways Class
	 *
	 * @class   Alg_WC_Custom_Payment_Gateways
	 * @version 1.6.2
	 * @since   1.0.0
	 */
	final class Alg_WC_Custom_Payment_Gateways {

		/**
		 * Plugin version.
		 *
		 * @var   string
		 * @since 1.0.0
		 */
		public $version = '2.1.0';

		/**
		 * The single instance of the class.
		 *
		 * @var   Alg_WC_Custom_Payment_Gateways The single instance of the class
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * The core file reference.
		 *
		 * @var   string The path of the core file
		 * @since 1.0.0
		 */
		protected $core = null;

		/**
		 * Main Alg_WC_Custom_Payment_Gateways Instance
		 *
		 * Ensures only one instance of Alg_WC_Custom_Payment_Gateways is loaded or can be loaded.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @static
		 * @return  Alg_WC_Custom_Payment_Gateways - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Alg_WC_Custom_Payment_Gateways Constructor.
		 *
		 * @version 1.6.0
		 * @since   1.0.0
		 * @access  public
		 */
		public function __construct() {

			// Check for active plugins.
			if (
			! $this->is_plugin_active( 'woocommerce/woocommerce.php' ) ||
			( 'custom-payment-gateways-for-woocommerce.php' === basename( __FILE__ ) && $this->is_plugin_active( 'custom-payment-gateways-for-woocommerce-pro/custom-payment-gateways-for-woocommerce-pro.php' ) )
			) {
				return;
			}

			// Set up localisation.
			load_plugin_textdomain( 'custom-payment-gateways-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

			// Pro.
			if ( 'custom-payment-gateways-for-woocommerce-pro.php' === basename( __FILE__ ) ) {
				require_once 'includes/pro/class-alg-wc-custom-payment-gateways-pro.php';
			}

			// Include required files.
			$this->includes();

			// Admin.
			if ( is_admin() ) {
				$this->admin();
			}

			add_action( 'woocommerce_blocks_loaded', array( $this, 'alg_gateway_block_support' ) );
		}

		/**
		 * Is plugin active.
		 *
		 * @param   string $plugin Plugin Name.
		 * @return  bool
		 * @version 1.6.0
		 * @since   1.6.0
		 */
		public function is_plugin_active( $plugin ) {
			return ( function_exists( 'is_plugin_active' ) ? is_plugin_active( $plugin ) :
			(
				in_array( $plugin, apply_filters( 'active_plugins', (array) get_option( 'active_plugins', array() ) ), true ) ||
				( is_multisite() && array_key_exists( $plugin, (array) get_site_option( 'active_sitewide_plugins', array() ) ) )
			)
			);
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function includes() {
			// Functions.
			require_once 'includes/alg-wc-custom-payment-gateways-functions.php';
			// Core.
			$this->core = require_once 'includes/class-alg-wc-custom-payment-gateways-core.php';
		}

		/**
		 * Admin.
		 *
		 * @version 1.6.2
		 * @since   1.2.0
		 */
		public function admin() {
			// Action links.
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
			// Settings.
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
			// Version update.
			if ( get_option( 'alg_wc_custom_payment_gateways_version', '' ) !== $this->version ) {
				add_action( 'admin_init', array( $this, 'version_updated' ) );
			}
			// HPOS compatibility
			add_action( 'before_woocommerce_init', array( $this, 'cpg_declare_hpos_compatibility' ) );

			require_once 'includes/class-alg-wc-custom-payment-upgrades.php';
		}

		public function cpg_declare_hpos_compatibility() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Show action links on the plugin screen.
		 *
		 * @version 1.2.1
		 * @since   1.0.0
		 * @param   mixed $links Links.
		 * @return  array
		 */
		public function action_links( $links ) {
			$custom_links   = array();
			$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_payment_gateways' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
			if ( 'custom-payment-gateways-for-woocommerce.php' === basename( __FILE__ ) ) {
				$custom_links[] = '<a target="_blank" href="https://imaginate-solutions.com/downloads/custom-payment-gateways-for-woocommerce/">' .
				__( 'Unlock All', 'custom-payment-gateways-woocommerce' ) . '</a>';
			}
			return array_merge( $custom_links, $links );
		}

		/**
		 * Add Custom Payment Gateways settings tab to WooCommerce settings.
		 *
		 * @param   array $settings WC Settings Array.
		 * @return  array
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function add_woocommerce_settings_tab( $settings ) {
			$settings[] = require_once 'includes/settings/class-alg-wc-settings-custom-payment-gateways.php';
			return $settings;
		}

		/**
		 * Version updated.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		public function version_updated() {
			update_option( 'alg_wc_custom_payment_gateways_version', $this->version );
		}

		/**
		 * Get the plugin url.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_url() {
			return untrailingslashit( plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		/**
		 * Adds support for the custom payment gateway in WooCommerce Blocks.
		 *
		 * @return void
		 */
		public function alg_gateway_block_support() {
			// Check if the AbstractPaymentMethodType class exists (required for WooCommerce Blocks compatibility).
			if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				return;
			}

			// Register a callback to handle cart updates (e.g., applying fees dynamically).
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => 'extension-fees', // Custom namespace for the callback.
					'callback'  => array( $this, 'update_cart_fees' ), // Method to handle the update.
				)
			);

			// Include the custom payment gateway support class for WooCommerce Blocks.
			require_once __DIR__ . '/includes/class-wc-gateway-blocks-support.php';

			// Register the custom payment gateway with WooCommerce Blocks.
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					// Register the custom payment gateway with the PaymentMethodRegistry.
					$payment_method_registry->register( new WC_Gateway_Blocks_Support() );
				}
			);
		}


		/**
		 * Updates the cart session with the selected payment method.
		 *
		 * @param array $data The data array received from the frontend, which may contain
		 *                    a `payment_method` key representing the selected payment method.
		 *
		 * @return void
		 */
		public function update_cart_fees( $data ) {
			// Check if the 'payment_method' key is set in the input data.
			if ( isset( $data['payment_method'] ) ) {
				// Save the selected payment method in the WooCommerce session.
				WC()->session->set( 'chosen_payment_method', $data['payment_method'] );
			}
		}
	}

endif;

if ( ! function_exists( 'alg_wc_custom_payment_gateways' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Custom_Payment_Gateways to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_WC_Custom_Payment_Gateways
	 */
	function alg_wc_custom_payment_gateways() {
		return Alg_WC_Custom_Payment_Gateways::instance();
	}
}

alg_wc_custom_payment_gateways();
