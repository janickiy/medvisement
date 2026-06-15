<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\Import;

use MeowCrew\SubscriptionsDiscounts\SanitizeManager;
use WC_Product;

class Woocommerce {

	/**
	 * Import constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'addColumnsToImporter' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns',
			array( $this, 'addColumnToMappingScreen' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'processImport' ), 10, 2 );
	}

	/**
	 * Register the 'Discounts for WooCommerce Subscriptions' column in the importer.
	 *
	 * @param array $options
	 *
	 * @return array $options
	 */
	public function addColumnsToImporter( $options ) {

		$options['subscriptions_discounts_type']       = __( 'Discounts pricing type', 'discounts-for-woocommerce-subscriptions' );
		$options['subscriptions_discounts_fixed']      = __( 'Fixed discounts', 'discounts-for-woocommerce-subscriptions' );
		$options['subscriptions_discounts_percentage'] = __( 'Percentage discounts', 'discounts-for-woocommerce-subscriptions' );

		return $options;
	}


	/**
	 * Add automatic mapping support for 'Discount for WooCommerce Subscriptions'.
	 *
	 * @param array $columns
	 *
	 * @return array $columns
	 */
	public function addColumnToMappingScreen( $columns ) {

		$columns[ __( 'Fixed discounts', 'discounts-for-woocommerce-subscriptions' ) ]        = 'subscriptions_discounts_fixed';
		$columns[ __( 'Percentage discounts', 'discounts-for-woocommerce-subscriptions' ) ]   = 'subscriptions_discounts_percentage';
		$columns[ __( 'Discounts pricing type', 'discounts-for-woocommerce-subscriptions' ) ] = 'subscriptions_discounts_type';

		return $columns;
	}

	/**
	 * Process the data read from the CSV file.
	 *
	 * @param WC_Product $product - Product being imported or updated.
	 * @param array $data - CSV data read for the product.
	 *
	 * @return WC_Product $object
	 */
	public function processImport( $product, $data ) {

		if ( ! empty( $data['subscriptions_discounts_fixed'] ) ) {

			$fixed = $this->decodeExport( $data['subscriptions_discounts_fixed'] );

			if ( $data && ! empty( $data ) ) {
				$product->update_meta_data( '_fixed_subscription_discounts', $fixed );
			}
		} else {
			$product->update_meta_data( '_fixed_subscription_discounts', array() );
		}

		if ( ! empty( $data['subscriptions_discounts_percentage'] ) ) {

			$percentage = $this->decodeExport( $data['subscriptions_discounts_percentage'] );

			if ( $data && ! empty( $data ) ) {
				$product->update_meta_data( '_percentage_subscription_discounts', $percentage );
			}
		} else {
			$product->update_meta_data( '_percentage_subscription_discounts', array() );
		}

		if ( ! empty( $data['subscriptions_discounts_type'] ) ) {

			if ( in_array( $data['subscriptions_discounts_type'], array( 'fixed', 'percentage' ) ) ) {
				$product->update_meta_data( '_subscriptions_discounts_type', $data['subscriptions_discounts_type'] );
			}
		}

		return $product;
	}

	/**
	 * Decode export file format to array
	 *
	 * @param string $data
	 *
	 * @return array
	 */
	protected function decodeExport( $data ) {
		$rules = explode( ',', $data );
		$data  = array();

		if ( $rules ) {
			foreach ( $rules as $rule ) {
				$rule = explode( ':', $rule );

				if ( isset( $rule[0] ) && isset( $rule[1] ) ) {
					$data[ intval( $rule[0] ) ] = $rule[1];
				}
			}

		}

		$data = SanitizeManager::sanitizeDiscountsRules( $data );

		return ! empty( $data ) ? $data : array();
	}
}
