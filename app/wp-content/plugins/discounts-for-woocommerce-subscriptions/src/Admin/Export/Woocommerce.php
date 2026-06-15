<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\Export;

use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use WC_Product;

/**
 * Class WooCommerce Export
 */
class Woocommerce {

	/**
	 * Export constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'addExportColumn' ), 1, 10 );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'addExportColumn' ), 1, 10 );

		add_filter( 'woocommerce_product_export_product_column_subscriptions_discounts_fixed',
			array( $this, 'addExportFixedData' ), 10,
			2 );

		add_filter( 'woocommerce_product_export_product_column_subscriptions_discounts_percentage',
			array( $this, 'addExportPercentageData' ), 10, 2 );

		add_filter( 'woocommerce_product_export_product_column_subscriptions_discounts_type',
			array( $this, 'addExportPricingTypeData' ), 10, 2 );
	}

	/**
	 * Register the 'Fixed price' column in the exporter.
	 *
	 * @param array $columns
	 *
	 * @return array $options
	 */
	public function addExportColumn( $columns ) {
		$columns['subscriptions_discounts_type']       = __( 'Discounts type', 'discounts-for-woocommerce-subscriptions' );
		$columns['subscriptions_discounts_fixed']      = __( 'Fixed subscription discounts', 'discounts-for-woocommerce-subscriptions' );
		$columns['subscriptions_discounts_percentage'] = __( 'Percentage subscription discounts', 'discounts-for-woocommerce-subscriptions' );

		return $columns;
	}

	/**
	 * Provide the data to be exported for one item in the column.
	 *
	 * @param WC_Product $product
	 * @param string $type
	 *
	 * @return mixed $value
	 */
	public function addExportData( $product, $type = 'fixed' ) {

		if ( 'percentage' == $type ) {
			$subscriptions_discounts_ = DiscountsManager::getPercentageDiscounts( $product->get_id(), 'edit' );
		} else {
			$subscriptions_discounts_ = DiscountsManager::getFixedDiscounts( $product->get_id(), 'edit' );
		}

		$str = '';

		foreach ( $subscriptions_discounts_ as $quantity => $price ) {
			$str .= $quantity . ':' . $price . ',';
		}

		return mb_strlen( $str ) > 0 ? trim( $str, ',' ) : null;
	}

	/**
	 * Export fixed pricing rules
	 *
	 * @param mixed $value
	 * @param WC_product $product
	 *
	 * @return mixed
	 */
	public function addExportFixedData( $value, $product ) {
		return $this->addExportData( $product, 'fixed' );
	}

	/**
	 * Export percentage pricing rules
	 *
	 * @param mixed $value
	 * @param WC_product $product
	 *
	 * @return mixed
	 */
	public function addExportPercentageData( $value, $product ) {
		return $this->addExportData( $product, 'percentage' );
	}

	/**
	 * Export discount type
	 *
	 * @param mixed $value
	 * @param WC_product $product
	 *
	 * @return mixed
	 */
	public function addExportPricingTypeData( $value, $product ) {
		$type = DiscountsManager::getDiscountsType( $product->get_id(), false, 'edit' );

		return $type ? $type : '';
	}

}
