<?php namespace MeowCrew\SubscriptionsDiscounts\Frontend;

use ArrayIterator;
use Exception;
use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;
use WC_Order_Item_Product;
use WC_Subscription;

class MyAccount {

	public function __construct() {
		add_action( 'wcs_subscription_details_table_before_payment_method', function ( WC_Subscription $subscription ) {

			$itemsNumber = $subscription->get_item_count();

			foreach ( $subscription->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product ) {

					try {
						$discountedItem = new DiscountedOrderItem( $item, $subscription );

						if ( ! $discountedItem->isDiscountedItem() ) {
							continue;
						}

						$clarification = '';

						if ( $itemsNumber > 1 ) {
							$clarification = sprintf( ' (%s)', $item->get_name() );
						}

						?>

						<tr>
							<td><?php echo esc_html( __( 'Current price change', 'discounts-for-woocommerce-subscriptions' ) . $clarification ); ?></td>
							<td>
								<?php

								$appliedDiscount = $discountedItem->getAppliedDiscount();
								$appliedDiscount = isset( $discountedItem->getDiscounts()[ $appliedDiscount ] ) ? floatval( $discountedItem->getDiscounts()[ $appliedDiscount ] ) : false;

								if ( false === $appliedDiscount ) {
									echo esc_html( '-' );
								} else {
									if ( $discountedItem->getDiscountsType() === 'percentage' ) {

										$sign = $appliedDiscount > 0 ? '-' : '+';

										echo esc_html( $sign . abs( $appliedDiscount ) . '%' );

									} else {

										$discount = DiscountedOrderItem::getPercentageDiscountBetweenPrices( $discountedItem->getOriginalPrice(), $appliedDiscount );

										if ( is_null( $discount ) ) {
											echo '-';
										} else {

											if ( $discount > 0 ) {
												if ( $appliedDiscount > $discountedItem->getOriginalPrice() ) {
													echo esc_html( '+' . $discount . '%' );
												} else {
													echo esc_html( '-' . $discount . '%' );
												}
											} else {
												echo esc_html( '0%' );
											}
										}
									}
								}

								?>
							</td>
						</tr>
						<?php unset( $appliedDiscount ); ?>
						<tr style="display:none;">
							<td><?php echo esc_html( __( 'Price upgrade', 'discounts-for-woocommerce-subscriptions' ) . $clarification ); ?></td>
							<td>
								<?php
								$appliedDiscount        = false;
								$currentDiscount        = $discountedItem->getAppliedDiscount();
								$totalRenewals          = $discountedItem->getTotalRenewals();
								$discountsIterator      = new ArrayIterator( $discountedItem->getDiscounts() );
								$renewalsToNextDiscount = '';

								if ( $currentDiscount ) {

									while ( $discountsIterator->key() !== $currentDiscount && $discountsIterator->valid() ) {
										$discountsIterator->next();
									}

									$discountsIterator->next();
									if ( $discountsIterator->valid() ) {
										$appliedDiscount        = $discountsIterator->current();
										$renewalsToNextDiscount = $discountsIterator->key() - $totalRenewals;
									}
								} else {
									$appliedDiscount        = $discountsIterator->current();
									$renewalsToNextDiscount = $discountsIterator->key() - $totalRenewals;
								}

								if ( $renewalsToNextDiscount ) {
									// Show for the future renewals
									$renewalsToNextDiscount --;
								}


								if ( $discountedItem->getDiscountsType() === 'percentage' ) {

									if ( 0 === $appliedDiscount ) {
										echo '-';

										return;
									} else {
										$sign = $appliedDiscount > 0 ? '-' : '+';

										$discount = $sign . abs( $appliedDiscount );
									}

								} else {
									$discount = DiscountedOrderItem::getPercentageDiscountBetweenPrices( $discountedItem->getOriginalPrice(), $appliedDiscount );

									if ( $discount > 0 ) {
										if ( $appliedDiscount > $discountedItem->getOriginalPrice() ) {
											$discount .= '+';
										} else {
											$discount .= '-';
										}
									}
								}

								if ( $renewalsToNextDiscount < 1 || is_null( $discount ) ) {
									echo '-';
								} else {
									// translators: %1$d: renewals count, %2$d: percentage discount
									echo esc_html( ' ' . sprintf( __( 'In %1$d renewals to %2$d%%', 'discounts-for-woocommerce-subscriptions' ), $renewalsToNextDiscount, $discount ) );
								}

								?>
							</td>
						</tr>
						<?php

					} catch ( Exception $e ) {
						continue;
					}
				}
			}
		} );
	}
}
