<?php namespace MeowCrew\SubscriptionsDiscounts;

use WC_Product;

class DiscountsManager {
	
	/**
	 * Return fixed price rules or empty array if not exist rules
	 *
	 * @param $product_id
	 * @param  string  $context
	 *
	 * @return array
	 */
	public static function getFixedDiscounts( $product_id, $context = 'view' ) {
		return self::getDiscounts( $product_id, 'fixed', $context );
	}
	
	/**
	 * Return percentage price rules or empty array if not exist rules
	 *
	 * @param $product_id
	 * @param  string  $context
	 *
	 * @return array
	 */
	public static function getPercentageDiscounts( $product_id, $context = 'view' ) {
		return self::getDiscounts( $product_id, 'percentage', $context );
	}
	
	/**
	 * Get product pricing rules
	 *
	 * @param  int  $product_id
	 * @param  bool  $type
	 * @param  string  $context
	 * @param  bool  $removeFirstPaymentRule
	 *
	 * @return array
	 */
	public static function getDiscounts(
		$product_id,
		$type = false,
		$context = 'view',
		$removeFirstPaymentRule = true
	) {
		
		$type = $type ? $type : self::getDiscountsType( $product_id, 'fixed', $context );
		
		if ( 'fixed' === $type ) {
			$rules = get_post_meta( $product_id, '_fixed_subscription_discounts', true );
		} else {
			$rules = get_post_meta( $product_id, '_percentage_subscription_discounts', true );
		}
		
		$parent_id = $product_id;
		
		// If no rules for variation check for product level rules.
		if ( 'edit' !== $context && self::variationHasNoOwnDiscounts( $product_id, $rules ) ) {
			
			$product = wc_get_product( $product_id );
			
			if ( ! $product ) {
				return array();
			}
			
			$parent_id = $product->get_parent_id();
			
			$type = self::getDiscountsType( $parent_id );
			
			if ( 'fixed' === $type ) {
				$rules = get_post_meta( $parent_id, '_fixed_subscription_discounts', true );
			} else {
				$rules = get_post_meta( $parent_id, '_percentage_subscription_discounts', true );
			}
		}
		
		$rules = ! empty( $rules ) ? $rules : array();
		
		ksort( $rules );
		
		if ( 'edit' !== $context ) {
			$rules = apply_filters( 'subscription_discounts/price/product_price_rules', $rules, $product_id, $type,
				$parent_id );
			
			if ( $removeFirstPaymentRule ) {
				$rules = self::removeFirstPaymentRule( $rules );
			}
		}
		
		return $rules;
	}
	
	public static function getFirstPaymentPrice( $productId, $rules = array() ) {
		
		$rules = $rules ? $rules : self::getDiscounts( $productId, null, null, false );
		
		if ( array_key_exists( 1, $rules ) ) {
			return $rules[1];
		}
		
		return false;
	}
	
	protected static function removeFirstPaymentRule( $rules ) {
		if ( array_key_exists( 1, $rules ) ) {
			unset( $rules[1] );
		}
		
		return $rules;
	}
	
	/**
	 * Get price by product quantity
	 *
	 * @param  int  $quantity
	 * @param  int  $product_id
	 * @param  string  $context
	 * @param  string  $place
	 * @param  bool  $additional_discount
	 *
	 * @return bool|float|int
	 */
	public static function getPriceByRules(
		$quantity,
		$product_id,
		$context = 'view',
		$place = 'shop',
		$product_price = false,
		$rules = null,
		$rulesType = null
	) {
		
		$rules = $rules ? $rules : self::getDiscounts( $product_id, null, null, false );
		
		$type = $rulesType ? $rulesType : self::getDiscountsType( $product_id );
		
		if ( 'fixed' === $type ) {
			foreach ( array_reverse( $rules, true ) as $_amount => $price ) {
				if ( $_amount <= $quantity ) {
					
					$product_price = $price;
					
					if ( 'view' === $context ) {
						$product = wc_get_product( $product_id );
						
						$product_price = self::getPriceWithTaxes( $product_price, $product, $place );
					}
					
					break;
				}
			}
		}
		
		if ( 'percentage' === $type ) {
			$product = wc_get_product( $product_id );
			
			foreach ( array_reverse( $rules, true ) as $_amount => $percentDiscount ) {
				if ( $_amount <= $quantity ) {
					
					$product_price = $product_price ? $product_price : $product->get_price();
					
					$product_price = self::getPriceByPercentDiscount( $product_price, $percentDiscount );
					
					if ( 'view' === $context ) {
						
						$product = wc_get_product( $product_id );
						
						$product_price = self::getPriceWithTaxes( $product_price, $product, $place );
					}
					
					break;
				}
			}
		}
		
		$product_price = isset( $product_price ) ? $product_price : false;
		
		return apply_filters( 'subscription_discounts/price/price_by_rules', $product_price, $quantity, $product_id,
			$context, $place );
	}
	
	/**
	 * Calculate displayed price depend on taxes
	 *
	 * @param  float  $price
	 * @param  WC_Product  $product
	 * @param  string  $place
	 *
	 * @return float
	 */
	public static function getPriceWithTaxes( $price, $product, $place = 'shop' ) {
		
		if ( wc_tax_enabled() ) {
			
			if ( 'cart' === $place ) {
				$price = 'incl' === get_option( 'woocommerce_tax_display_cart' ) ?
					
					wc_get_price_including_tax( $product, array(
						'qty'   => 1,
						'price' => $price,
					) ) :
					
					wc_get_price_excluding_tax( $product, array(
						'qty'   => 1,
						'price' => $price,
					) );
			} else {
				$price = wc_get_price_to_display( $product, array(
					'price' => $price,
					'qty'   => 1,
				) );
			}
		}
		
		return $price;
	}
	
	
	/**
	 * Calculate price using percentage discount
	 *
	 * @param  float|int  $price
	 * @param  float|int  $discount
	 *
	 * @return bool|float|int
	 */
	public static function getPriceByPercentDiscount( $price, $discount ) {
		if ( $price > 0 && $discount <= 100 ) {
			$discount_amount = ( $price / 100 ) * $discount;
			
			return $price - $discount_amount;
		}
		
		return false;
	}
	
	/**
	 * Get pricing type of product. Available: fixed or percentage
	 *
	 * @param  int  $product_id
	 * @param  string  $default
	 * @param  string  $context
	 *
	 * @return string
	 */
	public static function getDiscountsType( $product_id, $default = 'fixed', $context = 'view' ) {
		
		$type = get_post_meta( $product_id, '_subscriptions_discounts_type', true );
		
		if ( 'view' === $context && self::variationHasNoOwnDiscounts( $product_id ) ) {
			$product = wc_get_product( $product_id );
			
			$type = get_post_meta( $product->get_parent_id(), '_subscriptions_discounts_type', true );
		}
		
		$type = in_array( $type, array( 'fixed', 'percentage' ) ) ? $type : $default;
		
		return apply_filters( 'subscription_discounts/price/type', $type, $product_id, $context );
	}
	
	/**
	 * Update price rules
	 *
	 * @param  array  $amounts
	 * @param  array  $prices
	 * @param  int  $product_id
	 */
	public static function updateFixedDiscounts( $amounts, $prices, $product_id ) {
		$rules = array();
		
		foreach ( $amounts as $key => $amount ) {
			if ( ! empty( $amount ) && isset( $prices[ $key ] ) && ! key_exists( $amount, $rules ) ) {
				$rules[ $amount ] = wc_format_decimal( $prices[ $key ] );
			}
		}
		
		$rules = SanitizeManager::sanitizeDiscountsRules( $rules );
		
		update_post_meta( $product_id, '_fixed_subscription_discounts', $rules );
	}
	
	/**
	 * Update price rules
	 *
	 * @param  array  $amounts
	 * @param  array  $percents
	 * @param  int  $product_id
	 */
	public static function updatePercentageDiscounts( $amounts, $percents, $product_id ) {
		$rules = array();
		
		foreach ( $amounts as $key => $amount ) {
			
			if ( ! empty( $amount ) && isset( $percents[ $key ] ) && ! key_exists( $amount,
					$rules ) && $percents[ $key ] <= 100 ) {
				$rules[ $amount ] = $percents[ $key ];
			}
		}
		
		$rules = SanitizeManager::sanitizeDiscountsRules( $rules );
		
		update_post_meta( $product_id, '_percentage_subscription_discounts', $rules );
	}
	
	/**
	 * Update product pricing type
	 *
	 * @param  int  $product_id
	 * @param  string  $type
	 */
	public static function updateDiscountsType( $product_id, $type ) {
		if ( in_array( $type, array( 'percentage', 'fixed' ) ) ) {
			update_post_meta( $product_id, '_subscriptions_discounts_type', $type );
		}
	}
	
	/**
	 * Check if variation has no own rules
	 *
	 * @param  int  $product_id
	 * @param  bool  $rules
	 *
	 * @return bool
	 */
	protected static function variationHasNoOwnDiscounts( $product_id, $rules = false ) {
		
		$rules = $rules ? $rules : self::getDiscounts( $product_id, false, 'edit' );
		
		if ( empty( $rules ) ) {
			
			$product = wc_get_product( $product_id );
			
			return $product && $product->is_type( 'variation' );
		}
		
		return false;
	}
}
