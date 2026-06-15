<?php
// Запуск: wp eval-file app-scripts/clear-db.php
// Очистка БД для тестового сайта

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( '-1' );
}

ini_set( 'memory_limit', '2G' );
set_time_limit( 0 );
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$full_clear_tables = [
	'telegram_historical',
	'telegram_sessions',
	'wp_actionscheduler_actions',
	'wp_actionscheduler_logs',
	'wp_woocommerce_sessions',
	'wp_user_telegram'
];

echo "Очистка таблиц полностью...\n";
foreach ( $full_clear_tables as $table ) {
	$wpdb->query( "TRUNCATE TABLE $table;" );
	echo "Очищена таблица: $table\n";
}

echo "Очистка ревизий и черновиков...\n";
$wpdb->query( "DELETE FROM wp_posts WHERE post_type='revision';" );
$wpdb->query( "DELETE FROM wp_posts WHERE post_status='auto-draft';" );

// Удаляем заболевания
echo "Удаляем заболевания...\n";
$posts = get_posts( [
	'post_type'      => 'disease',
	'posts_per_page' => - 1,
	'fields'         => 'ids'
] );

shuffle( $posts );

$keep_ids   = array_slice( $posts, 0, 200 );
$delete_ids = array_diff( $posts, $keep_ids );

foreach ( $delete_ids as $post_id ) {
	wp_delete_post( $post_id, true );
}

// Удаляем препараты
echo "Удаляем препараты...\n";
$posts = get_posts( [
	'post_type'      => 'substance',
	'posts_per_page' => - 1,
	'fields'         => 'ids'
] );

shuffle( $posts );

$keep_ids   = array_slice( $posts, 0, 200 );
$delete_ids = array_diff( $posts, $keep_ids );

foreach ( $delete_ids as $post_id ) {
	wp_delete_post( $post_id, true );
}

// Удаляем подписчиков
$subscribers = get_users( [
	'role'   => 'subscriber',
	'fields' => 'ID',
] );

shuffle( $subscribers );

$keep_ids   = array_slice( $subscribers, 0, 300 );
$delete_ids = array_diff( $subscribers, $keep_ids );

foreach ( $delete_ids as $user_id ) {

	// Шаблоны и заметки
	$wpdb->delete(
		"{$wpdb->prefix}medvise_user_templates",
		['user_id' => $user_id]
	);
	$wpdb->delete(
		"{$wpdb->prefix}medvise_user_notes",
		['user_id' => $user_id]
	);

	// Заказы
	$orders = $wpdb->get_col( $wpdb->prepare( "
        SELECT p.ID
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm 
            ON pm.post_id = p.ID
        WHERE p.post_type IN ('shop_order','shop_subscription')
        AND pm.meta_key = '_customer_user'
        AND pm.meta_value = %d
    ", $user_id ) );

	foreach ( $orders as $order_id ) {
		$items = $wpdb->get_col( $wpdb->prepare( "
            SELECT order_item_id
            FROM {$wpdb->prefix}woocommerce_order_items
            WHERE order_id = %d
        ", $order_id ) );

		foreach ( $items as $item_id ) {
			$wpdb->delete(
				"{$wpdb->prefix}woocommerce_order_itemmeta",
				[ 'order_item_id' => $item_id ]
			);
		}

		$wpdb->delete(
			"{$wpdb->prefix}woocommerce_order_items",
			[ 'order_id' => $order_id ]
		);

		$wpdb->delete(
			$wpdb->postmeta,
			[ 'post_id' => $order_id ]
		);

		wp_delete_post( $order_id, true );
	}

	wp_delete_user( $user_id );

	echo "Удален подписчик: {$user_id}\n";
}