<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Medvise_Clinical_Recommendations {
	const ARCHIVE_SLUG            = 'clinical-recommendations';
	const ARCHIVE_QUERY_VAR       = 'medvise_clinical_recommendations';
	const SINGLE_QUERY_VAR        = 'medvise_clinical_recommendation';
	const PDF_QUERY_VAR           = 'medvise_clinical_recommendation_pdf';
	const REWRITE_VERSION_OPTION  = 'medvise_clinical_recommendations_rewrite_version';
	const REWRITE_VERSION         = '2.0.0';
	const DEFAULT_PER_PAGE        = 15;
	const REST_NAMESPACE          = 'medvise/v1';
	const REST_SUGGEST_ROUTE      = '/clinical-recommendations/suggest';
	const PARSER_ROOT             = '/var/www/parser';
	const POST_TYPE               = 'disease';
	const ARTICLE_TYPE_TAXONOMY   = 'article-type';
	const ARTICLE_TYPE_SLUG       = 'clinical-guidelines';
	const SPECIALTY_TAXONOMY      = 'specialty';
	const AGE_TAXONOMY            = 'age';
	const SOURCE_ID_META_KEY      = 'source_id';
	const CODE_VERSION_META_KEY   = 'medvise_clinrec_code_version';
	const PUBLISH_DATE_META_KEY   = 'medvise_clinrec_publish_date';
	const PDF_ATTACHMENT_META_KEY = 'medvise_clinrec_pdf_attachment_id';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<string, array|null>
	 */
	private $recommendation_cache = [];

	/**
	 * @var array<string, array>
	 */
	private $archive_context_cache = [];

	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_routes' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_filter( 'template_include', [ $this, 'template_include' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_pdf' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'document_title_parts', [ $this, 'filter_document_title' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_filter( 'body_class', [ $this, 'body_class' ] );
	}

	public function register_routes() {
		add_rewrite_rule(
			'^' . self::ARCHIVE_SLUG . '/?$',
			'index.php?' . self::ARCHIVE_QUERY_VAR . '=1',
			'top'
		);

		add_rewrite_rule(
			'^' . self::ARCHIVE_SLUG . '/pdf/([^/]+)/?$',
			'index.php?' . self::PDF_QUERY_VAR . '=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . self::ARCHIVE_SLUG . '/([^/]+)/?$',
			'index.php?' . self::SINGLE_QUERY_VAR . '=$matches[1]',
			'top'
		);

		if ( self::REWRITE_VERSION !== get_option( self::REWRITE_VERSION_OPTION ) ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false );
		}
	}

	public function register_query_vars( $vars ) {
		$vars[] = self::ARCHIVE_QUERY_VAR;
		$vars[] = self::SINGLE_QUERY_VAR;
		$vars[] = self::PDF_QUERY_VAR;

		return $vars;
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_SUGGEST_ROUTE,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_suggestions' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function body_class( $classes ) {
		if ( $this->is_archive_request() ) {
			$classes[] = 'medvise-clinical-recommendations-archive';
		}

		if ( $this->is_single_request() ) {
			$classes[] = 'medvise-clinical-recommendations-single';
		}

		return $classes;
	}

	public function enqueue_assets() {
		if ( ! $this->is_archive_request() && ! $this->is_single_request() ) {
			return;
		}

		$style_path = MEDVISE_CLINICAL_RECOMMENDATIONS_PATH . '/assets/clinical-recommendations.css';
		wp_enqueue_style(
			'medvise-clinical-recommendations',
			MEDVISE_CLINICAL_RECOMMENDATIONS_URL . '/assets/clinical-recommendations.css',
			[],
			file_exists( $style_path ) ? (string) filemtime( $style_path ) : self::REWRITE_VERSION
		);

		if ( $this->is_archive_request() ) {
			$script_path = MEDVISE_CLINICAL_RECOMMENDATIONS_PATH . '/assets/clinical-recommendations.js';
			wp_enqueue_script(
				'medvise-clinical-recommendations',
				MEDVISE_CLINICAL_RECOMMENDATIONS_URL . '/assets/clinical-recommendations.js',
				[],
				file_exists( $script_path ) ? (string) filemtime( $script_path ) : self::REWRITE_VERSION,
				true
			);

			wp_localize_script(
				'medvise-clinical-recommendations',
				'MedviseClinicalRecommendations',
				[
					'archiveUrl' => $this->get_archive_url(),
				]
			);
		}
	}

	public function template_include( $template ) {
		if ( $this->is_archive_request() ) {
			return MEDVISE_CLINICAL_RECOMMENDATIONS_PATH . '/templates/archive.php';
		}

		if ( $this->is_single_request() ) {
			if ( ! $this->get_single_context() ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );

				return get_404_template();
			}

			return MEDVISE_CLINICAL_RECOMMENDATIONS_PATH . '/templates/single.php';
		}

		return $template;
	}

	public function maybe_serve_pdf() {
		if ( ! $this->is_pdf_request() ) {
			return;
		}

		$code_version = $this->get_requested_code_version( self::PDF_QUERY_VAR );
		if ( '' === $code_version ) {
			$this->set_404();
		}

		$local_file = $this->get_local_pdf_path( $code_version );

		if ( $local_file && is_readable( $local_file ) ) {
			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: inline; filename="' . rawurlencode( basename( $local_file ) ) . '"' );
			header( 'Content-Length: ' . (string) filesize( $local_file ) );
			readfile( $local_file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteDataFile_get_contents-unknown,WordPress.WP.AlternativeFunctions.file_system_read_readfile
			exit;
		}

		wp_safe_redirect( $this->get_remote_pdf_url( $code_version ) );
		exit;
	}

	public function rest_suggestions( WP_REST_Request $request ) {
		$search = trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) );
		if ( mb_strlen( $search ) < 2 ) {
			return rest_ensure_response(
				[
					'items'       => [],
					'results_url' => $this->get_archive_url(),
				]
			);
		}

		$specialty = $this->get_specialty_from_value( sanitize_text_field( (string) $request->get_param( 'specialty' ) ) );
		$items     = $this->get_recommendations( $search, $specialty ? (int) $specialty->term_id : 0, 1, 6 );

		$suggestions = array_map(
			function ( $item ) {
				return [
					'title'        => $item['name'],
					'url'          => $item['permalink'],
					'pdf_url'      => $item['pdf_url'],
					'publish_year' => $item['publish_year'],
					'age_category' => $item['age_category'],
					'excerpt'      => $item['excerpt'],
				];
			},
			$items
		);

		return rest_ensure_response(
			[
				'items'       => $suggestions,
				'results_url' => $this->get_archive_url(
					array_filter(
						[
							's'            => $search,
							'cr_specialty' => $specialty ? $specialty->slug : '',
						]
					)
				),
			]
		);
	}

	public function filter_document_title( $title_parts ) {
		if ( $this->is_archive_request() ) {
			$title_parts['title'] = 'Клинические рекомендации';
		}

		if ( $this->is_single_request() ) {
			$context = $this->get_single_context();
			if ( ! empty( $context['recommendation']['name'] ) ) {
				$title_parts['title'] = $context['recommendation']['name'];
			}
		}

		return $title_parts;
	}

	public function is_archive_request() {
		return '1' === (string) get_query_var( self::ARCHIVE_QUERY_VAR );
	}

	public function is_single_request() {
		return '' !== $this->get_requested_code_version( self::SINGLE_QUERY_VAR );
	}

	public function is_pdf_request() {
		return '' !== $this->get_requested_code_version( self::PDF_QUERY_VAR );
	}

	public function get_archive_context() {
		$search             = $this->get_search_term();
		$selected_specialty = $this->get_selected_specialty();
		$page_num           = $this->get_page_num();
		$cache_key          = md5( wp_json_encode( [ $search, $selected_specialty ? $selected_specialty->term_id : 0, $page_num ] ) );

		if ( isset( $this->archive_context_cache[ $cache_key ] ) ) {
			return $this->archive_context_cache[ $cache_key ];
		}

		$total_results = $this->count_recommendations( $search, $selected_specialty ? (int) $selected_specialty->term_id : 0 );
		$items         = $this->get_recommendations( $search, $selected_specialty ? (int) $selected_specialty->term_id : 0, $page_num, self::DEFAULT_PER_PAGE );
		$specialties   = $this->get_sidebar_specialties( $search, $selected_specialty ? (int) $selected_specialty->term_id : 0 );
		$all_total     = array_sum( wp_list_pluck( $specialties, 'count' ) );
		$total_pages   = max( 1, (int) ceil( $total_results / self::DEFAULT_PER_PAGE ) );

		$context = [
			'search'              => $search,
			'selected_specialty'  => $selected_specialty,
			'specialties'         => $specialties,
			'items'               => $items,
			'total_results'       => $total_results,
			'all_total'           => $all_total,
			'page_num'            => $page_num,
			'total_pages'         => $total_pages,
			'archive_url'         => $this->get_archive_url(),
			'pagination'          => $this->get_pagination_html( $page_num, $total_pages, $search, $selected_specialty ? $selected_specialty->slug : '' ),
			'heading'             => $this->build_archive_heading( $selected_specialty, $search ),
			'subheading'          => $this->build_archive_subheading( $total_results, $selected_specialty, $search ),
			'all_specialties_url' => $this->get_archive_url(
				array_filter(
					[
						's' => $search,
					]
				)
			),
		];

		$this->archive_context_cache[ $cache_key ] = $context;

		return $context;
	}

	public function get_single_context() {
		$code_version = $this->get_requested_code_version( self::SINGLE_QUERY_VAR );
		if ( '' === $code_version ) {
			return null;
		}

		$recommendation = $this->get_recommendation_by_code_version( $code_version );
		if ( empty( $recommendation ) ) {
			return null;
		}

		return [
			'recommendation' => $recommendation,
			'archive_url'    => $this->get_archive_url(),
		];
	}

	public function get_archive_url( $args = [] ) {
		return add_query_arg( $args, home_url( '/' . self::ARCHIVE_SLUG . '/' ) );
	}

	public function get_single_url( $code_version ) {
		return home_url( '/' . self::ARCHIVE_SLUG . '/' . rawurlencode( $code_version ) . '/' );
	}

	public function get_pdf_url( $code_version ) {
		return home_url( '/' . self::ARCHIVE_SLUG . '/pdf/' . rawurlencode( $code_version ) . '/' );
	}

	public function get_search_term() {
		if ( isset( $_GET['s'] ) ) {
			return sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		return isset( $_GET['cr_search'] ) ? sanitize_text_field( wp_unslash( $_GET['cr_search'] ) ) : '';
	}

	public function format_publish_date( $publish_date ) {
		$timestamp = strtotime( (string) $publish_date );
		if ( ! $timestamp ) {
			return '—';
		}

		return wp_date( 'd.m.Y', $timestamp );
	}

	public function get_recommendation_excerpt( $html, $limit = 190 ) {
		$text = wp_strip_all_tags( html_entity_decode( (string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' === $text ) {
			return '';
		}

		return wp_html_excerpt( $text, $limit, '...' );
	}

	private function get_page_num() {
		return isset( $_GET['cr_page_num'] ) ? max( 1, absint( $_GET['cr_page_num'] ) ) : 1;
	}

	private function get_selected_specialty() {
		$value = isset( $_GET['cr_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['cr_specialty'] ) ) : '';

		return $this->get_specialty_from_value( $value );
	}

	private function get_specialty_from_value( $value ) {
		if ( '' === $value ) {
			return null;
		}

		$specialty = get_term_by( 'slug', $value, self::SPECIALTY_TAXONOMY );

		if ( ! $specialty && ctype_digit( $value ) ) {
			$specialty = get_term_by( 'id', (int) $value, self::SPECIALTY_TAXONOMY );
		}

		return $specialty instanceof WP_Term ? $specialty : null;
	}

	private function get_requested_code_version( $query_var ) {
		$value = get_query_var( $query_var );
		if ( ! is_string( $value ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $value ) );
	}

	private function get_local_pdf_path( $code_version ) {
		$path = trailingslashit( self::PARSER_ROOT ) . 'data/files/' . $code_version . '/' . $code_version . '.pdf';

		return file_exists( $path ) ? $path : '';
	}

	private function get_remote_pdf_url( $code_version ) {
		return 'https://apicr.minzdrav.gov.ru/api.ashx?op=GetClinrecPdf&id=' . rawurlencode( $code_version );
	}

	private function count_recommendations( $search = '', $specialty_id = 0 ) {
		$query = new WP_Query(
			$this->build_query_args(
				$search,
				$specialty_id,
				1,
				1,
				true
			)
		);

		return (int) $query->found_posts;
	}

	private function get_recommendations( $search = '', $specialty_id = 0, $page_num = 1, $per_page = self::DEFAULT_PER_PAGE ) {
		$query = new WP_Query(
			$this->build_query_args(
				$search,
				$specialty_id,
				$page_num,
				$per_page,
				true
			)
		);

		$items = [];

		foreach ( $query->posts as $post ) {
			$item = $this->hydrate_recommendation_post( $post );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	private function get_sidebar_specialties( $search = '', $selected_specialty_id = 0 ) {
		$terms = get_terms(
			[
				'taxonomy'   => self::SPECIALTY_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		usort(
			$terms,
			function ( $left, $right ) {
				return strnatcasecmp( $left->name, $right->name );
			}
		);

		$items = [];

		foreach ( $terms as $term ) {
			$items[] = [
				'term_id' => (int) $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'count'   => $this->count_recommendations( $search, (int) $term->term_id ),
				'url'     => $this->get_archive_url(
					array_filter(
						[
							'cr_specialty' => $term->slug,
							's'            => $search,
						]
					)
				),
				'active'  => $selected_specialty_id === (int) $term->term_id,
			];
		}

		return $items;
	}

	private function get_recommendation_by_code_version( $code_version ) {
		if ( isset( $this->recommendation_cache[ $code_version ] ) ) {
			return $this->recommendation_cache[ $code_version ];
		}

		$base_args = [
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'ignore_sticky_posts' => true,
			'ep_integrate'        => false,
			'tax_query'           => [
				[
					'taxonomy' => self::ARTICLE_TYPE_TAXONOMY,
					'field'    => 'slug',
					'terms'    => self::ARTICLE_TYPE_SLUG,
				],
			],
		];

		$query = new WP_Query(
			$base_args + [
				'meta_query' => [
					'relation' => 'OR',
					[
						'key'   => self::CODE_VERSION_META_KEY,
						'value' => $code_version,
					],
					[
						'key'   => self::SOURCE_ID_META_KEY,
						'value' => 'pi_' . $code_version,
					],
				],
			]
		);

		if ( empty( $query->posts ) ) {
			$query = new WP_Query(
				$base_args + [
					'name' => sanitize_title( $code_version ),
				]
			);
		}

		$post = ! empty( $query->posts ) ? $query->posts[0] : null;
		$this->recommendation_cache[ $code_version ] = $post ? $this->hydrate_recommendation_post( $post ) : null;

		return $this->recommendation_cache[ $code_version ];
	}

	private function hydrate_recommendation_post( $post ) {
		$post = get_post( $post );
		if ( ! ( $post instanceof WP_Post ) ) {
			return null;
		}

		$code_version = (string) get_post_meta( $post->ID, self::CODE_VERSION_META_KEY, true );

		if ( '' === $code_version ) {
			$source_id = (string) get_post_meta( $post->ID, self::SOURCE_ID_META_KEY, true );
			if ( 0 === strpos( $source_id, 'pi_' ) ) {
				$code_version = substr( $source_id, 3 );
			}
		}

		$route_key = '' !== $code_version ? $code_version : (string) $post->post_name;

		$publish_date = (string) get_post_meta( $post->ID, self::PUBLISH_DATE_META_KEY, true );
		if ( '' === $publish_date ) {
			$publish_date = $post->post_date;
		}

		$timestamp    = strtotime( $publish_date );
		$publish_year = $timestamp ? wp_date( 'Y', $timestamp ) : '';

		$specialty_terms = get_the_terms( $post, self::SPECIALTY_TAXONOMY );
		$age_terms       = get_the_terms( $post, self::AGE_TAXONOMY );
		$rendered_html   = apply_filters( 'the_content', $post->post_content );
		$excerpt_source  = '' !== $post->post_excerpt ? $post->post_excerpt : $rendered_html;
		$pdf_url         = $this->get_recommendation_pdf_url( $post->ID, $code_version );

		return [
			'post_id'        => (int) $post->ID,
			'code_version'   => $code_version,
			'name'           => (string) $post->post_title,
			'publish_date'   => (string) $publish_date,
			'publish_year'   => $publish_year,
			'age_category'   => $this->build_term_names( $age_terms ),
			'formatted_html' => $rendered_html,
			'excerpt'        => $this->get_recommendation_excerpt( $excerpt_source ),
			'specialties'    => $this->hydrate_term_links( $specialty_terms ),
			'permalink'      => '' !== $route_key ? $this->get_single_url( $route_key ) : get_permalink( $post ),
			'pdf_url'        => $pdf_url,
		];
	}

	private function get_recommendation_pdf_url( $post_id, $code_version ) {
		$pdf_attachment_id = (int) get_post_meta( $post_id, self::PDF_ATTACHMENT_META_KEY, true );
		if ( $pdf_attachment_id > 0 ) {
			$attachment_url = wp_get_attachment_url( $pdf_attachment_id );
			if ( $attachment_url ) {
				return $attachment_url;
			}
		}

		return '' !== $code_version ? $this->get_pdf_url( $code_version ) : '';
	}

	private function build_term_names( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$names = wp_list_pluck( $terms, 'name' );

		return implode( ', ', array_filter( array_map( 'strval', $names ) ) );
	}

	private function hydrate_term_links( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		$items = [];

		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}

			$items[] = [
				'term_id' => (int) $term->term_id,
				'slug'    => $term->slug,
				'name'    => $term->name,
				'url'     => $this->get_archive_url(
					[
						'cr_specialty' => $term->slug,
					]
				),
			];
		}

		return $items;
	}

	private function build_query_args( $search = '', $specialty_id = 0, $page_num = 1, $per_page = self::DEFAULT_PER_PAGE, $with_found_rows = true ) {
		$tax_query = [
			[
				'taxonomy' => self::ARTICLE_TYPE_TAXONOMY,
				'field'    => 'slug',
				'terms'    => self::ARTICLE_TYPE_SLUG,
			],
		];

		if ( $specialty_id > 0 ) {
			$tax_query[] = [
				'taxonomy' => self::SPECIALTY_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => [ (int) $specialty_id ],
			];
		}

		$args = [
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => max( 1, (int) $page_num ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => ! $with_found_rows,
			'tax_query'           => $tax_query,
			'ep_integrate'        => '' !== $search,
		];

		if ( '' !== $search ) {
			$args['s'] = $search;
		} else {
			$args['orderby'] = [
				'date' => 'DESC',
				'ID'   => 'DESC',
			];
		}

		return $args;
	}

	private function get_pagination_html( $page_num, $total_pages, $search, $specialty_slug ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$args = [];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( '' !== $specialty_slug ) {
			$args['cr_specialty'] = $specialty_slug;
		}

		return paginate_links(
			[
				'base'      => add_query_arg( $args + [ 'cr_page_num' => '%#%' ], $this->get_archive_url() ),
				'format'    => '',
				'current'   => $page_num,
				'total'     => $total_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'type'      => 'list',
			]
		);
	}

	private function build_archive_subheading( $total_results, $selected_specialty, $search ) {
		if ( $selected_specialty && '' !== $search ) {
			return sprintf( 'Найдено %d рекомендаций по специальности «%s» и запросу «%s».', $total_results, $selected_specialty->name, $search );
		}

		if ( $selected_specialty ) {
			return sprintf( 'Найдено %d клинических рекомендаций по специальности «%s».', $total_results, $selected_specialty->name );
		}

		if ( '' !== $search ) {
			return sprintf( 'Найдено %d клинических рекомендаций по запросу «%s».', $total_results, $search );
		}

		return sprintf( 'Найдено %d клинических рекомендаций.', $total_results );
	}

	private function build_archive_heading( $selected_specialty, $search ) {
		if ( $selected_specialty ) {
			return $selected_specialty->name;
		}

		if ( '' !== $search ) {
			return 'Результаты поиска';
		}

		return 'Последние обновления';
	}

	private function set_404() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		exit;
	}
}
