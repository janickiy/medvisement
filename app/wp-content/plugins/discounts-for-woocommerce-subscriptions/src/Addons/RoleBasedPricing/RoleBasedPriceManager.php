<?php namespace MeowCrew\SubscriptionsDiscounts\Addons\RoleBasedPricing;

class RoleBasedPriceManager {

	public static function roleHasRules( $role, $product_id, $context = 'view' ) {

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		$parentRoleRulesExists = false;

		$productRoleRulesExists = metadata_exists( 'post', $product_id,
				"_{$role}_percentage_subscription_discounts" ) || metadata_exists( 'post', $product_id,
				"_{$role}_fixed_subscription_discounts" ) || metadata_exists( 'post', $product_id,
				"_{$role}_subscriptions_discounts_type" ) || metadata_exists( 'post', $product_id,
				"_{$role}_subscriptions_discounts_sale_price" ) || metadata_exists( 'post', $product_id,
				"_{$role}_subscriptions_discounts_regular_price" );


		if ( $product->is_type( 'variation' ) && 'edit' !== $context ) {

			$parentRoleRulesExists = metadata_exists( 'post', $product->get_parent_id(),
					"_{$role}_percentage_subscription_discounts" ) || metadata_exists( 'post',
					$product->get_parent_id(), "_{$role}_fixed_subscription_discounts" ) || metadata_exists( 'post',
					$product->get_parent_id(), "_{$role}_subscriptions_discounts_type" ) || metadata_exists( 'post',
					$product->get_parent_id(),
					"_{$role}_subscriptions_discounts_sale_price" ) || metadata_exists( 'post',
					$product->get_parent_id(), "_{$role}_subscriptions_discounts_regular_price" );

		}

		return $productRoleRulesExists || $parentRoleRulesExists;
	}

	public static function deleteAllDataForRole( $product_id, $role ) {
		delete_post_meta( $product_id, "_{$role}_percentage_subscription_discounts" );
		delete_post_meta( $product_id, "_{$role}_fixed_subscription_discounts" );
		delete_post_meta( $product_id, "_{$role}_subscriptions_discounts_type" );
		delete_post_meta( $product_id, "_{$role}_subscriptions_discounts_sale_price" );
		delete_post_meta( $product_id, "_{$role}_subscriptions_discounts_regular_price" );
	}

	/**
	 * Return fixed price rules or empty array if not exist rules
	 *
	 * @param  int  $product_id
	 * @param  string  $role
	 * @param  string  $context
	 *
	 * @return array
	 */
	public static function getFixedDiscounts( $product_id, $role, $context = 'view' ) {
		return self::getDiscounts( $product_id, $role, 'fixed', $context );
	}

	/**
	 * Return percentage price rules or empty array if not exist rules
	 *
	 * @param $product_id
	 * @param $role
	 * @param  string  $context
	 *
	 * @return array
	 */
	public static function getPercentageDiscounts( $product_id, $role, $context = 'view' ) {
		return self::getDiscounts( $product_id, $role, 'percentage', $context );
	}

	/**
	 * Get product pricing rules for role
	 *
	 * @param  int  $product_id
	 * @param  string  $role
	 * @param  bool  $type
	 * @param  string  $context
	 *
	 * @return array
	 */
	public static function getDiscounts(
		$product_id,
		$role,
		$type = false,
		$context = 'view'
	) {

		$type = $type ? $type : self::getDiscountsType( $product_id, $role, 'fixed', $context );

		if ( 'fixed' === $type ) {
			$rules = get_post_meta( $product_id, "_{$role}_fixed_subscription_discounts", true );
		} else {
			$rules = get_post_meta( $product_id, "_{$role}_percentage_subscription_discounts", true );
		}

		// If no rules for variation check for product level rules.
		if ( 'edit' !== $context && self::variationHasNoOwnDiscounts( $product_id, $role, $rules ) ) {

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				return array();
			}

			$product_id = $product->get_parent_id();

			$type = self::getDiscountsType( $product_id, $role );

			if ( 'fixed' === $type ) {
				$rules = get_post_meta( $product_id, "_{$role}_fixed_subscription_discounts", true );
			} else {
				$rules = get_post_meta( $product_id, "_{$role}_percentage_subscription_discounts", true );
			}
		}

		$rules = ! empty( $rules ) ? $rules : array();

		ksort( $rules );

		if ( 'edit' !== $context ) {

			$rules = apply_filters( 'subscription_discounts/role_based_rules/price/product_price_rules', $rules,
				$product_id, $type );
		}

		return $rules;
	}

	/**
	 * Get pricing type of product. Available: fixed or percentage
	 *
	 * @param  int  $product_id
	 * @param  string  $role
	 * @param  string  $default
	 * @param  string  $context
	 *
	 * @return string
	 */
	public static function getDiscountsType( $product_id, $role, $default = 'fixed', $context = 'view' ) {

		$type = get_post_meta( $product_id, "_{$role}_subscriptions_discounts_type", true );

		// think about it
		if ( 'view' === $context && self::variationHasNoOwnDiscounts( $product_id, $role ) ) {
			$product = wc_get_product( $product_id );

			$type = get_post_meta( $product->get_parent_id(), "_{$role}_subscriptions_discounts_type", true );
		}

		$type = in_array( $type, array( 'fixed', 'percentage' ) ) ? $type : $default;

		if ( 'edit' !== $context ) {
			return apply_filters( 'subscription_discounts/role_based_rules/price/type', $type, $role, $product_id );
		}

		return $type;
	}

	public static function getSalePrice( $productId, $role, $context = 'view' ) {

		$salePrice = get_post_meta( $productId, "_{$role}_subscriptions_discounts_sale_price", true );

		if ( 'view' === $context && self::variationHasNoOwnPrice( $productId, $role, 'sale' ) ) {
			$product = wc_get_product( $productId );

			$salePrice = get_post_meta( $product->get_parent_id(), "_{$role}_subscriptions_discounts_sale_price",
				true );
		}

		if ( 'edit' !== $context ) {
			$salePrice = apply_filters( 'subscription_discounts/role_based_rules/sale_price', $salePrice, $role,
				$productId );
		}

		return $salePrice;
	}

	public static function getRegularPrice( $productId, $role, $context = 'view' ) {

		$regularPrice = get_post_meta( $productId, "_{$role}_subscriptions_discounts_regular_price", true );

		if ( 'view' === $context && self::variationHasNoOwnPrice( $productId, $role, 'regular' ) ) {
			$product = wc_get_product( $productId );

			$regularPrice = get_post_meta( $product->get_parent_id(), "_{$role}_subscriptions_discounts_regular_price",
				true );
		}

		if ( 'edit' !== $context ) {
			$regularPrice = apply_filters( 'subscription_discounts/role_based_rules/regular_price', $regularPrice,
				$role, $productId );
		}

		return $regularPrice;
	}

	public static function updateSalePrice( $productId, $salePrice, $role ) {
		update_post_meta( $productId, "_{$role}_subscriptions_discounts_sale_price", $salePrice );
	}

	public static function updateRegularPrice( $productId, $regularPrice, $role ) {
		update_post_meta( $productId, "_{$role}_subscriptions_discounts_regular_price", $regularPrice );
	}

	/**
	 * Update price rules for certain role
	 *
	 * @param  array  $amounts
	 * @param  array  $prices
	 * @param  int  $product_id
	 * @param  string  $role
	 */
	public static function updateFixedDiscounts( $amounts, $prices, $product_id, $role ) {
		$rules = array();

		foreach ( $amounts as $key => $amount ) {
			if ( ! empty( $amount ) && ! empty( $prices[ $key ] ) && ! key_exists( $amount, $rules ) ) {
				$rules[ $amount ] = wc_format_decimal( $prices[ $key ] );
			}
		}

		update_post_meta( $product_id, "_{$role}_fixed_subscription_discounts", $rules );
	}

	/**
	 * Update price rules for certain role
	 *
	 * @param  array  $amounts
	 * @param  array  $percents
	 * @param  int  $product_id
	 * @param  string  $role
	 */
	public static function updatePercentageDiscounts( $amounts, $percents, $product_id, $role ) {
		$rules = array();

		foreach ( $amounts as $key => $amount ) {
			if ( ! empty( $amount ) && ! empty( $percents[ $key ] ) && ! key_exists( $amount,
					$rules ) && $percents[ $key ] < 99 ) {
				$rules[ $amount ] = $percents[ $key ];
			}
		}

		update_post_meta( $product_id, "_{$role}_percentage_subscription_discounts", $rules );
	}

	/**
	 * Update product pricing type for role
	 *
	 * @param  int  $product_id
	 * @param  string  $type
	 * @param  string  $role
	 */
	public static function updateDiscountsType( $product_id, $type, $role ) {
		if ( in_array( $type, array( 'percentage', 'fixed' ) ) ) {
			update_post_meta( $product_id, "_{$role}_subscriptions_discounts_type", $type );
		}
	}

	/**
	 * Check if variation has no own rules
	 *
	 * @param  int  $product_id
	 * @param  string  $role
	 * @param  bool  $rules
	 *
	 * @return bool
	 */
	protected static function variationHasNoOwnDiscounts( $product_id, $role, $rules = false ) {

		$rules = $rules ? $rules : self::getDiscounts( $product_id, $role, false, 'edit' );

		if ( empty( $rules ) ) {

			$product = wc_get_product( $product_id );

			return $product ? $product->is_type( 'variation' ) : false;
		}

		return false;
	}

	/**
	 * Check if variation has no own role-based pricing
	 *
	 * @param  int  $product_id
	 * @param  string  $role
	 * @param  string  $type
	 *
	 * @return bool
	 */
	protected static function variationHasNoOwnPrice( $product_id, $role, $type ) {

		if ( 'sale' === $type ) {
			$price = self::getSalePrice( $product_id, $role, 'edit' );
		} else {
			$price = self::getRegularPrice( $product_id, $role, 'edit' );
		}

		if ( $price ) {

			$product = wc_get_product( $product_id );

			return $product->is_type( 'subscription-variation' );
		}

		return false;
	}
}
