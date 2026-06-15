<?php

function medvise_get_clinical_guidelines_specialties() {
	static $result = null;

	if ( null !== $result ) {
		return $result;
	}

	$result = [
		'total' => 0,
		'terms' => [],
	];

	$clinical_guidelines_term = get_term_by( 'slug', 'clinical-guidelines', 'article-type' );
	if ( ! $clinical_guidelines_term || is_wp_error( $clinical_guidelines_term ) ) {
		return $result;
	}

	global $wpdb;

	$total_posts = (int) $wpdb->get_var(
		$wpdb->prepare(
			"
			SELECT COUNT(DISTINCT posts.ID)
			FROM {$wpdb->posts} posts
			INNER JOIN {$wpdb->term_relationships} type_rel ON type_rel.object_id = posts.ID
			INNER JOIN {$wpdb->term_taxonomy} type_taxonomy ON type_taxonomy.term_taxonomy_id = type_rel.term_taxonomy_id
			WHERE posts.post_type = 'disease'
			  AND posts.post_status = 'publish'
			  AND type_taxonomy.taxonomy = 'article-type'
			  AND type_taxonomy.term_id = %d
			",
			$clinical_guidelines_term->term_id
		)
	);

	$specialty_counts = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT specialty_taxonomy.term_id AS specialty_id, COUNT(DISTINCT posts.ID) AS total
			FROM {$wpdb->posts} posts
			INNER JOIN {$wpdb->term_relationships} type_rel ON type_rel.object_id = posts.ID
			INNER JOIN {$wpdb->term_taxonomy} type_taxonomy ON type_taxonomy.term_taxonomy_id = type_rel.term_taxonomy_id
			INNER JOIN {$wpdb->term_relationships} specialty_rel ON specialty_rel.object_id = posts.ID
			INNER JOIN {$wpdb->term_taxonomy} specialty_taxonomy ON specialty_taxonomy.term_taxonomy_id = specialty_rel.term_taxonomy_id
			WHERE posts.post_type = 'disease'
			  AND posts.post_status = 'publish'
			  AND type_taxonomy.taxonomy = 'article-type'
			  AND type_taxonomy.term_id = %d
			  AND specialty_taxonomy.taxonomy = 'specialty'
			GROUP BY specialty_taxonomy.term_id
			",
			$clinical_guidelines_term->term_id
		)
	);

	$counts_map = [];
	foreach ( $specialty_counts as $specialty_count ) {
		$counts_map[ (int) $specialty_count->specialty_id ] = (int) $specialty_count->total;
	}

	$specialties = get_terms( [
		'taxonomy'   => 'specialty',
		'hide_empty' => false,
		'orderby'    => 'term_order',
		'order'      => 'ASC',
	] );

	if ( is_wp_error( $specialties ) ) {
		$specialties = [];
	}

	foreach ( $specialties as $specialty ) {
		$count = $counts_map[ $specialty->term_id ] ?? 0;
		if ( $count < 1 ) {
			continue;
		}

		$specialty->clinical_guidelines_count = $count;
		$result['terms'][] = $specialty;
	}

	$result['total'] = $total_posts;

	return $result;
}

function medvise_get_selected_clinical_guidelines_specialty() {
	$raw_specialty = isset( $_GET['cr_specialty'] ) ? sanitize_title( wp_unslash( $_GET['cr_specialty'] ) ) : '';
	if ( $raw_specialty === '' ) {
		return null;
	}

	$specialty = get_term_by( 'slug', $raw_specialty, 'specialty' );

	return ( $specialty && ! is_wp_error( $specialty ) ) ? $specialty : null;
}

function medvise_get_clinical_guidelines_search_term() {
	return isset( $_GET['cr_query'] ) ? sanitize_text_field( wp_unslash( $_GET['cr_query'] ) ) : '';
}

function medvise_get_clinical_guidelines_page_url( $args = [] ) {
	$url = get_permalink();
	$args = array_filter(
		$args,
		static function ( $value ) {
			return $value !== null && $value !== '';
		}
	);

	return empty( $args ) ? $url : add_query_arg( $args, $url );
}

function medvise_get_clinical_guidelines_query( $selected_specialty = null, $search_term = '', $page = 1 ) {
	$args = [
		'post_type'      => 'disease',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'paged'          => max( 1, (int) $page ),
		'tax_query'      => [
			[
				'taxonomy' => 'article-type',
				'field'    => 'slug',
				'terms'    => [ 'clinical-guidelines' ],
			],
		],
	];

	if ( $selected_specialty ) {
		$args['tax_query'][] = [
			'taxonomy' => 'specialty',
			'field'    => 'term_id',
			'terms'    => [ $selected_specialty->term_id ],
		];
	}

	if ( $search_term !== '' ) {
		$args['s'] = $search_term;
		$args['ep_integrate'] = true;
	} else {
		$args['orderby'] = [
			'date'     => 'DESC',
			'modified' => 'DESC',
		];
	}

	return new WP_Query( $args );
}

function medvise_get_clinical_guideline_year( $post_id ) {
	$year = trim( (string) get_post_meta( $post_id, 'med_clinical_guideline_year', true ) );
	if ( $year !== '' ) {
		return $year;
	}

	$placement_date = trim( (string) get_post_meta( $post_id, 'med_clinical_guideline_placement_date', true ) );
	if ( preg_match( '/^\d{4}/', $placement_date, $matches ) ) {
		return $matches[0];
	}

	return get_the_date( 'Y', $post_id );
}

function medvise_get_clinical_guideline_status_badge( $post_id ) {
	$status = trim( (string) get_post_meta( $post_id, 'med_clinical_guideline_application_status', true ) );
	if ( $status === '' ) {
		return [];
	}

	$normalized = mb_strtolower( $status );
	if (
		str_contains( $normalized, 'не действ' )
		|| str_contains( $normalized, 'inactive' )
		|| str_contains( $normalized, 'утрат' )
		|| str_contains( $normalized, 'архив' )
		|| str_contains( $normalized, 'отмен' )
	) {
		return [
			'label' => 'Не действует',
			'class' => 'is-inactive',
		];
	}

	if (
		str_contains( $normalized, 'действ' )
		|| str_contains( $normalized, 'active' )
		|| str_contains( $normalized, 'актуал' )
	) {
		return [
			'label' => 'Действующая',
			'class' => 'is-active',
		];
	}

	return [
		'label' => $status,
		'class' => 'is-neutral',
	];
}

function medvise_get_clinical_guideline_age_labels( $post_id ) {
	$age_terms = get_the_terms( $post_id, 'age' );
	if ( empty( $age_terms ) || is_wp_error( $age_terms ) ) {
		return [];
	}

	$labels = [];
	foreach ( $age_terms as $age_term ) {
		$labels[] = $age_term->name;
	}

	return array_values( array_unique( $labels ) );
}

function medvise_get_clinical_guideline_pdf_url( $post_id ) {
	$pdf_url = trim( (string) get_post_meta( $post_id, 'med_clinical_guideline_pdf_url', true ) );
	if ( $pdf_url !== '' ) {
		return esc_url( $pdf_url );
	}

	$files = carbon_get_post_meta( $post_id, 'med_article_files' );
	if ( empty( $files ) || ! is_array( $files ) ) {
		return '';
	}

	foreach ( $files as $file_item ) {
		$file_id = $file_item['file'] ?? null;
		if ( is_numeric( $file_id ) ) {
			$file_url = wp_get_attachment_url( (int) $file_id );
			if ( $file_url ) {
				return esc_url( $file_url );
			}
		}

		if ( ! empty( $file_item['url'] ) ) {
			return esc_url( $file_item['url'] );
		}
	}

	return '';
}
