<?php namespace MeowCrew\SubscriptionsDiscounts\Addons;

use MeowCrew\SubscriptionsDiscounts\Addons\Coupons\CouponsAddon;
use MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing\RoleBasedPricingAddon;

class Addons {

	/**
	 * Addons constructor.
	 */
	public function __construct() {
		$this->init();
	}

	public function init() {

		$addons = apply_filters( 'subscription_discounts/addons/list', array(
			'RoleBasedPricing'  => new RoleBasedPricingAddon(),
			CouponsAddon::class => new CouponsAddon(),
		) );

		foreach ( $addons as $key => $addon ) {
			if ( $addon->isActive() ) {
				$addon->run();
			}
		}
	}
}
