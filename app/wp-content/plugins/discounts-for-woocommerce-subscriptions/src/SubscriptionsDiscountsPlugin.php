<?php namespace MeowCrew\SubscriptionsDiscounts;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use MeowCrew\SubscriptionsDiscounts\Addons\Addons;
use MeowCrew\SubscriptionsDiscounts\Core\FileManager;
use MeowCrew\SubscriptionsDiscounts\Core\AdminNotifier;
use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\Integrations\Integrations;
use MeowCrew\SubscriptionsDiscounts\Managers\RenewalManager;
use MeowCrew\SubscriptionsDiscounts\Settings\Settings;
use MeowCrew\SubscriptionsDiscounts\Admin\Admin;
use MeowCrew\SubscriptionsDiscounts\Frontend\Frontend;

/**
 * Class MeowCrew\SubscriptionsDiscountsPlugin
 *
 * @package MeowCrew\SubscriptionsDiscounts
 */
class SubscriptionsDiscountsPlugin {
	
	use ServiceContainerTrait;
	
	const VERSION = '3.1.0';
	
	/**
	 * MeowCrew\SubscriptionsDiscountsPlugin constructor.
	 *
	 * @param  string  $mainFile
	 */
	public function __construct( $mainFile ) {
		
		FileManager::init( $mainFile, 'discounts-for-woocommerce-subscriptions' );
		$this->getContainer()->add( 'fileManager', FileManager::getInstance() );
		
		add_action( 'init', array( $this, 'loadTextDomain' ), - 999 );
		
		add_filter( 'plugin_action_links_' . plugin_basename( $this->getContainer()->getFileManager()->getMainFile() ),
			array( $this, 'addPluginAction' ), 10, 4 );
		
		add_action( 'before_woocommerce_init', function () use ( $mainFile ) {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', $mainFile, true );
				FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $mainFile, true );
			}
		} );
	}
	
	/**
	 * Add setting to plugin actions at plugins list
	 *
	 * @param  array  $actions
	 *
	 * @return array
	 */
	public function addPluginAction( $actions ) {
		$actions[] = '<a href="' . $this->getContainer()->getSettings()->getLink() . '">' . __( 'Settings',
				'discounts-for-woocommerce-subscriptions' ) . '</a>';
		$actions[] = '<a href="https://woocommerce.com/document/discounts-for-woocommerce-subscriptions/">' . __( 'Documentation',
				'discounts-for-woocommerce-subscriptions' ) . '</a>';
		
		return $actions;
	}
	
	/**
	 * Run plugin part
	 */
	public function run() {
		
		$this->getContainer()->add( 'adminNotifier', new AdminNotifier() );
		$this->getContainer()->add( 'notifier', new AdminNotifier() );
		
		$this->getContainer()->add( 'settings', new Settings() );
		
		$this->getContainer()->add( 'addons', new Addons() );
		$this->getContainer()->add( 'integrations', new Integrations() );
		
		$this->getContainer()->add( FirstPaymentDiscountService::class, new FirstPaymentDiscountService() );
		
		if ( $this->checkRequirePlugins() ) {
			new Admin();
			new Frontend();
			new RenewalManager();
		}
	}
	
	/**
	 * Load plugin translations
	 */
	public function loadTextDomain() {
		$name = $this->getContainer()->getFileManager()->getPluginName();
		load_plugin_textdomain( 'discounts-for-woocommerce-subscriptions', false, $name . '/languages/' );
	}
	
	/**
	 * Validate required plugins
	 *
	 * @return array
	 */
	private function validateRequiredPlugins() {
		$plugins = array();
		
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		
		/**
		 * Check if WooCommerce is active
		 **/
		if ( ! ( is_plugin_active( 'woocommerce/woocommerce.php' ) || is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) ) {
			$plugins[] = '<a target="_blank" href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a>';
		}
		
		if ( ! ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) || is_plugin_active_for_network( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) ) {
			$plugins[] = 'WooCommerce Subscriptions';
		}
		
		return $plugins;
	}
	
	/**
	 * Check required plugins and push notifications
	 */
	public function checkRequirePlugins() {
		/* translators: %s: required plugin */
		$message = __( 'The Discounts for WooCommerce Subscriptions plugin requires %s plugin to be active!',
			'discounts-for-woocommerce-subscriptions' );
		
		$plugins = $this->validateRequiredPlugins();
		
		if ( count( $plugins ) ) {
			foreach ( $plugins as $plugin ) {
				$error = sprintf( $message, $plugin );
				$this->getContainer()->getAdminNotifier()->push( $error, AdminNotifier::ERROR, false );
			}
			
			return false;
		}
		
		return true;
	}
	
	public static function getSupportedSimpleProductTypes() {
		return apply_filters( 'subscription_discounts/frontend/supported_simple_product_types', array(
			'subscription',
		) );
	}
	
	public static function getSupportedVariableProductTypes() {
		return apply_filters( 'subscription_discounts/frontend/supported_variable_product_types', array(
			'variable-subscription',
			'subscription_variation',
		) );
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		set_transient( 'discounts_for_woocommerce_subscriptions_activated', true, 100 );
	}
	
}
