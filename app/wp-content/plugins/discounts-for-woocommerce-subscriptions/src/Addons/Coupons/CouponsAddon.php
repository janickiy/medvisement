<?php namespace MeowCrew\SubscriptionsDiscounts\Addons\Coupons;

use MeowCrew\SubscriptionsDiscounts\Addons\AbstractAddon;
use MeowCrew\SubscriptionsDiscounts\DiscountsManager;
use WC_Coupon;

class CouponsAddon extends AbstractAddon {

	const SETTING_ENABLE_KEY = 'enable_coupons_discounts_addon';
	const SETTING_OVERRIDE_DISCOUNTS_KEY = 'override_discounts';

	public function getName() {
		return __( 'Coupons for WooCommerce Subscriptions', 'discounts-for-woocommerce-subscriptions' );
	}

	public function isActive() {
		return $this->getContainer()->getSettings()->get( self::SETTING_ENABLE_KEY, 'yes' ) === 'yes';
	}

	public function run() {
		add_filter( 'woocommerce_coupon_data_tabs', array( $this, 'addNewTab' ) );
		add_action( 'woocommerce_coupon_data_panels', array( $this, 'renderSubscriptionDiscountsTab' ) );

		// Saving
		add_action( 'woocommerce_coupon_options_save', array( $this, 'updatePriceRules' ), 10, 2 );

		add_filter( 'subscription_discounts/cart/cart_item_discounts', function ( $originalDiscounts, $productId ) {

			// If product has own rules and setting isn't set to override them by a coupon - do no modify anything.
			if ( ! empty( $originalDiscounts ) && ! $this->isOverrideDiscounts() ) {
				return $originalDiscounts;
			}

			$cart = wc()->cart;

			if ( ! $cart ) {
				return $originalDiscounts;
			}

			foreach ( $cart->get_coupons() as $coupon ) {

				$discounts     = DiscountsManager::getDiscounts( $coupon->get_id(), null, null, false );
				$discountsType = DiscountsManager::getDiscountsType( $coupon->get_id() );

				if ( $discounts ) {

					add_filter( 'subscription_discounts/cart/cart_item_discounts_type',
						function ( $__type, $__productId ) use ( $discountsType, $productId ) {
							if ( $productId === $__productId ) {
								return $discountsType;
							}

							return $__type;
						}, 10, 2 );

					return $discounts;
				}
			}

			return $originalDiscounts;
		}, 10, 2 );
	}

	public function renderSubscriptionDiscountsTab() {

		global $post;

		?>
		<div id="subscription_discounts_coupon_data" class="panel woocommerce_options_panel">

			<div>
				<?php

				$type = DiscountsManager::getDiscountsType( $post->ID, 'fixed', 'edit' );

				$this->getContainer()->getFileManager()->includeTemplate( 'admin/add-price-rules.php', array(
					'price_rules_fixed'      => DiscountsManager::getFixedDiscounts( $post->ID, 'edit' ),
					'price_rules_percentage' => DiscountsManager::getPercentageDiscounts( $post->ID, 'edit' ),
					'type'                   => $type,
					'prefix'                 => 'coupon',
				) );

				?>
			</div>
		</div>
		<?php
	}

	public function isOverrideDiscounts() {
		return $this->getContainer()->getSettings()->get( self::SETTING_OVERRIDE_DISCOUNTS_KEY, 'yes' ) === 'yes';

	}

	/**
	 * Update price quantity rules for coupon
	 *
	 * @param  int  $couponId
	 */
	public function updatePriceRules( $couponId, WC_Coupon $coupon ) {

		// Not allowed types
		/*      if ( in_array( $coupon->get_discount_type(), array( 'recurring_fee', 'recurring_percent' ) ) ) {
			DiscountsManager::updatePercentageDiscounts( array(), array(), $couponId );

			$template = 'Renewals discounts cannot be set for recurring discount type of coupon. <a target="_blank" href="https://woocommerce.com/document/discounts-for-woocommerce-subscriptions/#section-3">Read more</a>';
			wcs_add_admin_notice( __( $template, 'discounts-for-woocommerce-subscriptions' ), 'error' );

			return;
		}*/

		$nonce = isset( $_POST['_simple_product_dfws_nonce'] ) ? sanitize_key( $_POST['_simple_product_dfws_nonce'] ) : false;

		if ( wp_verify_nonce( $nonce, 'save_simple_product_subscription_discount__data' ) ) {

			$data = $_POST;

			$fixedAmounts = isset( $data['subscriptions_discounts_fixed_quantity_coupon'] ) ? (array) $data['subscriptions_discounts_fixed_quantity_coupon'] : array();
			$fixedPrices  = ! empty( $data['subscriptions_discounts_fixed_price_coupon'] ) ? (array) $data['subscriptions_discounts_fixed_price_coupon'] : array();

			DiscountsManager::updateFixedDiscounts( $fixedAmounts, $fixedPrices, $couponId );

			$percentageAmounts = isset( $data['subscriptions_discounts_percent_quantity_coupon'] ) ? (array) $data['subscriptions_discounts_percent_quantity_coupon'] : array();
			$percentagePrices  = ! empty( $data['subscriptions_discounts_percent_discount_coupon'] ) ? (array) $data['subscriptions_discounts_percent_discount_coupon'] : array();

			DiscountsManager::updatePercentageDiscounts( $percentageAmounts, $percentagePrices, $couponId );

			if ( isset( $_POST['subscriptions_discounts_rules_type_coupon'] ) ) {
				DiscountsManager::updateDiscountsType( $couponId,
					sanitize_text_field( $_POST['subscriptions_discounts_rules_type_coupon'] ) );
			}
		}
	}

	public function addNewTab( $tabs ) {
		$tabs['subscription_discounts'] = array(
			'label'  => __( 'Subscription discounts', 'discounts-for-woocommerce-subscriptions' ),
			'target' => 'subscription_discounts_coupon_data',
			'class'  => 'subscription_discounts_coupon_data',
		);

		return $tabs;
	}
}
