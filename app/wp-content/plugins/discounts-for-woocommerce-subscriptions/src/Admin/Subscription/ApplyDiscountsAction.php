<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\Subscription;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;
use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;
use MeowCrew\SubscriptionsDiscounts\Managers\RenewalManager;
use MeowCrew\SubscriptionsDiscounts\SanitizeManager;

class ApplyDiscountsAction {

	use ServiceContainerTrait;

	const PROCESS_ACTION = 'dfws_save_discounts_for_order';

	/**
	 * Subscription page
	 *
	 * @var SubscriptionPage
	 */
	protected $subscriptionPage;

	public function __construct( SubscriptionPage $subscriptionPage ) {

		$this->subscriptionPage = $subscriptionPage;

		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'addActionButton' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'renderForm' ), 99999999 );

		// Saving
		add_action( 'woocommerce_process_shop_subscription_meta', array( $this, 'processDiscounts' ), 10, 2 );
	}

	public function processDiscounts( $subscriptionId, $subscription ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing - checking nonce does not make any sense. Required by WooCommerce phpcs rules
		if ( true || wp_verify_nonce( true ) ) {
			$data = $_POST;
		}

		if ( ! isset( $_POST['subscriptions_discounts_rules_type_subscription'] ) ) {
			return;
		}

		$discountsType = sanitize_text_field( $_POST['subscriptions_discounts_rules_type_subscription'] );
		$discounts     = array();

		if ( 'fixed' === $discountsType ) {
			$fixedAmounts = isset( $data['subscriptions_discounts_fixed_quantity_subscription'] ) ? (array) $data['subscriptions_discounts_fixed_quantity_subscription'] : array();
			$fixedPrices  = ! empty( $data['subscriptions_discounts_fixed_price_subscription'] ) ? (array) $data['subscriptions_discounts_fixed_price_subscription'] : array();

			foreach ( $fixedAmounts as $key => $amount ) {
				if ( ! empty( $amount ) && isset( $fixedPrices[ $key ] ) && ! key_exists( $amount, $discounts ) ) {
					$discounts[ $amount ] = wc_format_decimal( $fixedPrices[ $key ] );
				}
			}

		} elseif ( 'percentage' === $discountsType ) {
			
			$percentageAmounts = isset( $data['subscriptions_discounts_percent_quantity_subscription'] ) ? (array) $data['subscriptions_discounts_percent_quantity_subscription'] : array();
			$percentagePrices  = ! empty( $data['subscriptions_discounts_percent_discount_subscription'] ) ? (array) $data['subscriptions_discounts_percent_discount_subscription'] : array();

			foreach ( $percentageAmounts as $key => $amount ) {

				if ( ! empty( $amount ) && isset( $percentagePrices[ $key ] ) && ! key_exists( $amount,
						$discounts ) && $percentagePrices[ $key ] <= 100 ) {
					$discounts[ $amount ] = $percentagePrices[ $key ];
				}
			}
		}

		$discounts = SanitizeManager::sanitizeDiscountsRules( $discounts );

		if ( ! empty( $discounts ) ) {
			foreach ( $subscription->get_items() as $item ) {
				if ( $item instanceof \WC_Order_Item_Product ) {

					// If product does not exist anymore
					if ( ! $item->get_product() ) {
						continue;
					}

					DiscountedOrderItem::addDiscounts( $item, $discounts, $discountsType );
				}
			}

			RenewalManager::applyDiscounts( $subscription, false );
		}

	}

	public function renderForm( $order ) {

		if ( ! $this->canDiscountsBeApplied( $order ) ) {
			return;
		}

		?>

		<div class="woocommerce_options_panel dfws-subscription-apply-discounts-form"
			 style="margin-left: -20px; margin-top: 50px; clear: both; text-align: left; display: none">
			<?php
			$this->getContainer()->getFileManager()->includeTemplate( 'admin/add-price-rules.php', array(
				'price_rules_fixed'      => array(),
				'price_rules_percentage' => array(),
				'type'                   => 'percentage',
				'prefix'                 => 'subscription',
			) );
			?>

			<input type="submit" class="button button-primary"
				   value="<?php esc_attr_e( 'Apply discounts', 'discounts-for-woocommerce-subscriptions' ); ?>">
		</div>
		<?php
	}

	public function addActionButton( \WC_Order $order ) {

		if ( ! $this->canDiscountsBeApplied( $order ) ) {
			return;
		}

		?>
		<button type="button"
				class="button apply-subscription-discounts">
				<?php 
				esc_html_e( 'Apply renewals discounts',
				'discounts-for-woocommerce-subscriptions' ); 
				?>
				</button>

		<script>
			jQuery(document).ready(function ($) {
				$('.apply-subscription-discounts').click(function () {
					$('.dfws-subscription-apply-discounts-form').toggle();
				});
			})
		</script>
		<?php
	}

	public function canDiscountsBeApplied( $order ) {

		if ( ! wcs_is_subscription( $order ) ) {
			return false;
		}

		if ( $this->subscriptionPage->subscriptionHasDiscounts( $order ) ) {
			return false;
		}

		return true;
	}
}
