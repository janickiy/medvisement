<?php

function medvise_save_post_remove_nbsp( $data, $postarr, $unsanitized_postarr, $update ) {

	// Заменяем данные только в заболеваниях и препаратах
	if ( isset( $postarr['post_type'] ) && ! in_array( $postarr['post_type'], array( 'disease', 'substance' ) ) ) {
		return $data;
	}

	// Заменяем nbsp на пробел
	$data['post_content'] = preg_replace( "/[\x{A0}]/miu", ' ', $data['post_content'] );
	$data['post_content'] = str_replace( "&nbsp;", ' ', $data['post_content'] );

	// Удаляем двойные пробелы
	$data['post_content'] = str_replace( '/([а-яa-z0-9]+) {2,}([а-яa-z0-9]+)/miu', '${1} ${2}', $data['post_content'] );

	return $data;
}

add_filter( 'wp_insert_post_data', 'medvise_save_post_remove_nbsp', 10, 4 );

function medvise_force_disease_article_type( $post_id, $post, $update ) {

	// Заболевания по умолчанию "Статьи"
	if ( 'disease' !== $post->post_type ) {
		return;
	}

	// Если был установлен тип статьи - пропускаем
	$article_type = $_POST['tax_input']['article-type'];
	if ( ! empty( $article_type ) ) {
		return;
	}

	wp_set_object_terms( $post->ID, 'article', 'article-type' );
}

add_action( 'save_post', 'medvise_force_disease_article_type', 10, 3 );