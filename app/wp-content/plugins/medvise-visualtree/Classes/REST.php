<?php


namespace Medvisement\Classes;


class REST {

	public function setup() {

		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );

	}

	public function rest_api_init() {
		register_rest_route( 'medvise-vt/v1', '/tree/(?P<type>[a-z_]+)', [
			'methods'  => 'GET',
			'callback' => [ $this, 'generate_tree_json' ],
			'permission_callback' => '__return_true'
		] );

		register_rest_route( 'medvise-vt/v1', '/tree/plain/(?P<post_id>[0-9]+)', [
			'methods'  => 'GET',
			'callback' => [ $this, 'post_tree_position_json' ],
			'permission_callback' => '__return_true'
		] );
	}

	public function generate_tree_json( $data ) {

		$type = $data->get_param( 'type' );

		$model_name = Helpers::getTreeModelNamespace( $type );

		if ($model_name === NULL) {
			return 'Неизвестный тип древа!';
		}

		if ( empty( $data->get_param('node') ) ) {
			$tree_nodes = $model_name::all();
		}
		else {
			$node_id = (int) $data->get_param('node') ;
			$node = $model_name::find($node_id);
			$tree_nodes = $node->getDescendants();
		}

		$tree_nodes->each( function ( $item, $key ) {
			$item->title    = $item->name;
			$item->key      = (string) $item->id;
			$item->parent   = (string) $item->parent;
			$item->url = empty($item->post_id) ? '' : get_permalink($item->post_id);
			$item->expanded = FALSE;
			// Для плагина пользовательских инструкций
			$item->tour_classes = '';
			// Для стилизации основной статьи
			$item->article_main = FALSE;
		} );

		foreach ( $tree_nodes as $key => $value ) {
			unset( $tree_nodes[ $key ]['name'] );
		}

		$tree_nodes = $tree_nodes->sortBy( 'position' )->toTree();

		foreach ( $tree_nodes as $k => $node ) {
			Helpers::walkTroughNodeArray( $node, function ( $node ) {

				if ( isset($node['children']) && ! empty($node['url']) ) {
					// Если у ноды есть дочерние - дублируем ее саму как дочернюю первым элементом
					array_unshift($node['children'] , [
						'title' => $node['title'],
						'key' => $node['key'] . "0",
						'parent' => $node['key'],
						'url' => $node['url'],
						'expanded' => $node['expanded'],
						'tour_classes' => $node['tour_classes'],
						'article_main' => TRUE
					] );

					// Ссылку с самой ноды снимаем
					$node['url'] = '';
				}

				return $node;
			} );

			$tree_nodes[$k] = $node;
		}

		return rest_ensure_response( $tree_nodes );
	}

	public function post_tree_position_json( $data ) {
		$post_id = $data->get_param( 'post_id' );

		$post = get_post( $post_id );

		// Пост не найден или не заболевание/препарат
		if ( empty( $post ) || ! in_array( $post->post_type, [ 'disease', 'substance', 'custom_quiz' ] ) ) {
			return rest_ensure_response( [
				'status'     => 'error',
				'plain_tree' => ''
			] );
		}

		$model_name = Helpers::getTreeModelNamespace( $post->post_type );

		if ( $model_name === NULL ) {
			// В древе не найден
			return rest_ensure_response( [
				'status'     => 'ok',
				'plain_tree' => ''
			] );
		}

		$node = $model_name::where( 'post_id', $post_id )->first();

		if ( ! empty( $node ) ) {
			$pieces = $this->treeToPlain( $node->getAncestorsAndSelf()->toTree() );
			return rest_ensure_response( [
				'status'     => 'ok',
				'plain_tree' => implode( " > ", $pieces )
			] );
		} else {
			// В древе не найден
			return rest_ensure_response( [
				'status'     => 'ok',
				'plain_tree' => ''
			] );
		}

	}

	private function treeToPlain( $tree, &$count = 0) {
		$pieces = [];

		$pieces[$count] = $tree[0]['name'];
		$count++;

		if ( isset( $tree[0]['children'] ) ) {
			$pieces += $this->treeToPlain( $tree[0]['children'], $count );
		}

		return $pieces;
	}

	public static function getInstance() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

}