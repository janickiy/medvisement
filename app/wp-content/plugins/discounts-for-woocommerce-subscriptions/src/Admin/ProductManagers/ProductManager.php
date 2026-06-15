<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers;

use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use MeowCrew\SubscriptionsDiscounts\SubscriptionsDiscountsPlugin;
use WC_Product;

/**
 * Class ProductManager
 *
 * @package MeowCrew\SubscriptionsDiscounts\Admin\Product
 */
class ProductManager extends ProductManagerAbstract {

	/**
	 * Register hooks
	 */
	protected function hooks() {

		// Subscriptions discounts Product Tab
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'registerSubscriptionDiscountsProductTab' ), 99, 1 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'renderSubscriptionDiscountsTab' ) );

		// Simple Product
		add_action( 'woocommerce_product_options_pricing', array( $this, 'renderPriceRules' ) );

		// Saving
		add_action( 'woocommerce_process_product_meta', array( $this, 'updatePriceRules' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'updateDiscountsType' ) );
	}

	/**
	 * Add discount tab to woocommerce product tabs
	 *
	 * @param array $productTabs
	 *
	 * @return array
	 */
	public function registerSubscriptionDiscountsProductTab( $productTabs ) {
		$supportedVariableTypes = SubscriptionsDiscountsPlugin::getSupportedVariableProductTypes();
		$supportedSimpleTypes   = SubscriptionsDiscountsPlugin::getSupportedSimpleProductTypes();

		$supportedTypes = array_map( function ( $type ) {
			return 'show_if_' . $type;
		}, array_merge( $supportedVariableTypes, $supportedSimpleTypes ) );

		$supportedTypes[] = 'hide_if_external';

		$productTabs['subscription-discounts-tab'] = array(
			'label'  => __( 'Subscription discounts', 'discounts-for-woocommerce-subscriptions' ),
			'target' => 'subscription-discounts-data',
			'class'  => $supportedTypes
		);

		return $productTabs;
	}

	/**
	 * Render content for the subscription discounts tab
	 */
	public function renderSubscriptionDiscountsTab() {

		global $post;

		?>
		<div id="subscription-discounts-data" class="panel woocommerce_options_panel">

			<?php
			do_action( 'subscription_discounts/admin/pricing_tab_begin', $post->ID );

			$supportedVariableTypes = SubscriptionsDiscountsPlugin::getSupportedVariableProductTypes();

			$supportedVariableTypes        = array_map( function ( $type ) {
				return 'show_if_' . str_replace( '-', '_', $type );
			}, $supportedVariableTypes );
			$supportedVariableTypesClasses = implode( ' ', $supportedVariableTypes );

			?>
			<div class="hidden <?php echo esc_attr( $supportedVariableTypesClasses ); ?>">
				<?php

				$type = DiscountsManager::getDiscountsType( $post->ID, 'fixed', 'edit' );

				$this->getContainer()->getFileManager()->includeTemplate( 'admin/add-price-rules.php', array(
					'price_rules_fixed'      => DiscountsManager::getFixedDiscounts( $post->ID, 'edit' ),
					'price_rules_percentage' => DiscountsManager::getPercentageDiscounts( $post->ID, 'edit' ),
					'type'                   => $type,
					'prefix'                 => 'variable',
				) );

				?>
			</div>

			<?php do_action( 'subscription_discounts/admin/pricing_tab_end', $post->ID ); ?>
		</div>
		<?php
	}


	/**
	 * Update price quantity rules for simple product
	 *
	 * @param int $product_id
	 */
	public function updatePriceRules( $product_id ) {

		$nonce = isset( $_POST['_simple_product_dfws_nonce'] ) ? sanitize_key( $_POST['_simple_product_dfws_nonce'] ) : false;

		if ( wp_verify_nonce( $nonce, 'save_simple_product_subscription_discount__data' ) ) {

			$data = $_POST;

			$prefix = isset( $data['product-type'] ) ? sanitize_text_field( $data['product-type'] ) : false;

			if ( ! $prefix ) {
				return;
			}

			if ( in_array( $prefix, SubscriptionsDiscountsPlugin::getSupportedSimpleProductTypes() ) ) {
				$prefix = 'simple';
			} elseif ( in_array( $prefix, SubscriptionsDiscountsPlugin::getSupportedVariableProductTypes() ) ) {
				$prefix = 'variable';
			}

			$fixedAmounts = isset( $data[ 'subscriptions_discounts_fixed_quantity_' . $prefix ] ) ? (array) $data[ 'subscriptions_discounts_fixed_quantity_' . $prefix ] : array();
			$fixedPrices  = ! empty( $data[ 'subscriptions_discounts_fixed_price_' . $prefix ] ) ? (array) $data[ 'subscriptions_discounts_fixed_price_' . $prefix ] : array();

			DiscountsManager::updateFixedDiscounts( $fixedAmounts, $fixedPrices, $product_id );

			$percentageAmounts = isset( $data[ 'subscriptions_discounts_percent_quantity_' . $prefix ] ) ? (array) $data[ 'subscriptions_discounts_percent_quantity_' . $prefix ] : array();
			$percentagePrices  = ! empty( $data[ 'subscriptions_discounts_percent_discount_' . $prefix ] ) ? (array) $data[ 'subscriptions_discounts_percent_discount_' . $prefix ] : array();

			DiscountsManager::updatePercentageDiscounts( $percentageAmounts, $percentagePrices, $product_id );

		}
	}

	/**
	 * Update product pricing type
	 *
	 * @param int $product_id
	 */
	public function updateDiscountsType( $product_id ) {
		$nonce = isset( $_POST['_simple_product_dfws_nonce'] ) ? sanitize_key( $_POST['_simple_product_dfws_nonce'] ) : false;

		if ( wp_verify_nonce( $nonce, 'save_simple_product_subscription_discount__data' ) ) {

			$prefix = isset( $_POST['product-type'] ) ? sanitize_text_field( $_POST['product-type'] ) : false;

			if ( ! $prefix ) {
				return;
			}

			if ( in_array( $prefix, SubscriptionsDiscountsPlugin::getSupportedSimpleProductTypes() ) ) {
				$prefix = 'simple';
			} elseif ( in_array( $prefix, SubscriptionsDiscountsPlugin::getSupportedVariableProductTypes() ) ) {
				$prefix = 'variable';
			}

			if ( isset( $_POST[ 'subscriptions_discounts_rules_type_' . $prefix ] ) ) {
				DiscountsManager::updateDiscountsType( $product_id,
					sanitize_text_field( $_POST[ 'subscriptions_discounts_rules_type_' . $prefix ] ) );
			}
		}
	}

	/**
	 * Render inputs for price rules on a simple product
	 *
	 * @global WC_Product $product_object
	 */
	public function renderPriceRules() {
		global $product_object;

		if ( $product_object instanceof WC_Product ) {

			$supportedSimpleTypes = SubscriptionsDiscountsPlugin::getSupportedSimpleProductTypes();

			$supportedSimpleTypes = array_map( function ( $type ) {
				return 'show_if_' . str_replace( '-', '_', $type );
			}, $supportedSimpleTypes );

			$supportedSimpleTypesClasses = implode( ' ', $supportedSimpleTypes );

			?>
			<div class="hidden <?php echo esc_attr( $supportedSimpleTypesClasses ); ?>">
				<?php

				$type = DiscountsManager::getDiscountsType( $product_object->get_id(), 'fixed', 'edit' );

				$this->getContainer()->getFileManager()->includeTemplate( 'admin/add-price-rules.php', array(
					'price_rules_fixed'      => DiscountsManager::getFixedDiscounts( $product_object->get_id(), 'edit' ),
					'price_rules_percentage' => DiscountsManager::getPercentageDiscounts( $product_object->get_id(), 'edit' ),
					'type'                   => $type,
					'prefix'                 => 'simple',
				) );
				?>
			</div>
			<?php

		}
	}
}
