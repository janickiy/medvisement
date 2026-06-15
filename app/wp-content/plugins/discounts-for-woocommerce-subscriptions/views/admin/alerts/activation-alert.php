<?php if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Activation plugin message
 *
 * @var string $link
 */
?>

<div class="updated notice is-dismissible">
	<p>
		<strong>
			<?php esc_attr_e( 'Thanks for installing Discounts for WooCommerce Subscriptions! You can customize it ', 'discounts-for-woocommerce-subscriptions' ); ?>
			<a href="<?php echo esc_url( $link ); ?>"><?php esc_attr_e( 'here', 'discounts-for-woocommerce-subscriptions' ); ?></a>
		</strong>
	</p>
	<button type="button" class="notice-dismiss"></button>
</div>
