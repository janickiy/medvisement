<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\Subscription;

use Exception;
use MeowCrew\SubscriptionsDiscounts\Core\FileManager;
use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;
use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use MeowCrew\SubscriptionsDiscounts\Managers\RenewalManager;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Subscription;

class SubscriptionPage {

	use ServiceContainerTrait;

	/**
	 * SubscriptionPage constructor.
	 *
	 */
	public function __construct() {

		add_filter( 'woocommerce_hidden_order_itemmeta', function ( $hidden ) {
			$hidden[] = '_dfws_discounts';
			$hidden[] = '_dfws_applied_discount';
			$hidden[] = '_dfws_type';
			$hidden[] = '_dfws_original_product_price';

			return $hidden;
		} );

		add_action( 'woocommerce_before_order_itemmeta', function ( $item_id, WC_Order_Item $item, $product ) {

			if ( $product && $item instanceof WC_Order_Item_Product ) {

				try {
					$discountedOrderItem = new DiscountedOrderItem( $item );

					if ( $discountedOrderItem->isDiscountedItem() ) {
						$this->getContainer()->getFileManager()->includeTemplate( 'admin/order-discounts-sequence.php',
							array(
								'discounted_order_item' => new DiscountedOrderItem( $item ),
							) );
					}

				} catch ( Exception $e ) {
					return false;
				}

			}
		}, 10, 3 );

		add_filter( 'manage_edit-shop_subscription_columns', function ( $columns ) {

			$column_header = '<span class="tips" data-tip="' . esc_attr__( 'Current discount rate that will be applied with next renewal.',
					'discounts-for-woocommerce-subscriptions' ) . '">' . esc_attr__( 'Renewal Discount',
					'discounts-for-woocommerce-subscriptions' ) . '</span>';

			return wcs_array_insert_after( 'recurring_total', $columns, 'dfws_renewal_discount', $column_header );

		}, 999 );

		add_filter( 'manage_shop_subscription_posts_custom_column', function ( $column ) {
			global $post;

			if ( 'dfws_renewal_discount' === $column ) {
				$subscription = wcs_get_subscription( $post->ID );

				$discounts = array();

				foreach ( $subscription->get_items() as $item ) {
					if ( $item instanceof WC_Order_Item_Product ) {

						// If product does not exist anymore
						if ( ! $item->get_product() ) {
							continue;
						}

						try {
							$discountedItem = new DiscountedOrderItem( $item, $subscription );

							if ( $discountedItem->isDiscountedItem() && $discountedItem->getAppliedDiscount() && array_key_exists( $discountedItem->getAppliedDiscount(),
									$discountedItem->getDiscounts() ) ) {
								$discount = $discountedItem->getDiscounts()[ $discountedItem->getAppliedDiscount() ];

								if ( $discountedItem->getDiscountsType() === 'percentage' ) {
									$discounts[] = $discount . '%';
								} else {
									$originalPrice        = $discountedItem->getOriginalPrice();
									$appliedDiscountValue = DiscountedOrderItem::getPercentageDiscountBetweenPrices( $discountedItem->getOriginalPrice(),
										$discount );

									if ( $originalPrice < $discount ) {
										$appliedDiscountValue .= '+';
									}

									$discounts[] = $appliedDiscountValue;
								}
							} else {
								$discounts[] = '—';
							}

						} catch ( Exception $exception ) {
							continue;
						}
					}
				}

				echo esc_html( implode( ' / ', $discounts ) );
			}

			return $column;
		}, 999 );

		add_filter( 'woocommerce_order_actions', function ( $actions ) {
			global $theorder;

			if ( wcs_is_subscription( $theorder ) ) {
				if ( $this->isDiscountCanBeApplied( $theorder ) ) {
					$actions['dfws_apply_subscription_discounts'] = __( 'Apply discounts',
						'discounts-for-woocommerce-subscriptions' );
				}
			}

			if ( wcs_is_subscription( $theorder ) ) {
				if ( $this->subscriptionHasDiscounts( $theorder ) ) {
					$actions['dfws_remove_subscription_discounts'] = __( 'Remove discounts',
						'discounts-for-woocommerce-subscriptions' );
				}
			}

			return $actions;

		} );

		add_action( 'woocommerce_order_action_dfws_apply_subscription_discounts', array(
			$this,
			'applySubscriptionDiscountsAction',
		), 10, 1 );

		add_action( 'woocommerce_order_action_dfws_remove_subscription_discounts', array(
			$this,
			'removeSubscriptionDiscountsAction',
		), 10, 1 );

		add_filter( 'woocommerce_subscription_bulk_actions', function ( $actions ) {
			$actions['dfws_apply_subscription_discounts']  = __( 'Apply discounts',
				'discounts-for-woocommerce-subscriptions' );
			$actions['dfws_remove_subscription_discounts'] = __( 'Remove discounts',
				'discounts-for-woocommerce-subscriptions' );

			return $actions;
		} );

		add_action( 'load-edit.php', function () {
			// We only want to deal with shop_subscription bulk actions.
			if ( ! isset( $_REQUEST['post_type'] ) || 'shop_subscription' !== $_REQUEST['post_type'] || ! isset( $_REQUEST['post'] ) ) {
				return;
			}

			$action = '';

			if ( isset( $_REQUEST['action'] ) && - 1 != $_REQUEST['action'] ) {
				$action = sanitize_text_field( $_REQUEST['action'] );
			} elseif ( isset( $_REQUEST['action2'] ) && - 1 != $_REQUEST['action2'] ) {
				$action = sanitize_text_field( $_REQUEST['action2'] );
			}

			if ( ! in_array( $action, array(
				'dfws_apply_subscription_discounts',
				'dfws_remove_subscription_discounts',
			) ) ) {
				return;
			}

			$subscription_ids = array_map( 'absint', (array) $_REQUEST['post'] );
			$changed          = 0;

			foreach ( $subscription_ids as $subscription_id ) {
				$subscription = wcs_get_subscription( $subscription_id );

				if ( is_a( $subscription, '\WC_Subscription' ) ) {

					if ( 'dfws_apply_subscription_discounts' === $action ) {
						if ( $this->isDiscountCanBeApplied( $subscription ) ) {
							$this->applySubscriptionDiscountsAction( $subscription );
							$changed ++;
						}
					} elseif ( 'dfws_remove_subscription_discounts' === $action ) {
						if ( $this->subscriptionHasDiscounts( $subscription ) ) {
							$this->removeSubscriptionDiscountsAction( $subscription );
							$changed ++;
						}
					}
				}
			}

			if ( $changed > 0 ) {
				// translators: %s: number of subscriptions, %2s: subscription/subscriptions
				$this->getContainer()->getAdminNotifier()->flash( sprintf( __( '%1$s %2$s have been applied discounts',
					'discounts-for-woocommerce-subscriptions' ), $changed,
					_n( 'subscription', 'subscriptions', $changed, 'discounts-for-woocommerce-subscriptions' ) ) );
			}

			wp_safe_redirect( wp_get_referer() );
			exit();
		} );

		new ApplyDiscountsAction( $this );
	}

	public function isDiscountCanBeApplied( WC_Subscription $subscription ) {
		foreach ( $subscription->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {


				$productId = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

				$itemDiscounts    = $item->get_meta( '_dfws_discounts', true );
				$productDiscounts = DiscountsManager::getDiscounts( $productId );

				if ( ! empty( $productDiscounts ) && empty( $itemDiscounts ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public function subscriptionHasDiscounts( WC_Subscription $subscription ) {
		foreach ( $subscription->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {

				// If product does not exist anymore
				if ( ! $item->get_product() ) {
					continue;
				}

				try {
					$discountedOrderItem = new DiscountedOrderItem( $item );

					if ( $discountedOrderItem->isDiscountedItem() ) {
						return true;
					}

				} catch ( Exception $e ) {
					continue;

					// todo: log
				}
			}
		}

		return false;
	}

	public function applySubscriptionDiscountsAction( WC_Subscription $subscription ) {
		if ( $this->isDiscountCanBeApplied( $subscription ) ) {

			foreach ( $subscription->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product ) {

					// If product does not exist anymore
					if ( ! $item->get_product() ) {
						continue;
					}

					add_filter( 'subscription_discounts/role_based_rules/current_user_roles',
						function ( $roles, $productId ) use ( $item ) {
							if ( $item->get_product_id() === $productId ) {
								$userId = $item->get_order()->get_customer_id();

								if ( $userId ) {
									$user = new \WP_User( $userId );

									if ( $user ) {
										return ( array ) $user->roles;
									}
								}
							}

							return $roles;
						}, 10, 2 );

					DiscountedOrderItem::addDiscounts( $item );
				}
			}

			RenewalManager::applyDiscounts( $subscription, false );
		}
	}

	public function removeSubscriptionDiscountsAction( WC_Subscription $subscription ) {
		if ( $this->subscriptionHasDiscounts( $subscription ) ) {

			foreach ( $subscription->get_items() as $item ) {
				if ( $item instanceof WC_Order_Item_Product ) {

					// If product does not exist anymore
					if ( ! $item->get_product() ) {
						continue;
					}

					try {
						$discountedItem = new DiscountedOrderItem( $item );

						if ( $discountedItem->isDiscountedItem() ) {
							DiscountedOrderItem::removeDiscounts( $item );

							$newPrice = wc_get_price_excluding_tax( $discountedItem->getItem()->get_product(), array(
								'price'    => $discountedItem->getOriginalPrice(),
								'qty' => $discountedItem->getItem()->get_quantity(),
							) );

							$discountedItem->getItem()->set_subtotal( $newPrice );
							$discountedItem->getItem()->set_total( $newPrice );
							$discountedItem->getItem()->calculate_taxes();
							$discountedItem->getItem()->save();

							$discountedItem->getSubscription()->calculate_taxes();
							$discountedItem->getSubscription()->calculate_totals();
							$discountedItem->getSubscription()->save();
						}
					} catch ( Exception $e ) {
						// todo: log

						continue;
					}
				}
			}
		}
	}
}
