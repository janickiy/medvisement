<?php namespace MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing;

use WC_Product;

class RoleBasedSaleRegularPricingManager {

	public function __construct() {

		add_filter( 'woocommerce_product_get_regular_price', array(
			$this,
			'adjustRegularPrice'
		), 98, 2 );

		add_filter( 'woocommerce_product_get_sale_price', array(
			$this,
			'adjustSalePrice'
		), 98, 2 );

		add_filter( 'woocommerce_product_get_price', array(
			$this,
			'adjustPrice'
		), 98, 2 );

		// Variations
		add_filter( 'woocommerce_product_variation_get_regular_price', array(
			$this,
			'adjustRegularPrice'
		), 98, 2 );

		add_filter( 'woocommerce_product_variation_get_sale_price', array(
			$this,
			'adjustSalePrice'
		), 98, 2 );

		add_filter( 'woocommerce_product_variation_get_price', array(
			$this,
			'adjustPrice'
		), 98, 2 );

		// Variable (price range)
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'adjustPrice' ), 99, 3 );

		// Variation
		add_filter( 'woocommerce_variation_prices_regular_price', array(
			$this,
			'adjustRegularPrice'
		), 98, 3 );

		add_filter( 'woocommerce_variation_prices_sale_price', array(
			$this,
			'adjustSalePrice'
		), 98, 3 );

		// Price caching
		add_filter( 'woocommerce_get_variation_prices_hash', function ( $hash ) {

			$user = wp_get_current_user();

			if ( $user ) {
				$hash[] = md5( json_encode( $user->roles ) );
			}

			return $hash;

		}, 98, 3 );

	}

	public function adjustPrice( $price, WC_Product $product ) {

		$rolePrice = $this->getRolePrice( $product );

		return $rolePrice ? $rolePrice : $price;
	}

	public function adjustSalePrice( $price, WC_Product $product ) {

		$rolePrice = $this->getRolePrice( $product, 'sale' );
		return $rolePrice ? (float) $rolePrice : $price;
	}

	public function adjustRegularPrice( $price, WC_Product $product ) {
		$rolePrice = $this->getRolePrice( $product, 'regular' );

		return $rolePrice ? (float) $rolePrice : $price;
	}

	protected function getRolePrice( WC_Product $product, $specific = false ) {
		$userRoles = $this->getCurrentUserRoles();

		if ( ! empty( $userRoles ) ) {
			foreach ( $userRoles as $role ) {

				$roleSalePrice    = RoleBasedPriceManager::getSalePrice( $product->get_id(), $role );
				$roleRegularPrice = RoleBasedPriceManager::getRegularPrice( $product->get_id(), $role );

				if ( $specific ) {
					if ( 'sale' === $specific && $roleSalePrice ) {
						return $roleSalePrice;
					} else if ( 'regular' === $specific && $roleRegularPrice ) {
						return $roleRegularPrice;
					}
				} else {
					if ( $roleSalePrice ) {
						return $roleSalePrice;
					} else if ( $roleRegularPrice ) {
						return $roleRegularPrice;
					}
				}
			}
		}

		return null;
	}

	protected function getCurrentUserRoles() {
		$roles = array();
		$user  = wp_get_current_user();

		if ( $user ) {
			$roles = ( array ) $user->roles;
		}

		return $roles;
	}
}
