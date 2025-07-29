<?php
/**
 * Plugin Name: PromptPay n8n Gateway
 * Plugin URI: https://github.com/Lumi-dev/wc-promptpay
 * Description: A WooCommerce payment gateway for PromptPay with n8n webhook integration for payment verification.
 * Version: 1.0.1
 * Author: Lumi-dev
 * Author URI: https://github.com/Lumi-dev
 * Text Domain: promptpay-n8n-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package promptpay-n8n-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'PromptPay_N8N_Gateway' ) ) :

	/**
	 * Main PromptPay_N8N_Gateway Class
	 *
	 * @class   PromptPay_N8N_Gateway
	 * @version 1.0.1
	 * @since   1.0.0
	 */
	final class PromptPay_N8N_Gateway {

		/**
		 * Plugin version.
		 *
		 * @var   string
		 * @since 1.0.0
		 */
		public $version = '1.0.1';

		/**
		 * The single instance of the class.
		 *
		 * @var   PromptPay_N8N_Gateway The single instance of the class
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
		 * Main PromptPay_N8N_Gateway Instance
		 *
		 * Ensures only one instance of PromptPay_N8N_Gateway is loaded or can be loaded.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @static
		 * @return  PromptPay_N8N_Gateway - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * PromptPay_N8N_Gateway Constructor.
		 *
		 * @version 1.0.1
		 * @since   1.0.0
		 * @access  public
		 */
		public function __construct() {

			// Check for active plugins.
			if ( ! $this->is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				return;
			}

			// Set up localisation.
			load_plugin_textdomain( 'promptpay-n8n-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			// Include required files.
			$this->includes();

			// Admin.
			if ( is_admin() ) {
				$this->admin();
			}

			// HPOS compatibility
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		}

		/**
		 * Is plugin active.
		 *
		 * @param   string $plugin Plugin Name.
		 * @return  bool
		 * @version 1.0.0
		 * @since   1.0.0
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
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function includes() {
			// Core.
			$this->core = require_once 'includes/class-promptpay-n8n-core.php';
		}

		/**
		 * Admin.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function admin() {
			// Action links.
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		}

		/**
		 * Declare HPOS compatibility.
		 *
		 * @version 1.0.1
		 * @since   1.0.1
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Show action links on the plugin screen.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @param   mixed $links Links.
		 * @return  array
		 */
		public function action_links( $links ) {
			$custom_links   = array();
			$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=promptpay_n8n' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
			return array_merge( $custom_links, $links );
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
	}

endif;

if ( ! function_exists( 'promptpay_n8n_gateway' ) ) {
	/**
	 * Returns the main instance of PromptPay_N8N_Gateway to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  PromptPay_N8N_Gateway
	 */
	function promptpay_n8n_gateway() {
		return PromptPay_N8N_Gateway::instance();
	}
}

promptpay_n8n_gateway();
