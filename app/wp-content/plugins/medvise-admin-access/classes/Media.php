<?php


namespace MedvisementAdminAccess;


class Media {
	public static function factory() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {
		add_action( 'delete_attachment', [ $this, 'delete_attachment' ] );

		add_filter( 'media_row_actions', [ $this, 'remove_media_delete_link_in_list_view' ], 10, 3 );

		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'remove_media_delete_link_in_grid_view' ], 10, 3 );
	}

	// Запрет на удаление медиа в бэкенде
	public function delete_attachment( $attachment_id ) {

		if ( ! Helpers::is_editor_or_author() ) {
			return TRUE;
		}

		$attachment = get_post( $attachment_id );

		global $current_user;

		// Авторам и редакторам разрешаем удалять только их медиа
		if ( $current_user->ID != $attachment->post_author ) {
			wp_die( "Вы не можете удалить данный файл!" );
		}

		return TRUE;
	}

	// Отображение медиа в режиме строк
	public function remove_media_delete_link_in_list_view( $actions, $post, $detached ) {

		if ( ! Helpers::is_editor_or_author() || $post->post_type != 'attachment' ) {
			return $actions;
		}

		global $current_user;

		// Авторам и редакторам разрешаем удалять только их медиа
		if ( $current_user->ID != $post->post_author ) {
			unset( $actions['delete'] );
		}

		return $actions;
	}

	// Отображение медиа в режиме карточек
	public function remove_media_delete_link_in_grid_view( $response, $attachment, $meta ) {

		if ( ! Helpers::is_editor_or_author() ) {
			return $response;
		}

		global $current_user;

		// Авторам и редакторам разрешаем удалять только их медиа
		if ( $current_user->ID != $attachment->post_author ) {
			$response['nonces']['delete'] = FALSE;
		}

		return $response;
	}
}