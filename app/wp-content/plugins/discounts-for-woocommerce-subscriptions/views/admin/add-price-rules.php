<?php
/**
 * Add price rules simple
 *
 * @var string $type
 * @var string $prefix
 * @var array $price_rules_fixed
 * @var array $price_rules_percentage
 */

$description = __( 'to set up progressive discounts for renewal payments, click on "new discount rule." In the first box, indicate the renewal sequence number from which the discount begins and specify the discount value in the second box. To create multilevel discounts, add more rules and set from what renewal sequence they should apply.', 'discounts-for-woocommerce-subscriptions' );

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<script>
	jQuery(document).ready(function ($) {
		$('[data-subscription-discounts-type-select]').on('change', function () {
			$('[data-subscription-discounts-type]').css('display', 'none');
			$('[data-subscription-discounts-type-' + this.value + ']').css('display', 'block');
		});
	});
</script>
<div style="border-top: 1px solid #f5f5f5">
	<p class="form-field">
		<label for="subscription-discounts-type-select">
			<?php esc_attr_e( 'Discounts pricing type', 'discounts-for-woocommerce-subscriptions' ); ?>
		</label>
		<select name="subscriptions_discounts_rules_type_<?php echo esc_attr( $prefix ); ?>"
				id="subscription-discounts-type-select"
				style="width: 50%"
				data-subscription-discounts-type-select>
			<option value="fixed" <?php selected( 'fixed', $type ); ?> >
				<?php esc_attr_e( 'Fixed', 'discounts-for-woocommerce-subscriptions' ); ?>
			</option>
			<option value="percentage" <?php selected( 'percentage', $type ); ?> >
				<?php
				esc_attr_e( 'Percentage',
					'discounts-for-woocommerce-subscriptions' );
				?>
			</option>
		</select>
	</p>

	<p class="form-field <?php echo 'percentage' === $type ? 'hidden' : ''; ?>" data-subscription-discounts-type-fixed
	   data-subscription-discounts-type>
		<label>
			<?php
			echo esc_attr__( 'Discounts', 'discounts-for-woocommerce-subscriptions' ) . wc_help_tip( $description );
			?>
		</label>
		<span data-discounts-rules-wrapper>
		<?php if ( ! empty( $price_rules_fixed ) ) : ?>
			<?php foreach ( $price_rules_fixed as $amount => $price ) : ?>
				<span data-discounts-rules-container>
					<span data-price-rules-input-wrapper>
						<input type="number" value="<?php echo esc_attr( $amount ); ?>" min="1"
							   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
							   class="price-quantity-rule price-quantity-rule--simple"
							   name="subscriptions_discounts_fixed_quantity_<?php echo esc_attr( $prefix ); ?>[]">
						<input type="text" value="<?php echo esc_attr( wc_format_localized_price( $price ) ); ?>"
							   placeholder="<?php esc_attr_e( 'Price', 'discounts-for-woocommerce-subscriptions' ); ?>"
							   class="wc_input_price price-quantity-rule--simple"
							   name="subscriptions_discounts_fixed_price_<?php echo esc_attr( $prefix ); ?>[]">
					</span>
					<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule></span>
					<br>
					<br>
				</span>

			<?php endforeach; ?>
		<?php endif; ?>

		<span data-discounts-rules-container>
			<span data-price-rules-input-wrapper>
				<input type="number" min="1"
					   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
					   class="price-quantity-rule price-quantity-rule--simple"
					   name="subscriptions_discounts_fixed_quantity_<?php echo esc_attr( $prefix ); ?>[]">
				<input type="text"
					   placeholder="<?php esc_attr_e( 'Price', 'discounts-for-woocommerce-subscriptions' ); ?>"
					   class="wc_input_price  price-quantity-rule--simple"
					   name="subscriptions_discounts_fixed_price_<?php echo esc_attr( $prefix ); ?>[]">
			</span>
			<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule></span>
			<br>
			<br>
		</span>
	<button data-add-new-discount-rule
			class="button"><?php esc_attr_e( 'New discount rule', 'discounts-for-woocommerce-subscriptions' ); ?></button>
	</span>
	</p>

	<p class="form-field <?php echo 'fixed' === $type ? 'hidden' : ''; ?>" data-subscription-discounts-type-percentage
	   data-subscription-discounts-type>
		<label>
			<?php
			echo esc_attr__( 'Discounts', 'discounts-for-woocommerce-subscriptions' ) . wc_help_tip( $description );
			?>
		</label>
		<span data-discounts-rules-wrapper>
		<?php if ( ! empty( $price_rules_percentage ) ) : ?>
			<?php foreach ( $price_rules_percentage as $amount => $discount ) : ?>
				<span data-discounts-rules-container>
					<span data-price-rules-input-wrapper>
						<input type="number" value="<?php echo esc_attr( $amount ); ?>" min="1"
							   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
							   class="price-quantity-rule price-quantity-rule--simple"
							   name="subscriptions_discounts_percent_quantity_<?php echo esc_attr( $prefix ); ?>[]">
						<input type="number" value="<?php echo esc_attr( $discount ); ?>" max="100"
							   placeholder="<?php esc_attr_e( 'Percent discount', 'discounts-for-woocommerce-subscriptions' ); ?>"
							   class="price-quantity-rule--simple"
							   name="subscriptions_discounts_percent_discount_<?php echo esc_attr( $prefix ); ?>[]"
							   step="any">
					</span>
					<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule></span>
					<br>
					<br>
				</span>

			<?php endforeach; ?>
		<?php endif; ?>

		<span data-discounts-rules-container>
			<span data-price-rules-input-wrapper>
				<input type="number" min="1"
					   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
					   class="price-quantity-rule price-quantity-rule--simple"
					   name="subscriptions_discounts_percent_quantity_<?php echo esc_attr( $prefix ); ?>[]">
				<input type="number" max="100"
					   placeholder="<?php esc_attr_e( 'Discount', 'discounts-for-woocommerce-subscriptions' ); ?>"
					   class="price-quantity-rule--simple"
					   name="subscriptions_discounts_percent_discount_<?php echo esc_attr( $prefix ); ?>[]" step="any">
			</span>
			<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule></span>
			<br>
			<br>
		</span>
	<button data-add-new-discount-rule
			class="button"><?php esc_attr_e( 'New discount rule', 'discounts-for-woocommerce-subscriptions' ); ?></button>

	</span>
	</p>

</div>

<?php wp_nonce_field( 'save_simple_product_subscription_discount__data', '_simple_product_dfws_nonce' ); ?>
