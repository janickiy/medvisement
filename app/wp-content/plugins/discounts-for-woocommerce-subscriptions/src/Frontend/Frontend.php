<?php namespace MeowCrew\SubscriptionsDiscounts\Frontend;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\SubscriptionsDiscountsPlugin;
use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use WP_Post;

/**
 * Class Frontend
 *
 * @package MeowCrew\SubscriptionsDiscounts\Frontend
 */
class Frontend {

	use ServiceContainerTrait;

	/**
	 * Frontend constructor.
	 */
	public function __construct() {

		new CartManager();
		new MyAccount();

		// Render price table
		add_action( $this->getContainer()->getSettings()->get( 'position_hook', 'woocommerce_before_add_to_cart_button' ), array(
			$this,
			'displayDiscountsTable'
		), - 999 );

		// Get table for variation
		add_action( 'wc_ajax_get_discounts_table', array( $this, 'getDiscountsTableVariation' ), 10, 1 );

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueAssets' ), 10, 1 );

		// Шорткод
        add_shortcode( 'subscriptions_discount_pricingtable', [ $this, 'renderPricingTableShortcode' ], 10, 1 );
	}

	/**
	 *  Display table at frontend
	 */
	public function displayDiscountsTable() {

		global $post;

		if ( ! $post ) {
			return;
		}

		$product = wc_get_product( $post->ID );

		if ( $product ) {
			if ( in_array( $product->get_type(), SubscriptionsDiscountsPlugin::getSupportedSimpleProductTypes() ) ) {
				$this->renderPricingTable( $product->get_id() );
			} elseif ( in_array( $product->get_type(), SubscriptionsDiscountsPlugin::getSupportedVariableProductTypes() ) ) {

				$variation_id         = 0;
				$is_default_variation = false;
				foreach ( $product->get_available_variations() as $variation_values ) {
					foreach ( $variation_values['attributes'] as $key => $attribute_value ) {
						$attribute_name = str_replace( 'attribute_', '', $key );
						$default_value  = $product->get_variation_default_attribute( $attribute_name );
						if ( $default_value == $attribute_value ) {
							$is_default_variation = true;
						} else {
							$is_default_variation = false;
							break;
						}
					}
					if ( $is_default_variation ) {
						$variation_id = $variation_values['variation_id'];
						break;
					}
				}

				// Now we get the default variation data
				if ( $is_default_variation ) {
					?>
					<div data-variation-discounts-for-subscriptions-table>
						<?php $this->renderPricingTable( $product->get_id(), $variation_id ); ?>
					</div>
					<?php
				} else {
					echo '<div data-variation-discounts-for-subscriptions-table></div>';
				}

			}
		}
	}

	/**
	 * Enqueue assets at simple product and variation product page.
	 *
	 * @global WP_Post $post .
	 */
	public function enqueueAssets() {
		global $post;

		if ( is_product() ) {
			$product = wc_get_product( $post->ID );

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-tooltip' );

			wp_enqueue_script( 'discounts-for-woocommerce-subscriptions-front-js',
				$this->getContainer()->getFileManager()->locateAsset( 'frontend/discounts-for-woocommerce-subscriptions.js' ),
				array( 'jquery' ), SubscriptionsDiscountsPlugin::VERSION );

			wp_enqueue_style( 'discounts-for-woocommerce-subscriptions-front-css',
				$this->getContainer()->getFileManager()->locateAsset( 'frontend/main.css' ), null, SubscriptionsDiscountsPlugin::VERSION );

			wp_localize_script( 'discounts-for-woocommerce-subscriptions-front-js', 'subscriptionDiscountsData',
				array(
					'product_type'     => $product->get_type(),
					'load_table_nonce' => wp_create_nonce( 'get_discounts_table' ),
					'settings'         => $this->getContainer()->getSettings()->getAll(),
					'is_premium'       => 'yes',
					'currency_options' => array(
						'currency_symbol'    => get_woocommerce_currency_symbol(),
						'decimal_separator'  => wc_get_price_decimal_separator(),
						'thousand_separator' => wc_get_price_thousand_separator(),
						'decimals'           => wc_get_price_decimals(),
						'price_format'       => get_woocommerce_price_format(),
						'price_suffix'       => $product->get_price_suffix(),
					)
				) );
		}

	}

	/**
	 * Fired when user choose some variation. Render price rules table for it if it exists
	 *
	 * @global WP_Post $post .
	 */
	public function getDiscountsTableVariation() {

		$product_id = isset( $_POST['variation_id'] ) ? sanitize_text_field( $_POST['variation_id'] ) : false;
		$nonce      = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : false;

		if ( wp_verify_nonce( $nonce, 'get_discounts_table' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$this->renderPricingTable( $product->get_parent_id(), $product->get_id() );
			}

		}
	}

	/**
	 * Main function for rendering pricing table for product
	 *
	 * @param int $product_id
	 * @param int $variation_id
	 */
	public function renderPricingTable( $product_id, $variation_id = null ) {

		$product_id = ! is_null( $variation_id ) ? $variation_id : $product_id;

		$product = wc_get_product( $product_id );

		$supportedTypes = array_merge( SubscriptionsDiscountsPlugin::getSupportedSimpleProductTypes(),
			SubscriptionsDiscountsPlugin::getSupportedVariableProductTypes() );
		// Exit if product is not valid
		if ( ! $product || ! in_array( $product->get_type(), $supportedTypes ) ) {
			return;
		}

		$rules = DiscountsManager::getDiscounts( $product_id );

		if ( ! empty( $rules ) ) {

			$template = 'percentage' === DiscountsManager::getDiscountsType( $product_id ) ? 'percentage-discounts-table.php' : 'fixed-discounts-table.php';

			?>
			<div class="data-discounts-table-container">
				<?php
				$this->getContainer()->getFileManager()->includeTemplate( 'frontend/' . $template, array(
					'price_rules'  => $rules,
					'real_price'   => $product->get_price(),
					'product_name' => $product->get_name(),
					'product_id'   => $product_id,
					'product'      => $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product,
					'settings'     => $this->getContainer()->getSettings()->getAll()
				) );
				?>
			</div>
			<?php

			do_action( 'subscription_discounts/frontend/after_discounts_table_rendered', $product, $rules, $template );

		}
	}
	
	public function renderPricingTableShortcode( $atts ) {
	    ob_start();
	    $this->renderPricingTable( $atts['product_id'], $atts['variation_id'] );
	    return ob_get_clean();
	}

}
