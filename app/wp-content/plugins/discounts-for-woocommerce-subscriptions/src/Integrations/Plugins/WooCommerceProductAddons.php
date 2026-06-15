<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Plugins;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\DiscountsManager;

class WooCommerceProductAddons {

	use ServiceContainerTrait;

	public function __construct() {

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' ) ) {

			add_action( 'wp_head', array( $this, 'addCompatibilityScript' ) );

			add_action( 'subscription_discounts/first_payment_discount/price_in_cart', array( $this, 'addAddonsPrice' ), 10, 2 );
		}
	}

	public function addAddonsPrice( $price, $cart_item ) {

		$extra_cost = 0;

		if ( isset( $cart_item['addons'] ) && false !== $price ) {
			foreach ( $cart_item['addons'] as $addon ) {
				$price_type  = $addon['price_type'];
				$addon_price = $addon['price'];

				switch ( $price_type ) {

					case 'percentage_based':
						$extra_cost += (float) ( $price * ( $addon_price / 100 ) );
						break;
					case 'flat_fee':
						$extra_cost += (float) ( $addon_price / $cart_item['quantity'] );
						break;
					default:
						$extra_cost += (float) $addon_price;
						break;
				}
			}

			return $price + $extra_cost;
		}

		return $price;

	}

	/**
	 * Render compatibility script
	 */
	public function addCompatibilityScript() {

		global $post;

		if ( ! $post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		$firstPaymentPrice = DiscountsManager::getFirstPaymentPrice( $product->get_id() );

		if ( ! $firstPaymentPrice ) {
			return;
		}

		$firstPaymentPrice = DiscountsManager::getPriceByRules( 1, $product->get_id() );

		if ( false === $firstPaymentPrice ) {
			return;
		}

		?>
		<script>
			jQuery(document).ready(function ($) {
				$('#product-addons-total').data('price', <?php echo floatval( $firstPaymentPrice ); ?>);
			});
		</script>
		<?php
	}
}
