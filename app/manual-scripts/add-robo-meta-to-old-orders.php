<?php /** @noinspection ALL */
// Запуск: wp eval-file manual-scripts/add-robo-meta-to-old-orders.php
// Добавляет мета запись для старых заказов

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

/*
 * 1) Получаем все подписки
 * 2) Получаем все заказы каждой подписки
 * 3) Проверяем, что заказ имеет мета
 * _created_via	checkout и _payment_method_title	Robokassa
 *
 * 4) Если нету мета _robokassa_recurring - пишем (в будущем ставим)
 */

$subscriptions = wcs_get_subscriptions( array(
	'subscriptions_per_page' => - 1, // без лимита
	'order'                  => 'DESC',
	'orderby'                => 'start_date',
) );

foreach ( $subscriptions as $subscription ) {

	$subscription_orders = $subscription->get_related_orders( 'all' );

	foreach ( $subscription_orders as $order ) {

		// Заказ не был создан клиентом вручную
		if ( 'checkout' !== $order->get_meta( '_created_via' ) ) {
			continue;
		}

		// Заказ не был оплачен робокассой
		if ( 'Robokassa' !== $order->get_meta( '_payment_method_title' ) ) {
			continue;
		}

		// Сумма заказа нулевая
		if ( $order->calculate_totals() <= 0 ) {
			continue;
		}

		// Мета поле итак есть
		if ( 'true' === $order->get_meta( '_robokassa_recurring' ) ) {
			continue;
		}

		var_dump( $order->get_id() );
		$order->update_meta_data( '_robokassa_recurring', 'true' );
		$order->save();
	}
}

var_dump( 'Готово' );