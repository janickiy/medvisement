<?php
// Запуск: wp eval-file manual-scripts/fix-taxonomy-age.php
// Переносит дубликаты термов и удаляет их, разовый скрипт

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

$diseases = get_posts( [
	'post_type'        => 'disease',
	'post_status'      => 'any',
	'suppress_filters' => TRUE,
	'tax_query'        => [
		[
			'taxonomy' => 'age',
			'field'    => 'term_id',
			'terms'    => [ 1785, 1787 ] // Оригинальные - 4 взрослый, 5 ребенок
		]
	],
	'numberposts'      => - 1
] );

foreach ( $diseases as $post ) {
	$terms = wp_get_post_terms( $post->ID, 'age', [ 'fields' => 'ids' ] );

	// 1780 => 4
	if ( in_array( 1780, $terms ) ) {
		wp_set_object_terms( $post->ID, 4, 'age', TRUE );
		wp_remove_object_terms( $post->ID, 1785, 'age' );
	}
	// 1781 => 5
	if ( in_array( 1781, $terms ) ) {
		wp_set_object_terms( $post->ID, 5, 'age', TRUE );
		wp_remove_object_terms( $post->ID, 1787, 'age' );
	}
}

// Удаляем впринципе
wp_delete_term( 1785, 'age' );
wp_delete_term( 1787, 'age' );