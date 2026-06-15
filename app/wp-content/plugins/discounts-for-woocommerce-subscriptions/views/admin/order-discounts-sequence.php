<?php use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;

defined( 'ABSPATH' ) || die;
/**
 * Available variables
 *
 * @var $discounted_order_item DiscountedOrderItem
 */

$hasFirstPaymentPrice = $discounted_order_item->hasFirstPaymentPrice();

if ( $hasFirstPaymentPrice ) {
	$firstTierTip = __( 'First payment discounted price', 'discounts-for-woocommerce-subscriptions' );
} else {
	$firstTierTip = __( 'Without a discount', 'discounts-for-woocommerce-subscriptions' );
}

?>

<div>
	<p class="dfws-order-item-discounts-title">
		<?php esc_html_e( 'Discounts sequence', 'discounts-for-woocommerce-subscriptions' ); ?>:
	</p>

	<div class="dfws-order-item-discounts">
		<div
			class="dfws-order-item-discount help_tip <?php echo esc_attr( $discounted_order_item->getAppliedDiscount() === false ? 'dfws-order-item-discount--active' : '' ); ?>"
			data-tip="<?php echo esc_attr( $firstTierTip ); ?>">
				<span class="dfws-order-item-discount__value">
					<?php if ( $discounted_order_item->getDiscountsType() === 'percentage' ) : ?>
						<?php
						if ( $hasFirstPaymentPrice ) {
							echo esc_html( $discounted_order_item->getFirstPaymentPrice() . '%' );
						} else {
							echo esc_attr( '0%' );
						}
						?>
					<?php else : ?>
						<?php echo wp_kses_post( wc_price( $discounted_order_item->getOriginalPrice( true ) ) ); ?>
					<?php endif; ?>
				</span>
		</div>

		<?php foreach ( $discounted_order_item->getDiscounts() as $quantity => $discount ) : ?>

			<?php if ( $discounted_order_item->getAppliedDiscount() === $quantity ) : ?>
				<div class="help_tip dfws-order-item-discount dfws-order-item-discount--active"
					 data-tip="
					 <?php 
						esc_attr_e( 'Current discount rate that will be applied with next renewal.',
						 'discounts-for-woocommerce-subscriptions' ); 
						?>
						 ">
					<span class="dfws-order-item-discount__value">
						<?php if ( $discounted_order_item->getDiscountsType() === 'percentage' ) : ?>
							<?php echo esc_attr( $discount ); ?>%
						<?php else : ?>
							<?php 
							echo wp_kses_post( wc_price( wc_get_price_to_display( $discounted_order_item->getItem()->get_product(),
								array( 'price' => $discount ) ) ) ); 
							?>
						<?php endif; ?>
					</span>
				</div>
			<?php else : ?>

				<?php
				// translators: %1$s: renewal number, %2$d: current renewals count, %3$d: needed renewals
				$tip = $discounted_order_item->getTotalRenewals() > $quantity ? __( 'Outdated',
					// translators:" %1$s: renewal number, %2$d: current renewal, %3$d: renewal number needed
					'discounts-for-woocommerce-subscriptions' ) : sprintf( __( 'Will be applied on the %1$s renewal. Now: %2$d/%3$d',
					'discounts-for-woocommerce-subscriptions' ), DiscountedOrderItem::formatRenewalNumber( $quantity ),
					$discounted_order_item->getTotalRenewals(), $quantity );
				?>

				<div class="dfws-order-item-discount help_tip"
					 data-tip="<?php echo esc_attr( $tip ); ?>">
				<span class="dfws-order-item-discount__value">
					<?php if ( $discounted_order_item->getDiscountsType() === 'percentage' ) : ?>
						<?php echo esc_attr( $discount ); ?>%
					<?php else : ?>
						<?php 
						echo wp_kses_post( wc_price( wc_get_price_to_display( $discounted_order_item->getItem()->get_product(),
							array( 'price' => $discount ) ) ) ); 
						?>
					<?php endif; ?>
				</span>
				</div>
			<?php endif; ?>


		<?php endforeach; ?>
	</div>
</div>
