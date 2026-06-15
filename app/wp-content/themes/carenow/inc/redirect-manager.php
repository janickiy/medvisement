<?php
/*
 * Для безопасности - первым делом в template_redirect проверяем значение move_to и обрезаем при необходимости
 */
function medvise_redirect_manager() {

	if ( empty( $_REQUEST['move_to'] ) ) {
		return;
	}

	$move_to_url = urldecode($_REQUEST['move_to']);

	// Указана левая ссылка - обрезаем
	if ( ! wp_http_validate_url( $move_to_url ) ) {
		$clean_url = remove_query_arg( 'move_to' );
		wp_safe_redirect( $clean_url );
		exit;
	}
}

add_action( 'template_redirect', 'medvise_redirect_manager', 1 );