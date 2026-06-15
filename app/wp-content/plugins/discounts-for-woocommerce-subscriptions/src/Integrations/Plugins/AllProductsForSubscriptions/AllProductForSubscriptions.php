<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Plugins\AllProductsForSubscriptions;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use WC_Product;
use WCS_ATT_Product_Schemes;

class AllProductForSubscriptions {

	const SETTING_ENABLE_KEY = 'enable_all_products_for_subscriptions_addon';

	use ServiceContainerTrait;

	public function __construct() {

		if ( $this->isActive() ) {
			$this->run();
		}
	}

	public function getName() {
		return 'All Products For Subscriptions';
	}

	/**
	 * Whether addon is active or not
	 *
	 * @return bool
	 */
	public function isActive() {
		return $this->getContainer()->getSettings()->get( self::SETTING_ENABLE_KEY, 'yes' ) === 'yes';
	}

	public function run() {
		add_action( 'subscription_discounts/frontend/after_discounts_table_rendered', array(
			$this,
			'renderPlansTables'
		), 10, 10 );

		add_filter( 'subscription_discounts/frontend/supported_variable_product_types', function ( $types ) {
			$types[] = 'variable';
			$types[] = 'variation';

			return $types;
		} );

		add_filter( 'subscription_discounts/frontend/supported_simple_product_types', function ( $types ) {
			$types[] = 'simple';
			$types[] = 'bundle';

			return $types;
		} );
	}

	public function renderPlansTables( WC_Product $product, $rules, $template ) {

		if ( $product && class_exists( '\WCS_ATT_Product_Schemes' ) ) {
			$subscriptionSchemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
			$real_price          = $product->get_price();

			if ( ! empty( $subscriptionSchemes ) && is_array( $subscriptionSchemes ) ) {

				foreach ( $subscriptionSchemes as $key => $subscriptionPlan ) {

					$data = $subscriptionPlan->get_data();

					if ( ! empty( $data['price'] ) ) {
						$_real_price = floatval( $data['price'] );
					} else if ( ! empty( $data['discount'] ) ) {
						$discount = floatval( $data['discount'] );

						$discountValue = ( $real_price / 100 ) * $discount;
						$_real_price   = $real_price - $discountValue;
					} else {
						$_real_price = $real_price;
					}
					?>
					<div style="display: none"
						 class="data-discounts-table-container data-discounts-table-container--apfs"
						 data-all-products-for-subscription="<?php echo esc_attr( $key ); ?>">
						<?php
						$this->getContainer()->getFileManager()->includeTemplate( 'frontend/' . $template, array(
							'price_rules'  => $rules,
							'real_price'   => $_real_price,
							'product_name' => $product->get_name(),
							'product_id'   => $product->get_id(),
							'product'      => $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product,
							'settings'     => $this->getContainer()->getSettings()->getAll(),
						) );
						?>
					</div>
					<script>
						jQuery(document).ready(function ($) {

							function showDiscountsTable() {
								let selectedSubscription = $('[name^=convert_to_sub_]').first().val();

								if ($('[name=subscribe-to-action-input]:checked').val() === 'yes') {
									$('[data-all-products-for-subscription=' + selectedSubscription + ']').show();
								}
							}

							$('[name^=convert_to_sub_]').on('change', function () {

								$('.data-discounts-table-container').hide();

								showDiscountsTable();
							});

							$('[name=subscribe-to-action-input]').on('change', function () {
								if ($('[name=subscribe-to-action-input]:checked').val() === 'no') {
									$('.data-discounts-table-container').hide();
								} else {
									showDiscountsTable()
								}
							}).trigger('change');

						});
					</script>
					<?php
				}
			}
		}
	}
}
