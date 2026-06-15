<?php namespace MeowCrew\SubscriptionsDiscounts\Frontend;

use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;
use WC_Order_Item_Product;

class CartManager {

	public function __construct() {

		// Adjust renewals cart to respect next renewal discount if exists
		add_filter( 'woocommerce_before_calculate_totals', function ( \WC_Cart $cart ) {

			if ( property_exists( $cart, 'recurring_cart_key' ) ) {

				$newCartContents = $cart->get_cart_contents();

				foreach ( $cart->get_cart_contents() as $key => $cartItem ) {
					$productId = ! empty( $cartItem['variation_id'] ) ? $cartItem['variation_id'] : $cartItem['product_id'];
					$discounts = self::getProductDiscounts( $productId );
		
					if ( array_key_exists( 2, $discounts ) ) {

						// Do not modify price for 2nd payment when there is a free trial.
						// 2nd payment in this case should be the same as 1st for a subscription without a trial.
						if ( $cartItem['data'] instanceof \WC_Product_Subscription ) {
							$trialLength = $cartItem['data']->get_meta( '_subscription_trial_length' );
							if ( $trialLength ) {
								continue;
							}
						}

						$newCartContents[ $key ]['data'] = clone $cartItem['data'];
						$discountsType                   = self::getProductDiscountsType( $productId );

						if ( 'fixed' === $discountsType ) {
							$newCartContents[ $key ]['data']->set_price( $discounts[2] );
						} else {

							if ( DiscountsManager::getFirstPaymentPrice( $productId, $discounts ) ) {
								$newPrice = DiscountsManager::getPriceByPercentDiscount( $newCartContents[ $key ]['data']->get_regular_price(),
									$discounts[2] );
							} else {
								$newPrice = DiscountsManager::getPriceByPercentDiscount( $newCartContents[ $key ]['data']->get_price(),
									$discounts[2] );
							}

							$newCartContents[ $key ]['data']->set_price( $newPrice );
						}
					}
				}

				$cart->set_cart_contents( $newCartContents );
			}
		} );

		add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				DiscountedOrderItem::addDiscounts( $item );
			}
		}, 10, 4 );

		add_action( 'wp_head', function () {

			if ( is_checkout() ) {
				$cart = wc()->cart;

				if ( ! empty( $cart->cart_contents ) ) {
					foreach ( $cart->get_applied_coupons() as $coupon ) {
						if ( 'discount_renewal' === $coupon ) {
							?>
							<style>
								.woocommerce-remove-coupon {
									display: none;
								}
							</style>
							<?php
							break;
						}
					}
				}
			}
		} );

		// Force enable payments for discounted items.
		add_filter( 'woocommerce_order_needs_payment', function ( $needsPayment, \WC_Order $order ) {

			if ( $needsPayment ) {
				return $needsPayment;
			}

			foreach ( $order->get_items() as $item ) {
				try {
					$discountedOrderItem = new DiscountedOrderItem( $item );
				} catch ( \Exception $e ) {
					return $needsPayment;
				}

				if ( $discountedOrderItem->isDiscountedItem() ) {
					return true;
				}
			}

			return $needsPayment;
		}, 20, 2 );

		// Force enable payments for discounted items.
		add_filter( 'woocommerce_cart_needs_payment', function ( $needsPayment, $cart ) {

			if ( $needsPayment ) {
				return $needsPayment;
			}

			if ( ! empty( WC()->cart->recurring_carts ) ) {
				foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
					foreach ( $recurring_cart->cart_contents as $cartItem ) {
						$productId = ! empty( $cartItem['variation_id'] ) ? $cartItem['variation_id'] : $cartItem['product_id'];
						$discounts = self::getProductDiscounts( $productId );

						if ( ! empty( $discounts ) ) {
							return true;
						}
					}
				}
			}

			return $needsPayment;
		}, 20, 2 );
	}

	public static function getProductDiscounts( $productId ) {
		return apply_filters( 'subscription_discounts/cart/cart_item_discounts',
			DiscountsManager::getDiscounts( $productId, null, null, false ), $productId );
	}

	public static function getProductDiscountsType( $productId ) {
		return apply_filters( 'subscription_discounts/cart/cart_item_discounts_type',
			DiscountsManager::getDiscountsType( $productId ), $productId );
	}
}
