<?php
/**
 * Flutterwave WooCommerce plugin main class
 *
 * @package Flutterwave
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Main Class
 */
final class Flutterwave {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public string $version = '2.3.2';

	/**
	 * Plugin API version.
	 *
	 * @var string
	 */
	public string $api_version = 'v3';
	/**
	 * Plugin instance.
	 *
	 * @var Flutterwave|null
	 */
	protected static ?Flutterwave $instance = null;

	/**
	 * Main Instance.
	 */
	public static function instance(): Flutterwave {
		self::$instance = is_null( self::$instance ) ? new self() : self::$instance;

		return self::$instance;
	}

	/**
	 * Flutterwave Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->load_plugin_textdomain();
		$this->includes();
		$this->init();
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( string $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Define Flutterwave Constants.
	 */
	private function define_constants() {
		$this->define( 'FLUTTERWAVEACCESS', 1 );
		$this->define( 'FLW_WC_PLUGIN_URL', plugin_dir_url( FLW_WC_PLUGIN_FILE ) );
		$this->define( 'FLW_WC_PLUGIN_BASENAME', plugin_basename( FLW_WC_PLUGIN_FILE ) );
		$this->define( 'FLW_WC_PLUGIN_DIR', plugin_dir_path( FLW_WC_PLUGIN_FILE ) );
		$this->define( 'FLW_WC_DIR_PATH', plugin_dir_path( FLW_WC_PLUGIN_FILE ) );
		$this->define( 'FLW_WC_VERSION', $this->version );
		$this->define( 'FLW_WC_MIN_WC_VER', '7.1' );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 2.3.2
	 */
	public function load_plugin_textdomain() {
		$locale = determine_locale();

		/**
		 * Filter to adjust the WooCommerce locale to use for translations.
		 */
		$locale = apply_filters( 'plugin_locale', $locale, 'woocommerce-rave' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingSinceComment
		load_plugin_textdomain( 'woocommerce-rave', false, dirname( FLW_WC_PLUGIN_BASENAME ) . '/i18n/languages' );
	}

	/**
	 * Initialize the plugin.
	 * Checks for an existing instance of this class in the global scope and if it doesn't find one, creates it.
	 *
	 * @return void
	 */
	private function init() {
		$notices = new FLW_WC_Payment_Gateway_Notices();

		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $notices, 'woocommerce_not_installed' ) );
		}

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		if ( version_compare( WC_VERSION, FLW_WC_MIN_WC_VER, '<' ) ) {
			add_action( 'admin_notices', array( $notices, 'woocommerce_wc_not_supported' ) );
			return;
		}

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		$this->register_payment_gateway();

		// add woocommerce block support.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'flutterwave_woocommerce_blocks_support' ) );
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.3.2
	 */
	public function __clone() {}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.3.2
	 */
	public function __wakeup() {}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		include_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/notices/class-flw-wc-payment-gateway-notices.php';
	}

	/**
	 * This handles actions on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$notices = new FLW_WC_Payment_Gateway_Notices();
			add_action( 'admin_notices', array( $notices, 'woocommerce_not_installed' ) );
		}
	}

	/**
	 * This handles actions on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Deactivation logic.
	}

	/**
	 * Register Flutterwave as a Payment Gateway.
	 *
	 * @return void
	 */
	public function register_payment_gateway() {
		require_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/class-flw-wc-payment-gateway.php';
		if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {
			require_once FLW_WC_DIR_PATH . 'includes/class-flw-wc-payment-gateway-subscriptions.php';

		}

		add_filter( 'woocommerce_payment_gateways', array( 'Flutterwave', 'add_gateway_to_woocommerce_gateway_list' ), 99 );
	}

	/**
	 * Add the Gateway to WooCommerce
	 *
	 * @param  array $methods Existing gateways in WooCommerce.
	 *
	 * @return array Gateway list with our gateway added
	 */
	public static function add_gateway_to_woocommerce_gateway_list( array $methods ): array {

		if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {

			$methods[] = 'FLW_WC_Payment_Gateway_Subscriptions';

		} else {

			$methods[] = 'FLW_WC_Payment_Gateway';
		}

		return $methods;
	}

	/**
	 * Add the Settings link to the plugin
	 *
	 * @param  array $links Existing links on the plugin page.
	 *
	 * @return array Existing links with our settings link added
	 */
	public static function plugin_action_links( array $links ): array {

		$rave_settings_url = esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=rave' ) );
		array_unshift( $links, "<a title='Flutterwave Settings Page' href='$rave_settings_url'>Settings</a>" );

		return $links;

	}

	/**
	 * Register the Flutterwave payment gateway for WooCommerce Blocks.
	 *
	 * @return void
	 */
	protected function flutterwave_woocommerce_blocks_support() {
		if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
			require_once dirname( FLW_WC_PLUGIN_FILE ) . '/includes/blocks/class-flutterwave-wc-gateway-blocks-support.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$container = Automattic\WooCommerce\Blocks\Package::container();
					// registers as shared instance.
					$container->register(
						Flutterwave_WC_Gateway_Blocks_Support::class,
						function() {
							return new Flutterwave_WC_Gateway_Blocks_Support();
						}
					);

					$payment_method_registry->register( new Flutterwave_WC_Gateway_Blocks_Support() );
				}
			);
		}
	}
}
