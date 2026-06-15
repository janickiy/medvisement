<?php
// Запуск: wp eval-file manual-scripts/fix-subscriptions-statuses.php
// Добавляет мета запись для старых заказов

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$subscriptions = wcs_get_subscriptions( [
	'subscription_status'    => [ 'pending' ],
	'subscriptions_per_page' => - 1
] );

foreach ( $subscriptions as $subscription ) {

	$subscription_id = $subscription->get_id();
	$last_order_id   = $subscription->get_last_order();

	$subscription_orders = $subscription->get_related_orders( 'all' );

	// Если один заказ и он не оплачен - на рассмотрении (wc-pending)
	if (
		count( $subscription_orders ) == 1
		&& $subscription_orders[ key( $subscription_orders ) ]->get_status() == 'pending'
		&& $subscription->get_status() == 'pending'
	) {
		// Ничего не делаем
	} // Последний заказ оплачен - Активно (wc-active)
	elseif ( $subscription_orders[ key( $subscription_orders ) ]->get_status() == 'completed' ) {
		// Ставим статус активно в wp_posts и в wp_wc_orders

		$query = "UPDATE `{$wpdb->prefix}posts` SET post_status = 'wc-active' WHERE ID = $subscription_id;";
		$wpdb->query( $query );
		$query = "UPDATE `{$wpdb->prefix}wc_orders` SET status = 'wc-active' WHERE id = $subscription_id;";
		$wpdb->query( $query );


	} // Последний заказ не оплачен и есть другие - на удержании (wc-on-hold)
	elseif (
		$subscription_orders[ key( $subscription_orders ) ]->get_status() == 'failed'
		|| $subscription_orders[ key( $subscription_orders ) ]->get_status() == 'pending'
	) {
		$query = "UPDATE `{$wpdb->prefix}posts` SET post_status = 'wc-on-hold' WHERE ID = $subscription_id;";
		$wpdb->query( $query );
		$query = "UPDATE `{$wpdb->prefix}wc_orders` SET status = 'wc-on-hold' WHERE id = $subscription_id;";
		$wpdb->query( $query );
	} else {
		var_dump( 'Невозможная ситуация: ' . $subscription->get_id() );
	}

}