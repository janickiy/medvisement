<?php /** @noinspection ALL */
// Запуск: wp eval-file manual-scripts/fix-subscriptions-post-types.php
// Добавляет мета запись для старых заказов

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$query = "
SELECT 
    pm.meta_id,
  pm.post_id 
FROM 
  `{$wpdb->prefix}postmeta` pm
WHERE
    pm.meta_key = '_schedule_trial_end'
";

$possible_subscriptions_meta = $wpdb->get_results( $query );

$subscription_ids = [];
$order_ids = [];

foreach ( $possible_subscriptions_meta as $subscription_meta ) {
	$query = "
	SELECT 
    pm.meta_id
FROM 
  `{$wpdb->prefix}postmeta` pm
WHERE
    pm.meta_key = '_payment_method_title'
	AND pm.post_id = {$subscription_meta->post_id}
	";

	$meta_id = $wpdb->get_var( $query );

	// У подписки _schedule_trial_end < _payment_method_title. У заказов наоборот
	if ( $subscription_meta->meta_id < $meta_id ) {
		$subscription_ids[] = $subscription_meta->post_id;
	}
	else {
		$order_ids[] = $subscription_meta->post_id;
	}
}

var_dump("Подписки:");

echo implode(',', $subscription_ids);
echo "\n";

var_dump("Заказы:");

echo implode(',', $order_ids);
echo "\n";