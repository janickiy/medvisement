<?php
/**
 *
 * Plugin Name:       Discounts for WooCommerce Subscriptions
 * Plugin URI:        https://woocommerce.com/products/discounts-for-woocommerce-subscriptions/
 * Description:       Offer discounts on subscription renewal payments and improve the LTV of your client's base.
 * Version:           3.1.0
 * Author:            Meow Crew
 * Author URI:        meow-crew.com
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       discounts-for-woocommerce-subscriptions
 * Domain Path:       /languages/
 *
 * Woo: 8715443:f6fb3c8e92c81116dc51fb3ec5f1f366
 *
 * WC requires at least: 4.0
 * WC tested up to: 8.4
 *
 */
	
	use MeowCrew\SubscriptionsDiscounts\SubscriptionsDiscountsPlugin;
	
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
call_user_func( function () {

	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

	$main = new SubscriptionsDiscountsPlugin( __FILE__ );

	register_activation_hook( __FILE__, array( $main, 'activate' ) );

	$main->run();
} );
