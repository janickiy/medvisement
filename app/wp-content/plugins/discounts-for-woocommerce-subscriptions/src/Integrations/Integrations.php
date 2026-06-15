<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations;

use MeowCrew\SubscriptionsDiscounts\Integrations\Plugins\WooCommerceProductAddons;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Astra;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Avada;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Divi;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Electro;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Flatsome;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Merchandiser;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Neto;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\OceanWp;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Porto;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\Shopkeeper;
use MeowCrew\SubscriptionsDiscounts\Integrations\Themes\TheRetailer;
use MeowCrew\SubscriptionsDiscounts\Integrations\Plugins\AllProductsForSubscriptions\AllProductForSubscriptions;

class Integrations {

	public function __construct() {
		$this->init();
	}

	public function init() {
		$themes = apply_filters( 'subscription_discounts/integrations/themes', array(
			'avada'        => Avada::class,
			'astra'        => Astra::class,
			'divi'         => Divi::class,
			'oceanWP'      => OceanWp::class,
			'flatsome'     => Flatsome::class,
			'shopkeeper'   => Shopkeeper::class,
			'the retailer' => TheRetailer::class,
			'merchandiser' => Merchandiser::class,
			'electro'      => Electro::class,
			'porto'        => Porto::class,
		) );

		$plugins = apply_filters( 'subscription_discounts/integrations/plugins', array(
			AllProductForSubscriptions::class,
			WooCommerceProductAddons::class,
		) );

		foreach ( $themes as $themeName => $theme ) {
			if ( strpos( strtolower( wp_get_theme()->name ),
					$themeName ) !== false || ( ! empty( wp_get_theme()->template ) && strpos( strtolower( wp_get_theme()->template ),
						$themeName ) !== false ) ) {
				new $theme();
			}
		}

		foreach ( $plugins as $plugin ) {
			new $plugin();
		}
	}
}
