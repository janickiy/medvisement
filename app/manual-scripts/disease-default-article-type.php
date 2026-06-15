<?php
// Запуск: wp eval-file manual-scripts/disease-default-article-type.php
// Проставляем тип статьи в заболеваниях по умолчанию

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

$diseases = get_posts( [
	'post_type'        => 'disease',
	'post_status'      => 'any',
	'suppress_filters' => TRUE,
	'numberposts'      => - 1
] );

foreach ( $diseases as $post ) {
	wp_set_object_terms( $post->ID, 'article', 'article-type' );
}