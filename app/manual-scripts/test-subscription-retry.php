<?php
// Запуск: wp eval-file manual-scripts/test-subscription-retry.php
// Дебаг повторных правил подписка

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

// Симулируем запуск по расписанию
add_filter( 'wcs_is_scheduled_payment_attempt', '__return_true', 99 );

$subscriptions = wcs_get_subscriptions( array(
	'subscriptions_per_page' => - 1, // без лимита
	'order'                  => 'DESC',
	'orderby'                => 'start_date',
	'subscription_status'    => [ 'on-hold' ]
) );

foreach ( $subscriptions as $subscription ) {

	$subscription_related_orders = $subscription->get_related_orders( 'all' );
	$last_paid_order             = null;

	foreach ( $subscription_related_orders as $sub_order ) {
		if (
			$sub_order->status === 'completed'
			&& $sub_order->meta_exists( '_robokassa_recurring' )
			&& $sub_order->calculate_totals() > 0
		) {
			$last_paid_order = $sub_order;
			break;
		}
	}

	// Не смогли найти оплаченный заказ. Видимо подарочная подписка
	if ( null === $last_paid_order ) {
		continue;
	}


	echo "Подписка: " . $subscription->get_id() . ", " .
	     "Заказ родительский: " . $last_paid_order->get_id() . "\n";

	// Форсим ошибку платежа
	$subscription->payment_failed();
}