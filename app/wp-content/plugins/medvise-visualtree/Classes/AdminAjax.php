<?php


namespace Medvisement\Classes;


class AdminAjax {

	public function setup() {
		add_action( 'wp_ajax_vt_update_nodes', [ $this, 'update_nodes' ] );

		add_action( 'wp_ajax_vt_get_posts', [ $this, 'get_posts' ] );
	}

	public function update_nodes() {
		check_ajax_referer( 'vt-nonce', 'vt_nonce' );

		if ( ! wp_verify_nonce( $_POST['vt_nonce'], 'vt-nonce' ) ) {
			$this->ajax_response( array(
				'success' => false,
				'msg' => "Ошибка авторизации запроса!"
			) );
		}

		// Определяем тип древа
		$model_name = Helpers::getTreeModelNamespace( $_POST['type'] );

		if ( $model_name === NULL ) {
			$this->ajax_response( array(
				'success' => false,
				'msg' => "Неизвестный тип древа!"
			) );
		}

		$processed_nodes = [];

		foreach ($_POST['nodes'] as $node) {

			// Проверяем входные данные
			if ( empty( $node['name'] ) || ! isset( $node['parent'] ) || ! isset( $node['position'] ) ) {
				$this->ajax_response( array(
					'success' => false,
					'msg' => "Не заданы минимальные параметры для: " . print_r($node, true)
				) );
				return false;
			}

			// Удаление ноды
			if ( ! empty($node['flag']) && $node['flag'] == 'delete') {
				$delete_node = $model_name::find($node['id']);
				$descendants_and_self = $delete_node->getDescendantsAndSelf();
				$descendants_and_self->map->delete();
				continue;
			}
			// Удаление дочерей ноды
			if ( ! empty($node['flag']) && $node['flag'] == 'delete-children') {
				$parent_node = $model_name::find($node['id']);
				$descendants = $parent_node->getDescendants();
				$descendants->map->delete();
				continue;
			}

			// Новая нода или обновление
			if ( str_starts_with($node['id'], '_') ) {
				// Новая нода
				$temp_model_id = $node['id'];
				unset($node['id']);

				// Если родительский ид ноды начинается с _ - смотрим в уже обработанных реальный ид
				if ( str_starts_with($node['parent'], '_') ) {
					$node['parent'] = $processed_nodes[$node['parent']];
				}

				$created_model = $model_name::create($node);
				$processed_nodes[$temp_model_id] = $created_model->id;
			}
			else {

				$updated_model = $model_name::updateOrCreate(
					[
						'id' => $node['id']
					],
					[
						'name' => $node['name'],
						'parent' => (string) $node['parent'],  // именно строка для fancytree
						'post_id'  => $node['post_id'],
						'position' => $node['position'],
					]
				);

				$processed_nodes[$node['id']] = $updated_model->id;
			}

		}

		$this->ajax_response( array(
			'success' => true,
			'processed_nodes' => $processed_nodes
		) );
	}

	public function get_posts() {
		check_ajax_referer( 'vt-nonce', 'vt_nonce' );

		if ( ! wp_verify_nonce( $_POST['vt_nonce'], 'vt-nonce' ) ) {
			$this->ajax_response( array(
				'success' => false,
				'msg' => "Ошибка авторизации запроса!"
			) );
		}

		//Определяем тип поста
		$post_type = $_POST['type'];
		$s = $_POST['q'];

		$query = new \WP_Query(
			[
				'posts_per_page' => -1,
				's'              => $s,
				'post_type'      => $post_type,
				'post_status' => 'publish'
			]
		);
		$posts = $query->posts;

		$prepared_posts = [];
		foreach($posts as $post) {
			$prepared_posts[] = [
				'id' => $post->ID,
				'text' => $post->post_title
			];
		}

		$this->ajax_response([
			'success' => true,
			'posts' => $prepared_posts
		]);
	}

	public function ajax_response( $output ) {
		echo json_encode( $output );
		die();
	}

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}