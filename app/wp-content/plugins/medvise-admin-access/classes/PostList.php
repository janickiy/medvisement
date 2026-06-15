<?php


namespace MedvisementAdminAccess;


class PostList {

	public static function factory() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {
		add_filter( 'post_row_actions', [ $this, 'post_row_actions' ], 10, 3 );

		add_action( 'pre_get_posts', [ $this, 'restrict_posts' ] );

		add_filter( 'wp_count_posts', [ $this, 'wp_count_posts' ], 10, 3 );
	}

	// Отключаем кнопку удаления навсегда
	public function post_row_actions( $actions, $post ) {

		if ( Helpers::is_editor_or_author() && $post->post_status === 'trash' ) {
			unset( $actions['delete'] );
		}

		if ( Helpers::is_editor_or_author() ) {
			unset( $actions['inline hide-if-no-js'] );
		}

		return $actions;
	}

	// Автор может видеть только свои записи START
	public function restrict_posts( $query ) {
		global $current_user;
		global $pagenow;

		if ( ! is_admin() ) {
			return $query;
		}

		if ( 'edit.php' == $pagenow && Helpers::is_author() ) {
			$query->set( 'author', $current_user->ID );
		}

		if ( 'edit.php' == $pagenow && Helpers::is_editor() ) {

			// Редакторы могут смотреть только по своей специальности записи
			$med_specialty            = get_user_meta( $current_user->ID, 'med_specialty', TRUE );
			$editor_allowed_specialty = [];

			foreach ( $med_specialty as $k => $item ) {
				if ( ! empty( $item ) ) {
					$editor_allowed_specialty[] = $k;
				}
			}

			/*
			 * а получение всех статей + где есть разрешенная специальность и получение только своих статей - два разных запроса
			 */
			$taxquery = [
				'relation' => 'OR',
				[
					'taxonomy' => 'specialty',
					'field'    => 'term_id',
					'terms'    => $editor_allowed_specialty,
					'operator' => 'IN'
				],
				[
					'taxonomy' => 'specialty',
					'field'    => 'term_id',
					'operator' => 'NOT EXISTS'
				]
			];
			$query->set( 'tax_query', $taxquery );
		}

	}

	// Исправляем счетчик постов для авторов
	function wp_count_posts( $counts, $type, $perm ) {

		if ( in_array( $type, [ 'substance', 'disease' ] ) && Helpers::is_author() ) {
			global $wpdb;

			$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";

			if ( 'readable' === $perm && is_user_logged_in() && Helpers::is_author() ) {
				$post_type_object = get_post_type_object( $type );
				if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
					$query .= $wpdb->prepare(
						" AND post_author = %d",
						get_current_user_id()
					);
				}
			}

			if ( Helpers::is_editor() ) {
				//todo
			}

			$query .= ' GROUP BY post_status';

			$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
			$counts  = array_fill_keys( get_post_stati(), 0 );

			foreach ( $results as $row ) {
				$counts[ $row['post_status'] ] = $row['num_posts'];
			}

			$counts    = (object) $counts;
			$cache_key = _count_posts_cache_key( $type, $perm );
			wp_cache_set( $cache_key, $counts, 'counts' );

		}

		return $counts;
	}

}