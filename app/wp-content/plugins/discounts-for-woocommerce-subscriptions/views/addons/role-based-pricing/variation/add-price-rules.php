<?php if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Available variables
 *
 * @var string $role
 * @var string $type
 * @var int $loop
 * @var float $sale_price
 * @var float $regular_price
 */

$description = __( 'to set up progressive discounts for renewal payments, click on "new discount rule." In the first box, indicate the renewal sequence number from which the discount begins and specify the discount value in the second box. To create multilevel discounts, add more rules and set from what renewal sequence they should apply.', 'discounts-for-woocommerce-subscriptions' );
?>

<p class="form-field">
	<label for="subscriptions_discounts_regular_price_variable-[<?php echo esc_attr( $loop ); ?>]<?php echo esc_attr( $role ); ?>"
		   style="display: block;">
		<?php esc_attr_e( 'Regular price', 'discounts-for-woocommerce-subscriptions' ); ?>
	</label>

	<input type="text"
		   value="<?php echo esc_attr( wc_format_localized_price( $regular_price ) ); ?>"
		   class="wc_input_price"
		   style="width: 50%"
		   id="subscriptions_discounts_regular_price_variable-[<?php echo esc_attr( $loop ); ?>]<?php echo esc_attr( $role ); ?>"
		   name="subscriptions_discounts_regular_price_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>]">
</p>

<p class="form-field">
	<label for="subscriptions_discounts_sale_price_variable-[<?php echo esc_attr( $loop ); ?>]<?php echo esc_attr( $role ); ?>" style="display: block">
		<?php esc_attr_e( 'Sale price', 'discounts-for-woocommerce-subscriptions' ); ?>
	</label>

	<input type="text"
		   value="<?php echo esc_attr( wc_format_localized_price( $sale_price ) ); ?>"
		   class="wc_input_price"
		   style="width: 50%"
		   id="subscriptions_discounts_sale_price_variable-[<?php echo esc_attr( $loop ); ?>]<?php echo esc_attr( $role ); ?>"
		   name="subscriptions_discounts_sale_price_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>]">
</p>


<p class="form-field">
	<label for="subscription-discounts-type-select"
		   style="display: block"><?php esc_attr_e( 'Discounts pricing type', 'discounts-for-woocommerce-subscriptions' ); ?></label>
	<select name="subscriptions_discounts_rules_type_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>]"
			id="subscription-discounts-type-select"
			style="width: 50%"
			data-role-subscription-discounts-type-select>
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

<p class="form-field <?php echo esc_attr( 'percentage' === $type ? 'hidden' : '' ); ?>"
   data-role-subscription-discounts-type-fixed
   data-role-subscription-discounts-type>
	<label style="display: block">
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
							   name="subscriptions_discounts_fixed_quantity_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
						<input type="text" value="<?php echo esc_attr( wc_format_localized_price( $price ) ); ?>"
							   placeholder="<?php esc_attr_e( 'Price', 'discounts-for-woocommerce-subscriptions' ); ?>"
							   class="wc_input_price price-quantity-rule--simple"
							   name="subscriptions_discounts_fixed_price_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
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
					   name="subscriptions_discounts_fixed_quantity_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
				<input type="text"
					   placeholder="<?php esc_attr_e( 'Price', 'discounts-for-woocommerce-subscriptions' ); ?>"
					   class="wc_input_price  price-quantity-rule--simple"
					   name="subscriptions_discounts_fixed_price_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
			</span>
			<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule></span>
			<br>
			<br>
		</span>
	<button data-add-new-discount-rule
			class="button"><?php esc_attr_e( 'New discount rule', 'discounts-for-woocommerce-subscriptions' ); ?></button>
	</span>
</p>

<p class="form-field <?php echo esc_attr( 'fixed' === $type ? 'hidden' : '' ); ?>"
   data-role-subscription-discounts-type-percentage
   data-role-subscription-discounts-type>
	<label style="display: block">
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
							   name="subscriptions_discounts_percent_quantity_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
						<input type="number" value="<?php echo esc_attr( $discount ); ?>" max="99"
							   placeholder="<?php esc_attr_e( 'Percent discount', 'discounts-for-woocommerce-subscriptions' ); ?>"
							   class="price-quantity-rule--simple"
							   step="any"
							   name="subscriptions_discounts_percent_discount_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
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
					   name="subscriptions_discounts_percent_quantity_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
				<input type="number" max="99"
					   placeholder="<?php esc_attr_e( 'Percent discount', 'discounts-for-woocommerce-subscriptions' ); ?>"
					   class="price-quantity-rule--simple"
					   step="any"
					   name="subscriptions_discounts_percent_discount_roles_variable[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $role ); ?>][]">
			</span>
			<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule></span>
			<br>
			<br>
		</span>

	<button data-add-new-discount-rule class="button">
		<?php esc_attr_e( 'New discount rule', 'discounts-for-woocommerce-subscriptions' ); ?>
	</button>
	</span>
</p>
