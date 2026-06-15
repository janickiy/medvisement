<?php
// Запуск: wp eval-file manual-scripts/grant-article-access-for-telegram.php
// Выдача статьи за привязку телеграмма старым аккаунтам

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$query = "SELECT `user_id`, `tg_user_id` FROM `{$wpdb->prefix}user_telegram`";

$tg_users = $wpdb->get_results( $query );

foreach ( $tg_users as $tg_user ) {

	// Проверяем, были ли просмотры статей
	$query = "SELECT COUNT(*) FROM `{$wpdb->prefix}medvise_page_views` " .
	         "WHERE `user_id` = {$tg_user->user_id};";
	$page_views = (int) $wpdb->get_var($query);

	// Проверяем баланс статей просмотров
	$disease_views = (int) get_user_meta( $tg_user->user_id, 'disease_views', true );

	// Доступа к статья не было и баланс пустой - даем 1 просмотр статьи
	if ( $page_views === 0 && $disease_views === 0 ) {
		update_user_meta( $tg_user->user_id, 'disease_views', 1 );
		echo "Пользователь №{$tg_user->user_id} выдана статья \n";
	}

	// Записываем исторические данные
	$wpdb->query( $wpdb->prepare( 'INSERT INTO `telegram_historical` VALUES (%d) ON DUPLICATE KEY UPDATE tg_user_id=tg_user_id;', $tg_user->tg_user_id ) );
}