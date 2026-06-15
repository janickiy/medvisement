<?php if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Add pricing rules at admin panel template.
 *
 * @var int $i
 * @var string $type
 * @var array $price_rules
 * @var array $price_rules_fixed
 * @var array $price_rules_percentage
 */

$description = __( 'to set up progressive discounts for renewal payments, click on "new discount rule." In the first box, indicate the renewal sequence number from which the discount begins and specify the discount value in the second box. To create multilevel discounts, add more rules and set from what renewal sequence they should apply.', 'discounts-for-woocommerce-subscriptions' );
?>

<script>
	jQuery(document).on('woocommerce_variations_loaded', function ($) {
		jQuery('[data-subscription-discounts-type-select]').on('change', function () {
			var $wrapper = jQuery(this).closest('.woocommerce_variation');
			$wrapper.find('[data-subscription-discounts-type]').css('display', 'none');
			$wrapper.find('[data-subscription-discounts-type-' + this.value + ']').css('display', 'block');
		});
	});
</script>

<p class="form-field form-row">
	<label for="subscription-discounts-type-select"><?php esc_attr_e( 'Discounts pricing type', 'discounts-for-woocommerce-subscriptions' ); ?></label>
	<br>
	<select name="subscriptions_discounts_rules_type[<?php echo esc_attr( $i ); ?>]"
			id="subscription-discounts-type-select"
			style="width: 48%"
			data-subscription-discounts-type-select>
		<option value="fixed" <?php selected( 'fixed', $type ); ?> >
			<?php
			esc_attr_e( 'Fixed',
				'discounts-for-woocommerce-subscriptions' );
			?>
		</option>
		<option value="percentage" <?php selected( 'percentage', $type ); ?> >
			<?php
			esc_attr_e( 'Percentage',
				'discounts-for-woocommerce-subscriptions' );
			?>
		</option>
	</select>
</p>

<?php $pricingTypeClass = 'percentage' === $type ? 'hidden' : ''; ?>

<p class="form-field form-row <?php echo esc_attr( $pricingTypeClass ); ?>" data-subscription-discounts-type-fixed
   data-subscription-discounts-type style="margin-top: 0" data-discounts-rules-wrapper>
	<label>
		<?php
		echo esc_attr__( 'Discounts', 'discounts-for-woocommerce-subscriptions' ) . wc_help_tip( $description );
		?>
	</label>
	<br>
	<?php $j = 0; ?>
	<?php if ( ! empty( $price_rules_fixed ) ) : ?>
		<?php foreach ( $price_rules_fixed as $amount => $price ) : ?>
			<span data-discounts-rules-container>
				<span data-price-rules-input-wrapper>
					<input type="number" value="<?php echo esc_attr( $amount ); ?>" min="1"
						   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
						   class="price-quantity-rule price-quantity-rule--variation"
						   name="subscriptions_discounts_fixed_quantity[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]">
					<input type="text" value="<?php echo esc_attr( wc_format_localized_price( $price ) ); ?>"
						   placeholder="<?php esc_attr_e( 'Price', 'discounts-for-woocommerce-subscriptions' ); ?>"
						   class="wc_input_price price-quantity-rule--variation"
						   name="subscriptions_discounts_fixed_price[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]">
				</span>
				<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule
					  style="vertical-align: middle;"></span>
				<br>
				<br>
			</span>
			<?php $j ++; ?>
		<?php endforeach; ?>
	<?php endif; ?>

	<span data-discounts-rules-container>
		<span data-price-rules-input-wrapper>
			<input type="number" min="1"
				   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
				   class="price-quantity-rule price-quantity-rule--variation"
				   name="subscriptions_discounts_fixed_quantity[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]">
			<input type="text" placeholder="<?php esc_attr_e( 'Price', 'discounts-for-woocommerce-subscriptions' ); ?>"
				   class="wc_input_price  price-quantity-rule--variation"
				   name="subscriptions_discounts_fixed_price[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]">
		</span>
		<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule
			  style="vertical-align: middle;"></span>
		<br>
		<br>
	</span>

	<button class="button"
			data-add-new-discount-rule><?php esc_attr_e( 'New discount rule', 'discounts-for-woocommerce-subscriptions' ); ?></button>
</p>

<p class="form-field form-row <?php echo 'fixed' === $type ? 'hidden' : ''; ?>"
   data-subscription-discounts-type-percentage
   data-subscription-discounts-type style="margin-top: 0" data-discounts-rules-wrapper>
	<label>
		<?php
		echo esc_attr__( 'Discounts', 'discounts-for-woocommerce-subscriptions' ) . wc_help_tip( $description );
		?>
	</label>
	<br>
	<?php $j = 0; ?>
	<?php if ( ! empty( $price_rules_percentage ) ) : ?>
		<?php foreach ( $price_rules_percentage as $amount => $discount ) : ?>
			<span data-discounts-rules-container>
				<span data-price-rules-input-wrapper>
					<input type="number" value="<?php echo esc_attr( $amount ); ?>" min="1"
						   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
						   class="price-quantity-rule price-quantity-rule--variation"
						   name="subscriptions_discounts_percent_quantity[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]">
					<input type="number" value="<?php echo esc_attr( $discount ); ?>"
						   max="100"
						   placeholder="<?php esc_attr_e( 'Percent discount', 'discounts-for-woocommerce-subscriptions' ); ?>"
						   class="wc_input_price price-quantity-rule--variation"
						   name="subscriptions_discounts_percent_discount[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]"
						   step="any">
				</span>
				<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule
					  style="vertical-align: middle;"></span>
				<br>
				<br>
			</span>
			<?php $j ++; ?>
		<?php endforeach; ?>
	<?php endif; ?>

	<span data-discounts-rules-container>
		<span data-price-rules-input-wrapper>
			<input type="number" min="1"
				   placeholder="<?php esc_attr_e( 'Renewal sequence number', 'discounts-for-woocommerce-subscriptions' ); ?>"
				   class="price-quantity-rule price-quantity-rule--variation"
				   name="subscriptions_discounts_percent_quantity[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]">
			<input type="text"
				   max="100"
				   placeholder="<?php esc_attr_e( 'Discount', 'discounts-for-woocommerce-subscriptions' ); ?>"
				   class="wc_input_price  price-quantity-rule--variation"
				   name="subscriptions_discounts_percent_discount[<?php echo esc_attr( $i ); ?>][<?php echo esc_attr( $j ); ?>]"
				   step="any">
		</span>
		<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule
			  style="vertical-align: middle;"></span>
		<br>
		<br>
	</span>

	<button class="button" data-add-new-discount-rule>
		<?php esc_attr_e( 'New discount rule', 'discounts-for-woocommerce-subscriptions' ); ?>
	</button>
</p>
