<?php namespace MeowCrew\SubscriptionsDiscounts\Admin;

use MeowCrew\SubscriptionsDiscounts\Admin\Subscription\SubscriptionPage;
use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\SubscriptionsDiscountsPlugin;
use MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers\ProductManager;
use MeowCrew\SubscriptionsDiscounts\Core\FileManager;
use MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers\SimpleProductManager;
use MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers\VariationProductManager;
use MeowCrew\SubscriptionsDiscounts\Admin\Export\Woocommerce as WooCommerceExport;
use MeowCrew\SubscriptionsDiscounts\Admin\Import\Woocommerce as WooCommerceImport;
use MeowCrew\SubscriptionsDiscounts\Admin\Import\WPAllImport;

/**
 * Class Admin
 *
 * @package MeowCrew\SubscriptionsDiscounts\Admin
 */
class Admin {

	use ServiceContainerTrait;

	/**
	 * /**
	 * Admin constructor.
	 *
	 * Register menu items and handlers
	 */
	public function __construct() {

		$this->initManagers();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );

		add_action( 'admin_notices', array( $this, 'showActivationMessage' ) );
	
	}

	/**
	 * Init Managers
	 */
	public function initManagers() {
		$managers = apply_filters( 'subscription_discounts/admin/managers', array(
			ProductManager::class          => array(),
			VariationProductManager::class => array(),
			SubscriptionPage::class        => array(),
			WooCommerceExport::class       => array(),
			WooCommerceImport::class       => array(),
			WPAllImport::class             => array()
		) );

		foreach ( $managers as $managerClass => $args ) {
			if ( class_exists( $managerClass ) ) {
				new $managerClass( ...$args );
			}
		}
	}

	/**
	 * Show message about activation plugin and advise next step
	 */
	public function showActivationMessage() {

		if ( get_transient( 'discounts_for_woocommerce_subscriptions_activated' ) ) {

			$link = $this->getContainer()->getSettings()->getLink();

			$this->getContainer()->getFileManager()->includeTemplate( 'admin/alerts/activation-alert.php', array( 'link' => $link ) );

			delete_transient( 'discounts_for_woocommerce_subscriptions_activated' );
		}

	}

	/**
	 * Register assets on product create/update page
	 *
	 * @param $page
	 */
	public function enqueueAssets( $page ) {

		wp_enqueue_script( 'discounts-for-woocommerce-subscriptions-admin-js', $this->getContainer()->getFileManager()->locateAsset( 'admin/main.js' ),
			array( 'jquery' ), SubscriptionsDiscountsPlugin::VERSION );
		wp_enqueue_style( 'discounts-for-woocommerce-subscriptions-admin-css', $this->getContainer()->getFileManager()->locateAsset( 'admin/style.css' ),
			array(), SubscriptionsDiscountsPlugin::VERSION );

	}
}
