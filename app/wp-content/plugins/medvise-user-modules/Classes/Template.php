<?php


namespace MedviseUserModules;

use MedviseSubscriptions\Subscriber\Subscriber;

class Template {

	private static $allowed_posts = [ 'substance', 'disease' ];

	public function setup() {
		add_action( 'wp_ajax_medvise_um_template', [ $this, 'processAjax' ] );
	}

	public function processAjax() {

		check_ajax_referer( 'um-nonce', 'nonce' );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um-nonce' ) ) {
			$this->ajax_response( array(
				'success' => FALSE,
				'msg'     => "Ошибка авторизации запроса!"
			) );
		}

		$current_user = wp_get_current_user();

		if ( 'save' === $_POST['command'] ) {

			$template_id = self::save( $current_user->ID, $_POST['post_id'], $_POST['template_id'], [
				'title' => $_POST['title'],
				'content' => $_POST['content']
			] );

			if ( is_numeric( $template_id ) ) {
				$this->ajax_response( array(
					'success' => TRUE,
					'template_id' => $template_id
				) );
			}
			else {
				$this->ajax_response( array(
					'success' => false,
					'msg' => 'Ошибка запроса сохранения шаблона'
				) );
			}

		}
		elseif ( 'delete' === $_POST['command'] ) {
			$this::delete( $current_user->ID, $_POST['template_id'] );

			$this->ajax_response( array(
				'success' => TRUE
			) );
		}

		$this->ajax_response( array(
			'success' => FALSE,
			'msg'     => "Неизвестная команда {$_POST['command']}"
		) );
	}

	public static function get( $user_id, $post_id ) {

		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}medvise_user_templates` WHERE user_id=%d AND post_id=%d ORDER BY `id` ASC;", [
			$user_id,
			$post_id
		] );
		$templates  = $wpdb->get_results( $query, ARRAY_A );

		$output = [];

		foreach ( $templates as $template ) {
			$output[ $template['id'] ] = [
				'id'      => $template['id'],
				'title'   => $template['title'],
				'content' => $template['content']
			];
		}

		// Костыль, чтобы всегда был объект
		if ( empty( $output ) ) {
			$output = new \stdClass();
		}

		return $output;
	}

	public static function save( $user_id, $post_id, $template_id = '', $data ) {

		$post = get_post($post_id);

		if ( ! in_array( $post->post_type, self::$allowed_posts) ) {
			return false;
		}

		$templates_count = self::countUserPostTemplates( $user_id, $post_id );

		if ( $templates_count >= self::getUserLimit() ) {
			return false;
		}

		global $wpdb;

		if ( empty($template_id) ) {
			$query = "INSERT INTO `{$wpdb->prefix}medvise_user_templates` (user_id, post_id, title, content) " .
			         "VALUES (%d, %d, %s, %s);";

			$wpdb->query( $wpdb->prepare( $query, [
				$user_id,
				$post_id,
				$data['title'],
				$data['content']
			] ) );

			$template_id = $wpdb->insert_id;
		}
		else {
			$query = "UPDATE `{$wpdb->prefix}medvise_user_templates` SET title=%s, content=%s " .
			         "WHERE id=%d AND user_id=%d AND post_id=%d;";

			$wpdb->query( $wpdb->prepare( $query, [
				$data['title'],
				$data['content'],
				$template_id,
				$user_id,
				$post_id
			] ) );
		}

		return $template_id;
	}

	public static function delete( $user_id, $template_id ) {
		global $wpdb;

		$query = $wpdb->prepare("DELETE FROM `{$wpdb->prefix}medvise_user_templates` WHERE user_id=%d AND id=%d;", [
			$user_id,
			$template_id
		]);

		$wpdb->query($query);
	}

	public static function countUserPostTemplates($user_id, $post_id) {

		global $wpdb;

		$query = "SELECT COUNT(id) AS template_count FROM `{$wpdb->prefix}medvise_user_templates` WHERE user_id=%d AND post_id=%d;";

		return $wpdb->get_var( $wpdb->prepare( $query, [
			$user_id,
			$post_id
		] ) );
	}

	public static function getUserLimit() {

		if ( Subscriber::hasSubscription() ) {
			return 5;
		}

		return 1;
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