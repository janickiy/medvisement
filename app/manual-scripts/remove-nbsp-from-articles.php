<?php
	// Запуск: wp eval-file manual-scripts/remove-nbsp-from-articles.php
// Удаляем неразрывной пробел из статей

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$query = "SELECT `ID`, `post_content` FROM `{$wpdb->posts}` WHERE `post_type` IN ( 'disease', 'substance' );";

$posts = $wpdb->get_results( $query );

foreach ( $posts as $post ) {

	// Заменяем nbsp на пробел
	$post->post_content = preg_replace( "/[\x{A0}]/miu", ' ', $post->post_content );
	$post->post_content = str_replace( "&nbsp;", ' ', $post->post_content );

	// Удаляем двойные пробелы
	$post->post_content = preg_replace( '/([а-яa-z0-9]+) {2,}([а-яa-z0-9]+)/miu', '${1} ${2}', $post->post_content );

	// Обновляем запись
	$query = $wpdb->prepare(
		"UPDATE `{$wpdb->posts}` SET `post_content` = %s WHERE `ID` = %d",
		[
			$post->post_content,
			$post->ID
		]
	);

	$wpdb->query( $query );
}