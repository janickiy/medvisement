<?php


namespace MedviseUserModules;

use MedviseSubscriptions\Subscriber\Subscriber;

class Note {

	private static $allowed_posts = [ 'substance', 'disease' ];

	public function setup() {
		add_action( 'wp_ajax_medvise_um_notes', [ $this, 'processAjax' ] );

		add_action( 'the_content', [ $this, 'the_content' ], 10, 1);
	}

	public function the_content($content) {

		global $post;
		$current_user = wp_get_current_user();

		if ( ! is_single() || ! in_array( $post->post_type, self::$allowed_posts) || ! Subscriber::hasSubscription() ) {
			return $content;
		}

		// Смотрим, есть ли заметка уже
		$note = self::get( $current_user->ID, $post->ID);

		if ( $note['replace_original'] ) {
			return $note['content'];
		}

		return $content;
	}

	public function processAjax() {

		check_ajax_referer( 'um-nonce', 'nonce' );

		$data = [];
		parse_str( file_get_contents('php://input'), $data);

		if ( ! wp_verify_nonce( $data['nonce'], 'um-nonce' ) ) {
			$this->ajax_response( array(
				'success' => FALSE,
				'msg'     => "Ошибка авторизации запроса!"
			) );
		}

		$current_user = wp_get_current_user();

		if ( 'save' === $data['command'] ) {

			$note = self::save( $current_user->ID, $data['post_id'], [
				'title' => 'Заметка',
				'content' => $data['content'],
				'replace_original' => (int) $data['replace_original']
			] );

			if ( is_array( $note ) ) {
				$this->ajax_response( array(
					'success' => TRUE
				) );
			}
			else {
				$this->ajax_response( array(
					'success' => false,
					'msg' => 'Ошибка запроса сохранения заметки'
				) );
			}
		}
		elseif ( 'delete' === $data['command'] ) {
			$this::delete( $current_user->ID, $data['post_id'] );

			$this->ajax_response( array(
				'success' => TRUE
			) );
		}

		$this->ajax_response( array(
			'success' => false,
			'msg' => "Неизвестная команда {$data['command']}"
		) );
	}

	public static function get( $user_id, $post_id ) {

		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}medvise_user_notes` WHERE user_id=%d AND post_id=%d ORDER BY `id` DESC LIMIT 1;", [
			$user_id,
			$post_id
		] );
		$note  = $wpdb->get_row( $query, ARRAY_A );

		$content_placeholder = 'Используется для внесения собственных заметок/выделения содержимого оригинальной статьи <br><br>' .
		                       '- Вы всегда можете вставить актуальную версию статьи нажав на соответствующую кнопку <br>' .
		                       '- Чтобы отображать собственные заметки вместо статьи - поставьте галочку у "Заменять оригинал" и перезагрузите страницу<br>';

		$data = [
			'title' => empty($note['title']) ? '' : $note['title'],
			'content' => empty($note['content']) ? $content_placeholder : $note['content'],
			'replace_original' => empty($note['replace_original']) ? 0 : 1,
		];

		return $data;
	}

	public static function save( $user_id, $post_id, $data ) {

		$post = get_post($post_id);

		if ( ! in_array( $post->post_type, self::$allowed_posts) || ! Subscriber::hasSubscription() ) {
			return false;
		}

		global $wpdb;

		// Существует ли запись
		$query = "SELECT id FROM `{$wpdb->prefix}medvise_user_notes` WHERE user_id=%d AND post_id=%d ORDER BY `id` DESC LIMIT 1 ;";
		$note_id = $wpdb->get_var( $wpdb->prepare( $query, [
			$user_id,
			$post_id
		] ) );

		if ( empty( $note_id ) ) {
			$query = "INSERT INTO `{$wpdb->prefix}medvise_user_notes` (user_id, post_id, title, content, replace_original) " .
			         "VALUES (%d, %d, %s, %s, %d);";

			$wpdb->query( $wpdb->prepare( $query, [
				$user_id,
				$post_id,
				$data['title'],
				$data['content'],
				$data['replace_original']
			] ) );
		} else {
			$query = "UPDATE `{$wpdb->prefix}medvise_user_notes` SET title=%s, content=%s, replace_original=%d " .
			         "WHERE user_id=%d AND post_id=%d;";

			$wpdb->query( $wpdb->prepare( $query, [
				$data['title'],
				$data['content'],
				$data['replace_original'],
				$user_id,
				$post_id
			] ) );
		}

		return [
			'title' => $data['title'],
			'content' => $data['content']
		];
	}

	public static function delete( $user_id, $post_id ) {
		global $wpdb;

		$query = $wpdb->prepare("DELETE FROM `{$wpdb->prefix}medvise_user_notes` WHERE user_id=%d AND post_id=%d;", [
			$user_id,
			$post_id
		]);

		$wpdb->query($query);
	}

	public static function getInstance() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	private function ajax_response( $output ) {
		echo json_encode( $output );
		die();
	}
}