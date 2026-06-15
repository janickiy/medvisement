<?php namespace MeowCrew\SubscriptionsDiscounts\Entity;

use Exception;
use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use MeowCrew\SubscriptionsDiscounts\Frontend\CartManager;
use MeowCrew\SubscriptionsDiscounts\SanitizeManager;
use WC_Order_Item_Product;
use WC_Subscription;

class DiscountedOrderItem {
	
	/**
	 * Order Item
	 *
	 * @var WC_Order_Item_Product
	 */
	private $item;
	
	/**
	 * Subscription
	 *
	 * @var WC_Subscription
	 */
	private $subscription;
	
	/**
	 * DiscountedOrderItem constructor.
	 *
	 * @param  WC_Order_Item_Product  $item
	 * @param  WC_Subscription|null  $subscription
	 *
	 * @throws Exception
	 */
	public function __construct( WC_Order_Item_Product $item, WC_Subscription $subscription = null ) {
		$this->item = $item;
		
		$subscription = $subscription ? $subscription : false;
		
		if ( ! $subscription ) {
			$order = $this->getItem()->get_order();
			
			if ( $order ) {
				$subscription = wcs_get_subscription( $order->get_id() );
			}
		}
		
		if ( $subscription ) {
			$this->subscription = $subscription;
		} else {
			throw new Exception( 'Invalid order item' );
		}
	}
	
	/**
	 * Get subscription from item
	 *
	 * @return WC_Subscription
	 */
	public function getSubscription() {
		return $this->subscription;
	}
	
	/**
	 * Get order item from DiscountedItem
	 *
	 * @return WC_Order_Item_Product
	 */
	public function getItem() {
		return $this->item;
	}
	
	public function getDiscounts( $removeFirstPaymentDiscount = true ) {
		$discounts = SanitizeManager::sanitizeDiscountsRules( (array) $this->getItem()->get_meta( '_dfws_discounts' ) );
		
		if ( $removeFirstPaymentDiscount && array_key_exists( 1, $discounts ) ) {
			unset( $discounts[1] );
		}
		
		return $discounts;
	}
	
	public function hasFirstPaymentPrice() {
		return array_key_exists( 1, $this->getDiscounts( false ) );
	}
	
	public function getFirstPaymentPrice() {
		$discounts = $this->getDiscounts( false );
		
		if ( ! array_key_exists( 1, $discounts ) ) {
			return false;
		}
		
		return $discounts[1];
	}
	
	public function getDiscountsType() {
		return (string) $this->getItem()->get_meta( '_dfws_type' );
	}
	
	public function getAppliedDiscount() {
		$appliedDiscount = (int) $this->getItem()->get_meta( '_dfws_applied_discount' );
		
		if ( $appliedDiscount < 1 ) {
			return false;
		}
		
		return $appliedDiscount;
	}
	
	public function updateAppliedDiscount( $key ) {
		if ( array_key_exists( $key, $this->getDiscounts() ) ) {
			$this->getItem()->update_meta_data( '_dfws_applied_discount', $key );
			$this->getItem()->save();
		}
	}
	
	public function isDiscountedItem() {
		return ! empty( $this->getDiscounts() );
	}
	
	public function getTotalRenewals() {
		$total        = $this->getSubscription()->get_payment_count( 'completed', 'any' );
		$totalRefunds = $this->getSubscription()->get_payment_count( 'refunded', 'any' );
		
		$total -= $totalRefunds;
		
		// Do not take into account trial order as renewal
		if ( $this->getSubscription()->get_trial_period() ) {
			$total --;
		}
		
		return $total;
	}
	
	public function calculateAppliedDiscount( $totalRenewals = null ) {
		$totalRenewals   = $totalRenewals ? $totalRenewals : $this->getTotalRenewals();
		$appliedDiscount = false;
		
		foreach ( $this->getDiscounts() as $number => $discount ) {
			if ( $totalRenewals >= $number ) {
				$appliedDiscount = $number;
			}
		}
		
		return $appliedDiscount;
	}
	
	public function applyNewDiscount( $newDiscount ) {
		
		$discounts   = $this->getDiscounts( false );
		$oldDiscount = $this->getAppliedDiscount();
		
		if ( array_key_exists( $newDiscount, $discounts ) && $oldDiscount !== $newDiscount ) {
			
			$newDiscountAmount = $discounts[ $newDiscount ];
			
			if ( $this->getDiscountsType() === 'percentage' ) {
				$originalPrice = $this->getOriginalPrice();
				$newPrice      = $originalPrice - ( ( $originalPrice / 100 ) * $newDiscountAmount );
			} else {
				$newPrice = $newDiscountAmount;
			}
			
			$newPrice = wc_get_price_excluding_tax( $this->getItem()->get_product(), array(
				'price' => (float) $newPrice,
			) );
			
			$this->updateAppliedDiscount( $newDiscount );
			
			$this->getItem()->set_total( $newPrice * $this->getItem()->get_quantity() );
			$this->getItem()->set_subtotal( $newPrice * $this->getItem()->get_quantity() );
			$this->getItem()->calculate_taxes();
			
			if ( $this->getSubscription() instanceof WC_Subscription ) {
				$this->getSubscription()->calculate_totals();
			}
			
			return true;
		}
		
		return false;
	}
	
	public function getOriginalPrice( $toDisplay = false ) {
		
		if ( $this->getItem()->meta_exists( '_dfws_original_product_price' ) ) {
			$price = (float) $this->getItem()->get_meta( '_dfws_original_product_price', true );
		} else {
			$price = $this->getItem()->get_product() ? (float) $this->getItem()->get_product()->get_price() : 0;
		}
		
		return $toDisplay ? wc_get_price_to_display( $this->getItem()->get_product(), array(
			'price' => $price,
		) ) : $price;
	}
	
	public static function getPercentageDiscountBetweenPrices( $price, $salePrice ) {
		
		if ( $price <= 0 && $salePrice <= 0 ) {
			return null;
		}
		
		if ( $salePrice > $price ) {
			return ceil( 100 * $salePrice / $price );
		}
		
		return ceil( 100 - ( $salePrice * 100 / $price ) );
	}
	
	public static function formatRenewalNumber( $number ) {
		
		if ( $number < 1 ) {
			return 0;
		}
		
		switch ( $number ) {
			case 1:
				$renewalNumber = $number . 'st';
				break;
			case 2:
				$renewalNumber = $number . 'nd';
				break;
			case 3:
				$renewalNumber = $number . 'rd';
				break;
			default:
				$renewalNumber = $number . 'th';
		}
		
		return $renewalNumber;
	}
	
	public static function addDiscounts( WC_Order_Item_Product $item, $discounts = array(), $discountsType = null ) {
		
		$productId = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		$discounts = $discounts ? $discounts : CartManager::getProductDiscounts( $productId );
		
		if ( ! empty( $discounts ) ) {
			
			$discountsType = $discountsType ? $discountsType : CartManager::getProductDiscountsType( $productId );
			
			try {
				
				$item->add_meta_data( '_dfws_discounts', $discounts, true );
				$item->add_meta_data( '_dfws_type', $discountsType, true );
				$item->add_meta_data( '_dfws_applied_discount', 0, true );
				
				$item->add_meta_data( '_dfws_original_product_price', $item->get_product()->get_price() );
				
			} catch ( Exception $e ) {
				return;
			}
			
			$item->save();
		}
	}
	
	public static function removeDiscounts( WC_Order_Item_Product $item ) {
		
		try {
			$item->delete_meta_data( '_dfws_discounts' );
			$item->delete_meta_data( '_dfws_type' );
			$item->delete_meta_data( '_dfws_applied_discount' );
			
			$item->save();
		} catch ( Exception $e ) {
			return;
		}
		
	}
}
