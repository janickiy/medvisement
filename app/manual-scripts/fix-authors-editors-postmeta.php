<?php
// Запуск: wp eval-file manual-scripts/fix-authors-editors-postmeta.php
// Проставляем авторов в мета поле

$diseases = get_posts( [
	'post_type'        => 'disease',
	'post_status'      => 'any',
	'numberposts'      => - 1,
	'suppress_filters' => TRUE
] );

$substances = get_posts( [
	'post_type'        => 'substance',
	'post_status'      => 'any',
	'numberposts'      => - 1,
	'suppress_filters' => TRUE
] );

foreach ( $diseases as $disease ) {

	$user = get_userdata( $disease->post_author );

	$post_authors = carbon_get_the_post_meta( 'med_article_authors' );

	$post_editors = carbon_get_the_post_meta( 'med_article_editors' );

	// В Автора всех ставим, в редактры только если есть права
	if ( count( $post_authors ) === 1 && in_array( 0, $post_authors ) && ! in_array( 'administrator', $user->roles ) ) {
		carbon_set_post_meta( $disease->ID, 'med_article_authors', [ $disease->post_author ] );
	}

	if ( count( $post_editors ) === 1 && in_array( 0, $post_editors ) && in_array( 'editor', $user->roles ) ) {
		carbon_set_post_meta( $disease->ID, 'med_article_editors', [ $disease->post_author ] );
	}

}

foreach ( $substances as $substance ) {

	$user = get_userdata( $substance->post_author );

	$post_authors = carbon_get_the_post_meta( 'med_article_authors' );

	$post_editors = carbon_get_the_post_meta( 'med_article_editors' );

	if ( count( $post_authors ) === 1 && in_array( 0, $post_authors ) ) {
		carbon_set_post_meta( $substance->ID, 'med_article_authors', [ $substance->post_author ] );
	}

	if ( count( $post_editors ) === 1 && in_array( 0, $post_editors ) && in_array( 'editor', $user->roles ) ) {
		carbon_set_post_meta( $substance->ID, 'med_article_editors', [ $substance->post_author ] );
	}

}