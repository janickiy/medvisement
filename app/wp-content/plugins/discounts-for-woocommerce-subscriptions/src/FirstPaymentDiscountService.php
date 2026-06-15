<?php namespace MeowCrew\SubscriptionsDiscounts;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\Frontend\CartManager;
use WC_Product;

class FirstPaymentDiscountService {
	
	use ServiceContainerTrait;
	
	protected $prices = array();
	
	public function __construct() {
		
		add_filter( 'woocommerce_subscriptions_product_price_string_inclusions',
			function ( $include, WC_Product $product ) {
				if ( is_cart() || is_checkout() ) {
					return $include;
				}
				
				if ( array_key_exists( 'price', $include ) ) {
					$price = $this->adjustPrice( $product->get_price(), $product );
					
					if ( $price && $price !== $product->get_price() ) {
						if ( '' !== $product->get_price() ) {
							$price = wc_format_sale_price( wc_get_price_to_display( $product,
									array( 'price' => $product->get_regular_price() ) ),
									wc_get_price_to_display( $product,
										array( 'price' => $price ) ) ) . $product->get_price_suffix();
						}
						
						$include['price'] = $price;
					}
				}
				
				return $include;
			}, 10, 2 );
		
		add_filter( 'woocommerce_before_calculate_totals', function ( \WC_Cart $cart ) {
			
			foreach ( $cart->get_cart_contents() as $cartItem ) {
				
				if ( $cartItem['data'] instanceof WC_Product ) {
					
					$productId = ! empty( $cartItem['variation_id'] ) ? $cartItem['variation_id'] : $cartItem['product_id'];
					
					$pricingRules     = CartManager::getProductDiscounts( $productId );
					$pricingRulesType = CartManager::getProductDiscountsType( $productId );
					
					$newPrice = DiscountsManager::getPriceByRules( 1, $productId, 'no-tax', 'cart', null, $pricingRules,
						$pricingRulesType );
					
					if ( false !== $newPrice ) {
						
						$newPrice = apply_filters( 'subscription_discounts/first_payment_discount/price_in_cart',
							$newPrice, $cartItem, $cart );
						
						$cartItem['data']->set_price( $newPrice );
					}
				}
			}
			
		} );
	}
	
	public function adjustPrice( $regularProductPrice, WC_Product $product ) {
		
		if ( array_key_exists( $product->get_id(), $this->prices ) ) {
			$salePrice = $this->prices[ $product->get_id() ];
		} else {
			$salePrice    = DiscountsManager::getFirstPaymentPrice( $product->get_id() );
			$discountType = DiscountsManager::getDiscountsType( $product->get_id() );
			
			if ( $salePrice && 'percentage' === $discountType ) {
				
				$productPrice = $product->get_regular_price();
				
				if ( $productPrice ) {
					$salePrice = ( $productPrice * ( ( 100 - $salePrice ) / 100 ) );
				}
			}
			
			$this->prices[ $product->get_id() ] = $salePrice;
		}
		
		if ( $salePrice ) {
			return $salePrice;
		}
		
		return $regularProductPrice;
	}
}
