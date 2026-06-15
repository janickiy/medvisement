<?php
/**
 * Coupon tab containing affiliate's settings
 *
 * @author YITH <plugins@yithemes.com>
 * @package YITH\Affiliates\Views
 * @version 2.0.0
 */

/**
 * Template variables:
 *
 * @var WC_Coupon $coupon
 */

if ( ! defined( 'YITH_WCAF' ) ) {
	exit;
} // Exit if accessed directly
?>
<div id="affiliates_coupon_data" class="panel woocommerce_options_panel">
	<p class="form-field">
		<label for="coupon_referrer">
			<?php echo esc_html_x( 'Affiliate', '[ADMIN] Coupon affiliate tab', 'yith-woocommerce-affiliates' ); ?>
		</label>
		<select
			id="coupon_referrer"
			class="yith-wcaf-enhanced-select"
			name="coupon_referrer"
			style="width: 50%;"
			data-placeholder="<?php echo esc_attr_x( 'Search for an affiliate&hellip;', '[ADMIN] Coupon affiliate tab', 'yith-woocommerce-affiliates' ); ?>"
			data-action="yith_wcaf_get_affiliates_ids"
			data-security="<?php echo esc_attr( wp_create_nonce( 'search-affiliates' ) ); ?>"
			data-allow_clear="true"
		>
			<?php
			$affiliate_id = $coupon->get_meta( 'coupon_referrer' );
			$affiliate    = YITH_WCAF_Affiliates()->get_affiliate_by_id( $affiliate_id );

			if ( $affiliate ) {
				$affiliate_formatted_name = $affiliate->get_formatted_name() . ' (#' . $affiliate->get_user_id() . ' &ndash; ' . $affiliate->get_user()->user_email . ')';

				echo '<option value="' . esc_attr( $affiliate_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $affiliate_formatted_name ) . '</option>';
			}
			?>
		</select>
		<?php echo wp_kses_post( wc_help_tip( _x( 'User that will be referred when someone purchases with this coupon', '[ADMIN] Coupon affiliate tab', 'yith-woocommerce-affiliates' ) ) ); ?>
	</p>
</div>
