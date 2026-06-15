<?php


namespace MedvisementAdminAccess;


class Taxonomy {

	public static function factory() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {
		add_filter( 'get_terms', [ $this, 'get_terms_limit_access' ], 10, 4 );
	}

	public function get_terms_limit_access( $terms, $taxonomy, $query_vars, $term_query ) {

		if ( ! is_admin() || ! Helpers::is_editor_or_author() || $taxonomy[0] != 'specialty' ) {
			return $terms;
		}

		global $current_user;

		$med_specialty          = get_user_meta( $current_user->ID, 'med_specialty', TRUE );
		$user_allowed_specialty = [];

		foreach ( $med_specialty as $k => $item ) {
			if ( ! empty( $item ) ) {
				$user_allowed_specialty[] = $k;
			}
		}

		// Снимаем специальности, где нет юзера
		foreach ( $terms as $k => $term ) {
			// Иногда просто ID терма, иногда WP_Term
			if ( is_object( $term ) ) {
				if ( ! in_array( $term->term_id, $user_allowed_specialty ) ) {
					unset( $terms[ $k ] );
				}
			} else {
				if ( ! in_array( $term, $user_allowed_specialty ) ) {
					unset( $terms[ $k ] );
				}
			}
		}

		return $terms;
	}
}