<?php namespace MeowCrew\SubscriptionsDiscounts\Managers;

use Exception;
use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;
use WC_Order;
use WC_Order_Item_Product;
use WC_Subscription;

class RenewalManager {

	public function __construct() {

		// Recalculate the discounts when subscription is switched
		add_action( 'woocommerce_subscriptions_switch_completed', function ( WC_Order $order ) {
			$subscription = wcs_get_subscriptions_for_order( $order );

			if ( ! $subscription ) {
				return;
			}

			if ( is_array( $subscription ) ) {
				$subscription = array_values( $subscription )[0];
			}

			self::applyDiscounts( $subscription, false );
		} );

		// Main function to recalculate discounts for subscription
		add_action( 'woocommerce_payment_complete', function ( $orderId ) {

			$order = wc_get_order( $orderId );

			$subscription = wcs_get_subscriptions_for_order( $order );

			if ( ! $subscription ) {

				$subscription = wcs_get_subscription( $order->get_meta( '_subscription_renewal' ) );

				if ( ! $subscription ) {

					// Check for early renewals
					$cart_item = wcs_cart_contains_early_renewal();

					if ( $cart_item ) {
						$subscriptionId = $cart_item['subscription_renewal']['subscription_id'];
					} else {
						$subscriptionId = false;
					}

					$subscription = wcs_get_subscription( $subscriptionId );
				}
			}

			if ( ! $subscription ) {
				return;
			}

			if ( is_array( $subscription ) ) {
				$subscription = array_values( $subscription )[0];
			}

			self::applyDiscounts( $subscription, false );
		}, 10, 99 );

		add_action( 'woocommerce_order_status_changed', function ( $orderId, $from, $to ) {

			if ( ! in_array( $to, array( 'processing', 'completed' ) ) ) {
				return;
			}

			$order = wc_get_order( $orderId );

			$subscription = wcs_get_subscriptions_for_order( $order );

			if ( ! $subscription ) {
				return;
			}

			if ( is_array( $subscription ) ) {
				$subscription = array_values( $subscription )[0];
			}

			self::applyDiscounts( $subscription, false );
		}, 10, 3 );

		// Recalculate after subscription was reactivated
		add_action( 'subscriptions_activated_for_order', function ( $orderId ) {
			$order = wc_get_order( $orderId );

			$subscription = wcs_get_subscriptions_for_order( $order );

			if ( ! $subscription ) {
				return;
			}

			if ( is_array( $subscription ) ) {
				$subscription = array_values( $subscription )[0];
			}

			self::applyDiscounts( $subscription, false );
		}, 10, 99 );

		add_action( 'woocommerce_checkout_create_subscription',
			function ( WC_Subscription $subscription, $posted_data, WC_Order $order, $cart ) {
				self::applyDiscounts( $subscription );
			}, 10, 4 );

		add_filter( '____wcs_renewal_order_created', function ( WC_Order $order, WC_Subscription $subscription ) {
			self::applyDiscounts( $subscription );

			return $order;
		}, 10, 2 );

	}

	public static function applyDiscounts( WC_Subscription $subscription, $isForRenewal = true ) {

		foreach ( $subscription->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				try {
					$discountedItem = new DiscountedOrderItem( $item, $subscription );

					if ( $discountedItem->isDiscountedItem() ) {

						// Calculate for the upfront renewal. One upfront and one current renewal that is not taken into account in calculation total renewals.
						$totalRenewals = $discountedItem->getTotalRenewals() + 1;

						if ( $isForRenewal ) {
							$totalRenewals ++;
						}

						$newDiscount = $discountedItem->calculateAppliedDiscount( $totalRenewals );

						if ( $newDiscount !== $discountedItem->getAppliedDiscount() ) {

							if ( $discountedItem->applyNewDiscount( $newDiscount ) ) {
								$subscription->add_order_note( __( 'A new discount tier started to apply from the last renewal.',
									'discounts-for-woocommerce-subscriptions' ) );
							}
						}
					}

				} catch ( Exception $exception ) {
					continue;
				}
			}
		}
	}
}
