<?php

use MeowCrew\SubscriptionsDiscounts\DiscountsManager;

if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Available discounts
 *
 * @var WC_Product $product
 * @var array $price_rules
 * @var string $real_price
 * @var string $product_name
 * @var int $product_id
 * @var array $settings
 */

$firstPaymentDiscount = DiscountsManager::getFirstPaymentPrice( $product_id );
$real_price = $firstPaymentDiscount ? $firstPaymentDiscount : $real_price;
?>

<?php if ( ! empty( $price_rules ) ) : ?>
	<div class="clear"></div>
	<div class="discounts-for-subscriptions-table-wrapper"  >
		<?php if ( ! empty( $settings['table_title'] ) ) : ?>
			<h3 style="clear:both; margin: 20px 0;"><?php echo esc_attr( $settings['table_title'] ); ?></h3>
		<?php endif; ?>

		<?php do_action( 'before_fixed_subscription_discounts' ); ?>

		<table class="shop_table discounts-for-subscriptions-table"
			   data-discounts-for-subscriptions
			   data-product-id="<?php echo esc_attr( $product_id ); ?>"
			   data-product-name="<?php echo esc_attr( $product_name ); ?>">

			<?php if ( '' != $settings['head_quantity_text'] && '' != $settings['head_price_text'] ) : ?>
				<thead>
				<tr>
					<th>
						<span class="nobr"><?php echo esc_attr( sanitize_text_field( __( strtr( $settings['head_quantity_text'], array( '{period}' => WC_Subscriptions_Product::get_period( $product ) ) ), 'discounts-for-woocommerce-subscriptions') ) ); ?></span>
					</th>
					<th>
						<span class="nobr"><?php echo esc_attr( sanitize_text_field( $settings['head_price_text'] ) ); ?></span>
					</th>
					<?php
					do_action( 'subscription_discounts_fixed_header_columns', $price_rules, $real_price,
						$product_id );
					?>
				</tr>
				</thead>
			<?php endif; ?>

			<tbody>
			<tr data-price-rules-amount="1"
				data-price-rules-price="
				<?php
				echo esc_attr( wc_get_price_to_display( wc_get_product( $product_id ), array(
					'price' => $real_price,
				) ) );
				?>
				" data-price-rules-row>
				<td>
					<?php if ( 1 >= array_keys( $price_rules )[0] - 1 ) : ?>
						<span><?php echo esc_attr( number_format_i18n( 1 ) ); ?></span>
					<?php else : ?>
						<span><?php echo esc_attr( number_format_i18n( 1 ) ); ?> - <?php echo esc_attr( number_format_i18n( array_keys( $price_rules )[0] - 1 ) ); ?></span>
					<?php endif; ?>
				</td>
				<td>

					<span data-price-rules-formated-price>
						<?php
						echo wp_kses_post( wc_price( wc_get_price_to_display( wc_get_product( $product_id ),
							array(
								'price' => $real_price,
							) ) ) );
						?>
					</span>
				</td>
			</tr>

			<?php $iterator = new ArrayIterator( $price_rules ); ?>

			<?php while ( $iterator->valid() ) : ?>
				<?php
				$current_price    = $iterator->current();
				$current_quantity = $iterator->key();

				$iterator->next();

				if ( $iterator->valid() ) {
					$quantity = $current_quantity;

					if ( intval( $iterator->key() - 1 != $current_quantity ) ) {
						$quantity = number_format_i18n( $quantity ) . ' - ' . number_format_i18n( intval( $iterator->key() - 1 ) );
					}
				} else {
					$quantity = number_format_i18n( $current_quantity ) . '+';
				}
				?>
				<tr data-price-rules-amount="<?php echo esc_attr( $current_quantity ); ?>"
					data-price-rules-price="
					<?php
					echo esc_attr( wc_get_price_to_display( wc_get_product( $product_id ),
						array(
							'price' => $current_price,
						) ) );
					?>
						" data-price-rules-row>
					<td>
						<span><?php echo esc_attr( $quantity ); ?></span>
					</td>
					<td>
						<span data-price-rules-formated-price>
							<?php
							echo wp_kses_post( wc_price( wc_get_price_to_display( wc_get_product( $product_id ),
								array(
									'price' => $current_price,
								) ) ) );
							?>
						</span>
					</td>
				</tr>

				<?php
				do_action( 'subscription_discounts_fixed_body_columns', $iterator, $price_rules, $real_price,
					$product_id );
				?>

			<?php endwhile; ?>

			</tbody>
		</table>

		<?php do_action( 'after_fixed_subscription_discounts', $product_id, $price_rules, $settings ); ?>

	</div>
	<style>
		.discounts-for-subscriptions-table-wrapper {
		<?php echo 'yes' !== $settings['display'] ? 'display: none; ' : ''; ?>
		}
	</style>
<?php endif; ?>
