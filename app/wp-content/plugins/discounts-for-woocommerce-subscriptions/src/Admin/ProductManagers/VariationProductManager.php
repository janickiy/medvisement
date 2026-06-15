<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers;

use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use WP_Post;

/**
 * Class VariationProduct
 *
 * @package MeowCrew\SubscriptionsDiscounts\Admin\Product
 */
class VariationProductManager extends ProductManagerAbstract {

	/**
	 * Register hooks
	 */
	protected function hooks() {
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'renderPriceRules' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'updatePriceRules' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'updateDiscountsTypes' ), 10, 3 );
	}

	/**
	 * Update price quantity rules for variation product
	 *
	 * @param int $variation_id
	 * @param int $loop
	 */
	public function updatePriceRules( $variation_id, $loop ) {

		check_ajax_referer( 'save-variations', 'security' );

		$data = $_POST;

		if ( isset( $data['subscriptions_discounts_fixed_quantity'][ $loop ] ) ) {
			$fixedAmounts = $data['subscriptions_discounts_fixed_quantity'][ $loop ];
			$fixedPrices  = ! empty( $data['subscriptions_discounts_fixed_price'][ $loop ] ) ? (array) $data['subscriptions_discounts_fixed_price'][ $loop ] : array();

			DiscountsManager::updateFixedDiscounts( $fixedAmounts, $fixedPrices, $variation_id );
		}

		if ( isset( $data['subscriptions_discounts_percent_quantity'][ $loop ] ) ) {
			$amounts = $data['subscriptions_discounts_percent_quantity'][ $loop ];
			$prices  = ! empty( $data['subscriptions_discounts_percent_discount'][ $loop ] ) ? (array) $data['subscriptions_discounts_percent_discount'][ $loop ] : array();

			DiscountsManager::updatePercentageDiscounts( $amounts, $prices, $variation_id );
		}

	}

	/**
	 * Update product pricing type
	 *
	 * @param int $variation_id
	 * @param int $loop
	 */
	public function updateDiscountsTypes( $variation_id, $loop ) {
		check_ajax_referer( 'save-variations', 'security' );

		if ( ! empty( $_POST['subscriptions_discounts_rules_type'][ $loop ] ) ) {
			DiscountsManager::updateDiscountsType( $variation_id,
				sanitize_text_field( $_POST['subscriptions_discounts_rules_type'][ $loop ] ) );
		}
	}

	/**
	 * Render inputs for price rules on variation
	 *
	 * @param int $loop
	 * @param array $variation_data
	 * @param WP_Post $variation
	 */
	public function renderPriceRules( $loop, $variation_data, $variation ) {

		$this->getContainer()->getFileManager()->includeTemplate( 'admin/add-price-rules-variation.php', array(
			'price_rules_fixed'      => DiscountsManager::getFixedDiscounts( $variation->ID, 'edit' ),
			'price_rules_percentage' => DiscountsManager::getPercentageDiscounts( $variation->ID, 'edit' ),
			'type'                   => DiscountsManager::getDiscountsType( $variation->ID, 'fixed', 'edit' ),
			'i'                      => $loop,
			'variation_data'         => $variation_data,
		) );
	}
}
