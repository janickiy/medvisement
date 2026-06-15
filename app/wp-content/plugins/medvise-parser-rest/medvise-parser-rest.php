<?php
/**
 * Plugin Name: Medvise Parser REST
 * Description: Custom REST endpoint to upsert parser posts and attachments.
 * Version: 0.2.1
 * Author: Julia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MEDVISE_PARSER_REST_BOOTSTRAPPED' ) ) {
	return;
}

define( 'MEDVISE_PARSER_REST_BOOTSTRAPPED', true );

const MEDVISE_PARSER_REST_ATTACHMENT_KEY_META   = 'medvise_parser_attachment_key';
const MEDVISE_PARSER_REST_ATTACHMENT_HASH_META  = 'medvise_parser_attachment_hash';
const MEDVISE_PARSER_REST_ATTACHMENT_ROLE_META  = 'medvise_parser_attachment_role';
const MEDVISE_PARSER_REST_ATTACHMENT_SOURCE_META = 'medvise_parser_attachment_source_id';
const MEDVISE_PARSER_REST_ATTACHMENTS_META      = 'medvise_clinrec_attachment_ids';
const MEDVISE_PARSER_REST_PDF_ATTACHMENT_META   = 'medvise_clinrec_pdf_attachment_id';
const MEDVISE_PARSER_REST_SYNCED_AT_META        = 'medvise_clinrec_attachments_synced_at';
const MEDVISE_PARSER_REST_SOURCE_QUERY_VAR      = 'medvise_eng_article_source';
const MEDVISE_PARSER_REST_SOURCE_REWRITE_OPTION = 'medvise_eng_article_source_rewrite_version';
const MEDVISE_PARSER_REST_SOURCE_REWRITE_VERSION = '1.0.0';
const MEDVISE_PARSER_REST_ENGLISH_ARTICLE_TYPE_SLUG = 'eng-articles';
const MEDVISE_PARSER_REST_MNN_TAXONOMY = 'mnn';

add_action( 'init', 'medvise_parser_rest_register_mnn_taxonomy', 9 );
add_action( 'init', 'medvise_parser_rest_register_source_routes' );
add_filter( 'query_vars', 'medvise_parser_rest_register_source_query_vars' );
add_action( 'template_redirect', 'medvise_parser_rest_resolve_source_route', 1 );
add_filter( 'the_content', 'medvise_parser_rest_rewrite_source_links_in_content', 9 );
add_action( 'enqueue_block_editor_assets', 'medvise_parser_rest_enqueue_details_editor_toggle_fix' );

function medvise_parser_rest_enqueue_details_editor_toggle_fix() {
	$script = <<<'JS'
document.addEventListener('click', function (event) {
	var summary = event.target && event.target.closest ? event.target.closest('.block-editor-block-list__layout details.wp-block-details > summary') : null;
	if (!summary) {
		return;
	}

	event.stopPropagation();
}, true);
JS;

	wp_register_script( 'medvise-parser-details-editor-toggle', '', [], '1.0.0', true );
	wp_enqueue_script( 'medvise-parser-details-editor-toggle' );
	wp_add_inline_script( 'medvise-parser-details-editor-toggle', $script );
}

function medvise_parser_rest_register_mnn_taxonomy() {
	if ( taxonomy_exists( MEDVISE_PARSER_REST_MNN_TAXONOMY ) ) {
		register_taxonomy_for_object_type( MEDVISE_PARSER_REST_MNN_TAXONOMY, 'substance' );
		unregister_taxonomy_for_object_type( MEDVISE_PARSER_REST_MNN_TAXONOMY, 'disease' );
		return;
	}

	register_taxonomy(
		MEDVISE_PARSER_REST_MNN_TAXONOMY,
		'substance',
		[
			'labels'              => [
				'name'          => 'МНН',
				'singular_name' => 'МНН',
				'search_items'  => 'Искать МНН',
				'all_items'     => 'Все МНН',
				'edit_item'     => 'Изменить МНН',
				'update_item'   => 'Обновить МНН',
				'add_new_item'  => 'Добавить МНН',
				'new_item_name' => 'Название МНН',
				'menu_name'     => 'МНН',
			],
			'public'              => true,
			'show_in_rest'        => true,
			'hierarchical'        => false,
			'rewrite'             => false,
			'publicly_queryable'  => false,
			'show_admin_column'   => true,
		]
	);
}

function medvise_parser_rest_register_source_routes() {
	add_rewrite_rule(
		'^eng-articles/source/([0-9]+)/?$',
		'index.php?' . MEDVISE_PARSER_REST_SOURCE_QUERY_VAR . '=$matches[1]',
		'top'
	);

	if ( MEDVISE_PARSER_REST_SOURCE_REWRITE_VERSION !== get_option( MEDVISE_PARSER_REST_SOURCE_REWRITE_OPTION ) ) {
		flush_rewrite_rules( false );
		update_option( MEDVISE_PARSER_REST_SOURCE_REWRITE_OPTION, MEDVISE_PARSER_REST_SOURCE_REWRITE_VERSION, false );
	}
}

function medvise_parser_rest_register_source_query_vars( $vars ) {
	$vars[] = MEDVISE_PARSER_REST_SOURCE_QUERY_VAR;

	return $vars;
}

function medvise_parser_rest_resolve_source_route() {
	$topic_id = get_query_var( MEDVISE_PARSER_REST_SOURCE_QUERY_VAR );
	if ( '' === (string) $topic_id ) {
		return;
	}

	$topic_id = preg_replace( '/\D+/', '', (string) $topic_id );
	if ( '' === $topic_id ) {
		medvise_parser_rest_render_source_404();
	}

	$post_id = medvise_parser_rest_find_published_english_post_by_topic_id( $topic_id );
	if ( $post_id <= 0 ) {
		medvise_parser_rest_render_source_404();
	}

	$permalink = get_permalink( $post_id );
	if ( ! $permalink ) {
		medvise_parser_rest_render_source_404();
	}

	wp_safe_redirect( $permalink, 302 );
	exit;
}

function medvise_parser_rest_find_published_english_post_by_topic_id( $topic_id ) {
	$args = [
		'post_type'              => 'disease',
		'post_status'            => 'publish',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'meta_query'             => [
			[
				'key'     => 'source_id',
				'value'   => 'pi_en_' . $topic_id,
				'compare' => '=',
			],
		],
	];

	if ( taxonomy_exists( 'article-type' ) ) {
		$args['tax_query'] = [
			[
				'taxonomy' => 'article-type',
				'field'    => 'slug',
				'terms'    => MEDVISE_PARSER_REST_ENGLISH_ARTICLE_TYPE_SLUG,
			],
		];
	}

	$query = new WP_Query( $args );

	return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
}

function medvise_parser_rest_render_source_404() {
	global $wp_query;

	if ( $wp_query instanceof WP_Query ) {
		$wp_query->set_404();
	}

	status_header( 404 );
	nocache_headers();

	$template = get_query_template( '404' );
	if ( $template ) {
		include $template;
	}

	exit;
}

function medvise_parser_rest_rewrite_source_links_in_content( $content ) {
	$content = (string) $content;
	if ( false === strpos( $content, '/contents/topics/' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'/\bhref=(["\'])(?:https?:\/\/(?:www\.)?(?:uptodate\.com|utd\.libook\.xyz))?\/contents\/topics\/([0-9]+)\/?(#[^"\']*)?\1/i',
		static function ( $matches ) {
			$quote    = $matches[1];
			$topic_id = $matches[2];
			$fragment = isset( $matches[3] ) ? sanitize_text_field( $matches[3] ) : '';
			$href     = '/eng-articles/source/' . $topic_id . '/' . $fragment;

			return 'href=' . $quote . esc_url( $href ) . $quote;
		},
		$content
	);
}

function medvise_parser_rest_is_english_article_payload( $external_id, $article_type_slug, $is_english ) {
	return $is_english
		|| MEDVISE_PARSER_REST_ENGLISH_ARTICLE_TYPE_SLUG === sanitize_title( (string) $article_type_slug )
		|| 1 === preg_match( '/^en_\d+$/', (string) $external_id );
}

function medvise_parser_rest_build_article_post_name( $title, $external_id ) {
	$post_name = sanitize_title( (string) $title );
	if ( '' === $post_name ) {
		$post_name = sanitize_title( (string) $external_id );
	}

	return $post_name;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'medvise/v1',
			'/(?P<post_type>disease|substance)/upsert',
			[
				'methods'             => 'POST',
				'callback'            => 'medvise_parser_rest_upsert_post',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		register_rest_route(
			'medvise/v1',
			'/(?P<post_type>disease|substance)/status',
			[
				'methods'             => 'GET',
				'callback'            => 'medvise_parser_rest_post_status',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'external_id' => [
						'required' => false,
						'type'     => 'string',
					],
					'source_id'   => [
						'required' => false,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			'medvise/v1',
			'/(?P<post_type>disease|substance)/attachment',
			[
				'methods'             => 'POST',
				'callback'            => 'medvise_parser_rest_upload_attachment',
				'permission_callback' => static function () {
					return current_user_can( 'upload_files' );
				},
			]
		);

		register_rest_route(
			'medvise/v1',
			'/(?P<post_type>disease|substance)/attachments/finalize',
			[
				'methods'             => 'POST',
				'callback'            => 'medvise_parser_rest_finalize_attachments',
				'permission_callback' => static function () {
					return current_user_can( 'upload_files' );
				},
			]
		);
	}
);

function medvise_parser_rest_get_request_post_type( WP_REST_Request $request ) {
	$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );

	return in_array( $post_type, [ 'disease', 'substance' ], true ) ? $post_type : 'disease';
}

function medvise_parser_rest_get_or_create_term_slug( $taxonomy, $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}

	if ( preg_match( '/^[a-z0-9\-]+$/', $value ) ) {
		$term = get_term_by( 'slug', $value, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term->slug;
		}
	}

	$term = get_term_by( 'name', $value, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		return $term->slug;
	}

	$created = wp_insert_term( $value, $taxonomy );
	if ( is_wp_error( $created ) ) {
		return null;
	}

	if ( isset( $created['slug'] ) && $created['slug'] ) {
		return $created['slug'];
	}

	$term_id = isset( $created['term_id'] ) ? (int) $created['term_id'] : 0;
	if ( $term_id > 0 ) {
		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term->slug;
		}
	}

	return null;
}

function medvise_parser_rest_set_terms( $post_id, $taxonomy, $values ) {
	if ( ! is_array( $values ) || empty( $values ) ) {
		return;
	}

	$slugs = [];
	foreach ( $values as $value ) {
		$slug = medvise_parser_rest_get_or_create_term_slug( $taxonomy, $value );
		if ( $slug ) {
			$slugs[] = $slug;
		}
	}

	if ( ! empty( $slugs ) ) {
		wp_set_object_terms( $post_id, $slugs, $taxonomy, false );
	}
}

function medvise_parser_rest_get_or_create_article_type_slug( $slug, $name = '' ) {
	$slug = sanitize_title( (string) $slug );
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $slug ) {
		return '';
	}

	if ( ! taxonomy_exists( 'article-type' ) ) {
		return $slug;
	}

	$term = get_term_by( 'slug', $slug, 'article-type' );
	if ( $term && ! is_wp_error( $term ) ) {
		if ( '' !== $name && $name !== $term->name ) {
			wp_update_term(
				(int) $term->term_id,
				'article-type',
				[
					'name' => $name,
				]
			);
		}

		return $term->slug;
	}

	$term_name = '' !== $name ? $name : $slug;
	$created = wp_insert_term(
		$term_name,
		'article-type',
		[
			'slug' => $slug,
		]
	);

	if ( is_wp_error( $created ) ) {
		$term_id = (int) $created->get_error_data( 'term_exists' );
		if ( $term_id > 0 ) {
			$term = get_term( $term_id, 'article-type' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->slug;
			}
		}

		return $slug;
	}

	$term_id = isset( $created['term_id'] ) ? (int) $created['term_id'] : 0;
	if ( $term_id > 0 ) {
		$term = get_term( $term_id, 'article-type' );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term->slug;
		}
	}

	return $slug;
}

function medvise_parser_rest_prepare_post_content( $content ) {
	$content = trim( (string) $content );

	if ( '' === $content || str_contains( $content, '<!-- wp:' ) ) {
		return $content;
	}

	$block_content = medvise_parser_rest_convert_html_to_blocks( $content );

	return '' !== trim( $block_content ) ? $block_content : $content;
}

function medvise_parser_rest_convert_html_to_blocks( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}

	$document  = new DOMDocument( '1.0', 'UTF-8' );
	$previous  = libxml_use_internal_errors( true );
	$wrapped   = '<!DOCTYPE html><html><body><div id="medvise-parser-root">' . $html . '</div></body></html>';
	$converted = mb_convert_encoding( $wrapped, 'HTML-ENTITIES', 'UTF-8' );
	$loaded    = $document->loadHTML( $converted );

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return $html;
	}

	$root = $document->getElementById( 'medvise-parser-root' );
	if ( ! $root instanceof DOMNode ) {
		return $html;
	}

	return trim( medvise_parser_rest_convert_dom_children_to_blocks( $root ) );
}

function medvise_parser_rest_convert_dom_children_to_blocks( DOMNode $parent ) {
	$blocks        = [];
	$inline_buffer = '';

	foreach ( $parent->childNodes as $child ) {
		if ( XML_TEXT_NODE === $child->nodeType ) {
			$text = preg_replace( '/\s+/u', ' ', (string) $child->textContent );
			if ( '' !== trim( (string) $text ) ) {
				$inline_buffer .= esc_html( $text );
			}
			continue;
		}

		if ( XML_COMMENT_NODE === $child->nodeType ) {
			continue;
		}

		if ( ! $child instanceof DOMElement ) {
			continue;
		}

		if ( medvise_parser_rest_is_block_level_node( $child ) ) {
			$paragraph_block = medvise_parser_rest_flush_inline_buffer_to_paragraph( $inline_buffer );
			if ( '' !== $paragraph_block ) {
				$blocks[] = $paragraph_block;
			}

			$inline_buffer = '';
			$block_markup  = medvise_parser_rest_convert_dom_node_to_block( $child );

			if ( '' !== $block_markup ) {
				$blocks[] = $block_markup;
			}

			continue;
		}

		$inline_buffer .= medvise_parser_rest_dom_node_outer_html( $child );
	}

	$paragraph_block = medvise_parser_rest_flush_inline_buffer_to_paragraph( $inline_buffer );
	if ( '' !== $paragraph_block ) {
		$blocks[] = $paragraph_block;
	}

	return implode( "\n\n", array_filter( $blocks ) );
}

function medvise_parser_rest_is_block_level_node( DOMElement $node ) {
	$tag_name = strtolower( $node->tagName );

	return in_array(
		$tag_name,
		[
			'details',
			'p',
			'ul',
			'ol',
			'table',
			'figure',
			'img',
			'blockquote',
			'pre',
			'hr',
			'div',
			'section',
			'article',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
		],
		true
	);
}

function medvise_parser_rest_convert_dom_node_to_block( DOMElement $node ) {
	$tag_name = strtolower( $node->tagName );

	if ( 'details' === $tag_name ) {
		return medvise_parser_rest_convert_details_node_to_block( $node );
	}

	if ( 'p' === $tag_name ) {
		return medvise_parser_rest_convert_paragraph_node_to_block( $node );
	}

	if ( in_array( $tag_name, [ 'ul', 'ol' ], true ) ) {
		return medvise_parser_rest_convert_list_node_to_block( $node );
	}

	if ( in_array( $tag_name, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
		return medvise_parser_rest_convert_heading_node_to_block( $node );
	}

	if ( in_array( $tag_name, [ 'table', 'figure' ], true ) ) {
		return medvise_parser_rest_convert_table_node_to_block( $node );
	}

	if ( 'img' === $tag_name ) {
		return medvise_parser_rest_convert_image_node_to_block( $node );
	}

	if ( 'blockquote' === $tag_name ) {
		return medvise_parser_rest_wrap_in_block(
			'quote',
			'<blockquote class="wp-block-quote">' . medvise_parser_rest_dom_children_html( $node ) . '</blockquote>'
		);
	}

	if ( 'pre' === $tag_name ) {
		return medvise_parser_rest_wrap_in_block(
			'preformatted',
			'<pre class="wp-block-preformatted">' . esc_html( $node->textContent ) . '</pre>'
		);
	}

	if ( 'hr' === $tag_name ) {
		return medvise_parser_rest_wrap_in_block(
			'separator',
			'<hr class="wp-block-separator has-alpha-channel-opacity"/>'
		);
	}

	if ( in_array( $tag_name, [ 'div', 'section', 'article' ], true ) ) {
		$children_markup = medvise_parser_rest_convert_dom_children_to_blocks( $node );
		if ( '' !== trim( $children_markup ) ) {
			return $children_markup;
		}
	}

	return medvise_parser_rest_wrap_in_block( 'html', medvise_parser_rest_dom_node_outer_html( $node ) );
}

function medvise_parser_rest_convert_details_node_to_block( DOMElement $node ) {
	$summary_text = '';
	$content      = '';

	foreach ( $node->childNodes as $child ) {
		if ( $child instanceof DOMElement && 'summary' === strtolower( $child->tagName ) ) {
			$summary_text = trim( preg_replace( '/\s+/u', ' ', $child->textContent ) );
			continue;
		}

		if ( XML_TEXT_NODE === $child->nodeType && '' === trim( (string) $child->textContent ) ) {
			continue;
		}

		$fragment = $node->ownerDocument->createElement( 'div' );
		$fragment->appendChild( $child->cloneNode( true ) );
		$content .= medvise_parser_rest_convert_dom_children_to_blocks( $fragment );
		if ( '' !== trim( $content ) ) {
			$content .= "\n\n";
		}
	}

	$content = trim( $content );
	$attrs   = wp_json_encode(
		[
			'summary' => $summary_text,
		],
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);

	$html = '<details class="wp-block-details"><summary>' . esc_html( $summary_text ) . '</summary>';
	if ( '' !== $content ) {
		$html .= "\n" . $content . "\n";
	}
	$html .= '</details>';

	return medvise_parser_rest_wrap_in_block( 'details', $html, $attrs );
}

function medvise_parser_rest_convert_paragraph_node_to_block( DOMElement $node ) {
	$html = trim( medvise_parser_rest_dom_children_html( $node ) );
	if ( '' === preg_replace( '/&nbsp;|\s+/u', '', strip_tags( $html ) ) ) {
		return '';
	}

	return medvise_parser_rest_wrap_in_block( 'paragraph', '<p>' . $html . '</p>' );
}

function medvise_parser_rest_convert_heading_node_to_block( DOMElement $node ) {
	$level = (int) substr( strtolower( $node->tagName ), 1 );
	$attrs = 2 === $level ? '' : wp_json_encode( [ 'level' => $level ] );
	$html  = sprintf(
		'<h%d>%s</h%d>',
		$level,
		trim( medvise_parser_rest_dom_children_html( $node ) ),
		$level
	);

	return medvise_parser_rest_wrap_in_block( 'heading', $html, $attrs );
}

function medvise_parser_rest_convert_list_node_to_block( DOMElement $node ) {
	$is_ordered = 'ol' === strtolower( $node->tagName );
	$items      = [];

	foreach ( $node->childNodes as $child ) {
		if ( ! $child instanceof DOMElement || 'li' !== strtolower( $child->tagName ) ) {
			continue;
		}

		$items[] = medvise_parser_rest_convert_list_item_node_to_block( $child );
	}

	if ( empty( $items ) ) {
		return '';
	}

	$list_tag = $is_ordered ? 'ol' : 'ul';
	$attrs    = $is_ordered ? wp_json_encode( [ 'ordered' => true ] ) : '';
	$html     = '<' . $list_tag . ">\n" . implode( "\n", $items ) . "\n</" . $list_tag . '>';

	return medvise_parser_rest_wrap_in_block( 'list', $html, $attrs );
}

function medvise_parser_rest_convert_list_item_node_to_block( DOMElement $node ) {
	$content = '';
	$nested  = [];

	foreach ( $node->childNodes as $child ) {
		if ( XML_TEXT_NODE === $child->nodeType ) {
			$text = preg_replace( '/\s+/u', ' ', (string) $child->textContent );
			if ( '' !== trim( (string) $text ) ) {
				$content .= esc_html( $text );
			}
			continue;
		}

		if ( ! $child instanceof DOMElement ) {
			continue;
		}

		if ( in_array( strtolower( $child->tagName ), [ 'ul', 'ol' ], true ) ) {
			$nested[] = medvise_parser_rest_convert_list_node_to_block( $child );
			continue;
		}

		$content .= medvise_parser_rest_dom_node_outer_html( $child );
	}

	$html = '<li>' . trim( $content );
	if ( ! empty( $nested ) ) {
		$html .= "\n" . implode( "\n", array_filter( $nested ) );
	}
	$html .= '</li>';

	return medvise_parser_rest_wrap_in_block( 'list-item', $html );
}

function medvise_parser_rest_convert_table_node_to_block( DOMElement $node ) {
	$table_html = '';

	if ( 'table' === strtolower( $node->tagName ) ) {
		$table_html = medvise_parser_rest_dom_node_outer_html( $node );
	} else {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && 'table' === strtolower( $child->tagName ) ) {
				$table_html = medvise_parser_rest_dom_node_outer_html( $child );
				break;
			}
		}
	}

	if ( '' === $table_html ) {
		return medvise_parser_rest_wrap_in_block( 'html', medvise_parser_rest_dom_node_outer_html( $node ) );
	}

	return medvise_parser_rest_wrap_in_block(
		'table',
		'<figure class="wp-block-table">' . $table_html . '</figure>'
	);
}

function medvise_parser_rest_convert_image_node_to_block( DOMElement $node ) {
	$img_html = medvise_parser_rest_dom_node_outer_html( $node );

	return medvise_parser_rest_wrap_in_block(
		'image',
		'<figure class="wp-block-image">' . $img_html . '</figure>'
	);
}

function medvise_parser_rest_flush_inline_buffer_to_paragraph( &$buffer ) {
	$html   = trim( $buffer );
	$buffer = '';

	if ( '' === $html || '' === preg_replace( '/&nbsp;|\s+/u', '', strip_tags( $html ) ) ) {
		return '';
	}

	return medvise_parser_rest_wrap_in_block( 'paragraph', '<p>' . $html . '</p>' );
}

function medvise_parser_rest_wrap_in_block( $block_name, $html, $attrs = '' ) {
	$attrs = trim( (string) $attrs );
	$open  = '<!-- wp:' . $block_name . ( '' !== $attrs ? ' ' . $attrs : '' ) . ' -->';
	$close = '<!-- /wp:' . $block_name . ' -->';

	return $open . "\n" . $html . "\n" . $close;
}

function medvise_parser_rest_dom_children_html( DOMNode $node ) {
	$html = '';

	foreach ( $node->childNodes as $child ) {
		$html .= medvise_parser_rest_dom_node_outer_html( $child );
	}

	return $html;
}

function medvise_parser_rest_dom_node_outer_html( DOMNode $node ) {
	if ( XML_TEXT_NODE === $node->nodeType ) {
		return esc_html( $node->textContent );
	}

	return $node->ownerDocument ? $node->ownerDocument->saveHTML( $node ) : '';
}

function medvise_parser_rest_upsert_disease( WP_REST_Request $request ) {
	return medvise_parser_rest_upsert_post( $request );
}

function medvise_parser_rest_upsert_post( WP_REST_Request $request ) {
	$post_type         = medvise_parser_rest_get_request_post_type( $request );
	$params            = $request->get_json_params();
	$title             = isset( $params['title'] ) ? wp_strip_all_tags( (string) $params['title'] ) : '';
	$content           = isset( $params['content'] ) ? medvise_parser_rest_prepare_post_content( (string) $params['content'] ) : '';
	$external_id       = isset( $params['external_id'] ) ? sanitize_text_field( (string) $params['external_id'] ) : '';
	$status            = array_key_exists( 'status', $params ) ? sanitize_key( (string) $params['status'] ) : '';
	$article_type_slug = isset( $params['article_type_slug'] ) ? sanitize_text_field( (string) $params['article_type_slug'] ) : '';
	$article_type_name = isset( $params['article_type_name'] ) ? sanitize_text_field( (string) $params['article_type_name'] ) : '';
	$is_english        = ! empty( $params['is_english'] );
	$post_excerpt      = isset( $params['post_excerpt'] ) ? wp_kses_post( (string) $params['post_excerpt'] ) : '';
	$post_date         = isset( $params['post_date'] ) ? sanitize_text_field( (string) $params['post_date'] ) : '';
	$author_id         = isset( $params['author_id'] ) ? absint( $params['author_id'] ) : 0;

	$age_slugs = [];
	if ( isset( $params['age_slugs'] ) && is_array( $params['age_slugs'] ) ) {
		foreach ( $params['age_slugs'] as $age ) {
			$age_slugs[] = sanitize_title( (string) $age );
		}
	}

	$symptom_values = [];
	if ( isset( $params['symptom_names_or_slugs'] ) && is_array( $params['symptom_names_or_slugs'] ) ) {
		foreach ( $params['symptom_names_or_slugs'] as $symptom ) {
			$symptom_values[] = sanitize_text_field( (string) $symptom );
		}
	}

	$specialty_values = [];
	if ( isset( $params['specialty_slugs'] ) && is_array( $params['specialty_slugs'] ) ) {
		foreach ( $params['specialty_slugs'] as $specialty ) {
			$specialty_values[] = sanitize_title( (string) $specialty );
		}
	}

	if ( isset( $params['specialty_names_or_slugs'] ) && is_array( $params['specialty_names_or_slugs'] ) ) {
		foreach ( $params['specialty_names_or_slugs'] as $specialty ) {
			$specialty = sanitize_text_field( (string) $specialty );
			if ( '' !== $specialty ) {
				$specialty_values[] = $specialty;
			}
		}
	}

	$mnn_values = [];
	if ( isset( $params['mnn_names_or_slugs'] ) && is_array( $params['mnn_names_or_slugs'] ) ) {
		foreach ( $params['mnn_names_or_slugs'] as $mnn ) {
			$mnn = sanitize_text_field( (string) $mnn );
			if ( '' !== $mnn ) {
				$mnn_values[] = $mnn;
			}
		}
	}

	$meta_extra = [];
	if ( isset( $params['meta_extra'] ) && is_array( $params['meta_extra'] ) ) {
		$meta_extra = $params['meta_extra'];
	}

	if ( '' === $title || '' === $external_id ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => 'title/external_id required' ], 400 );
	}

	$source_id = 'pi_' . $external_id;
	$post_id   = medvise_parser_find_post_by_source_id( $source_id, $post_type );

	if ( ! $post_id ) {
		$post_id = medvise_parser_rest_find_post_by_title( $title, $article_type_slug, $post_type );
	}

	$postarr = [
		'post_title'   => $title,
		'post_content' => $content,
		'post_type'    => $post_type,
	];

	if ( medvise_parser_rest_is_english_article_payload( $external_id, $article_type_slug, $is_english ) ) {
		$current_post_name = $post_id ? (string) get_post_field( 'post_name', $post_id ) : '';
		if ( '' === $current_post_name ) {
			$post_name = medvise_parser_rest_build_article_post_name( $title, $external_id );
			if ( '' !== $post_name ) {
				$postarr['post_name'] = $post_name;
			}
		}
	}

	if ( '' !== $post_excerpt ) {
		$postarr['post_excerpt'] = $post_excerpt;
	}

	if ( $author_id > 0 && get_userdata( $author_id ) instanceof WP_User ) {
		$postarr['post_author'] = $author_id;
	}

	if ( '' !== $status ) {
		$postarr['post_status'] = $status;
	} elseif ( ! $post_id ) {
		$postarr['post_status'] = 'draft';
	}

	if ( '' !== $post_date ) {
		$timestamp = strtotime( $post_date );
		if ( $timestamp ) {
			$local_post_date        = wp_date( 'Y-m-d H:i:s', $timestamp );
			$postarr['post_date']   = $local_post_date;
			$postarr['post_date_gmt'] = get_gmt_from_date( $local_post_date );
		}
	}

	if ( $post_id ) {
		$postarr['ID'] = $post_id;
		$new_id        = wp_update_post( $postarr, true );
	} else {
		$new_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $new_id ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => $new_id->get_error_message() ], 500 );
	}

	$post_id = (int) $new_id;

	update_post_meta( $post_id, 'source_id', $source_id );
	delete_post_meta( $post_id, MEDVISE_PARSER_REST_SYNCED_AT_META );

	if ( $is_english ) {
		update_post_meta( $post_id, 'medvise_is_english_article', '1' );
	}

	if ( ! empty( $meta_extra ) ) {
		foreach ( $meta_extra as $key => $value ) {
			$meta_key = sanitize_key( (string) $key );
			if ( '' === $meta_key ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	if ( '' !== $article_type_slug ) {
		$article_type_slug = medvise_parser_rest_get_or_create_article_type_slug( $article_type_slug, $article_type_name );
		wp_set_object_terms( $post_id, [ $article_type_slug ], 'article-type', false );
	}

	if ( ! empty( $age_slugs ) ) {
		medvise_parser_rest_set_terms( $post_id, 'age', $age_slugs );
	}

	if ( ! empty( $symptom_values ) ) {
		medvise_parser_rest_set_terms( $post_id, 'symptoms', $symptom_values );
	}

	if ( ! empty( $specialty_values ) ) {
		medvise_parser_rest_set_terms( $post_id, 'specialty', array_values( array_unique( $specialty_values ) ) );
	}

	if ( ! empty( $mnn_values ) ) {
		medvise_parser_rest_set_terms( $post_id, MEDVISE_PARSER_REST_MNN_TAXONOMY, array_values( array_unique( $mnn_values ) ) );
	}

	return new WP_REST_Response( [ 'ok' => true, 'post_id' => $post_id ], 200 );
}

function medvise_parser_rest_upload_attachment( WP_REST_Request $request ) {
	$post_type       = medvise_parser_rest_get_request_post_type( $request );
	$post_id         = absint( $request->get_param( 'post_id' ) );
	$external_id     = sanitize_text_field( (string) $request->get_param( 'external_id' ) );
	$file_key        = sanitize_file_name( (string) $request->get_param( 'file_key' ) );
	$file_hash       = sanitize_text_field( (string) $request->get_param( 'file_hash' ) );
	$attachment_role = sanitize_key( (string) $request->get_param( 'attachment_role' ) );
	$file_params     = $request->get_file_params();

	if ( $post_id <= 0 || $post_type !== get_post_type( $post_id ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => 'valid parser post_id required' ], 400 );
	}

	if ( empty( $file_params['file'] ) || ! is_array( $file_params['file'] ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => 'uploaded file required' ], 400 );
	}

	$file = $file_params['file'];

	if ( '' === $file_key ) {
		$file_key = sanitize_file_name( $file['name'] ?? '' );
	}

	if ( '' === $file_key ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => 'file_key required' ], 400 );
	}

	$existing_attachment_id = medvise_parser_rest_find_attachment_by_key( $post_id, $file_key );
	if ( $existing_attachment_id > 0 ) {
		$existing_hash = (string) get_post_meta( $existing_attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_HASH_META, true );
		if ( '' !== $file_hash && $existing_hash === $file_hash ) {
			$attachment_meta = medvise_parser_rest_refresh_post_attachment_meta( $post_id );

			return new WP_REST_Response(
				[
					'ok'               => true,
					'action'           => 'skipped',
					'attachment_id'    => $existing_attachment_id,
					'attachment_url'   => (string) wp_get_attachment_url( $existing_attachment_id ),
					'attachment_ids'   => $attachment_meta['attachment_ids'],
					'pdf_attachment_id' => $attachment_meta['pdf_attachment_id'],
				],
				200
			);
		}

		wp_delete_attachment( $existing_attachment_id, true );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_handle_sideload(
		$file,
		[
			'test_form' => false,
			'mimes'     => medvise_parser_rest_allowed_mimes(),
		]
	);

	if ( isset( $upload['error'] ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => (string) $upload['error'] ], 500 );
	}

	$filetype = wp_check_filetype( $upload['file'], null );

	$attachment_id = wp_insert_attachment(
		[
			'post_mime_type' => ! empty( $filetype['type'] ) ? $filetype['type'] : ( $upload['type'] ?? 'application/octet-stream' ),
			'post_title'     => sanitize_text_field( pathinfo( $file_key, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		],
		$upload['file'],
		$post_id
	);

	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return new WP_REST_Response( [ 'ok' => false, 'error' => $attachment_id->get_error_message() ], 500 );
	}

	$attachment_id = (int) $attachment_id;
	$metadata      = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
	if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	update_post_meta( $attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_KEY_META, $file_key );
	update_post_meta( $attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_HASH_META, $file_hash );
	update_post_meta( $attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_ROLE_META, $attachment_role ?: 'file' );
	update_post_meta( $attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_SOURCE_META, 'pi_' . $external_id );

	$attachment_meta = medvise_parser_rest_refresh_post_attachment_meta( $post_id );

	return new WP_REST_Response(
		[
			'ok'                => true,
			'action'            => 'uploaded',
			'attachment_id'     => $attachment_id,
			'attachment_url'    => (string) wp_get_attachment_url( $attachment_id ),
			'attachment_ids'    => $attachment_meta['attachment_ids'],
			'pdf_attachment_id' => $attachment_meta['pdf_attachment_id'],
		],
		200
	);
}

function medvise_parser_rest_finalize_attachments( WP_REST_Request $request ) {
	$post_type     = medvise_parser_rest_get_request_post_type( $request );
	$post_id       = absint( $request->get_param( 'post_id' ) );
	$expected_keys = $request->get_param( 'expected_keys' );

	if ( $post_id <= 0 || $post_type !== get_post_type( $post_id ) ) {
		return new WP_REST_Response( [ 'ok' => false, 'error' => 'valid parser post_id required' ], 400 );
	}

	$expected = [];
	if ( is_array( $expected_keys ) ) {
		foreach ( $expected_keys as $file_key ) {
			$file_key = sanitize_file_name( (string) $file_key );
			if ( '' !== $file_key ) {
				$expected[] = $file_key;
			}
		}
	}

	foreach ( medvise_parser_rest_get_parser_attachment_ids( $post_id ) as $attachment_id ) {
		$current_key = (string) get_post_meta( $attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_KEY_META, true );
		if ( ! in_array( $current_key, $expected, true ) ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	$attachment_meta = medvise_parser_rest_refresh_post_attachment_meta( $post_id );
	update_post_meta( $post_id, MEDVISE_PARSER_REST_SYNCED_AT_META, current_time( 'mysql' ) );

	return new WP_REST_Response(
		[
			'ok'                => true,
			'attachment_ids'    => $attachment_meta['attachment_ids'],
			'pdf_attachment_id' => $attachment_meta['pdf_attachment_id'],
		],
		200
	);
}

function medvise_parser_find_post_by_source_id( $source_id, $post_type = 'disease' ) {
	$post_types = [ $post_type ];
	if ( 'substance' === $post_type ) {
		$post_types[] = 'disease';
	}

	$query = new WP_Query(
		[
			'post_type'      => array_values( array_unique( $post_types ) ),
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => 'source_id',
					'value'   => $source_id,
					'compare' => '=',
				],
			],
		]
	);

	if ( ! empty( $query->posts ) ) {
		return (int) $query->posts[0];
	}

	return 0;
}

function medvise_parser_rest_find_post_by_title( $title, $article_type_slug = '', $post_type = 'disease' ) {
	global $wpdb;

	$post_types = [ $post_type ];
	if ( 'substance' === $post_type ) {
		$post_types[] = 'disease';
	}
	$post_types         = array_values( array_unique( $post_types ) );
	$post_type_clauses  = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
	$title_without_suffix = trim( preg_replace( '/\s+\(КР\)$/u', '', $title ) );
	$title_with_suffix    = str_ends_with( $title_without_suffix, '(КР)' ) ? $title_without_suffix : $title_without_suffix . ' (КР)';
	$params               = $post_types;
	$sql    = "SELECT p.ID
		FROM {$wpdb->posts} p";

	if ( '' !== $article_type_slug ) {
		$sql     .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id";
	}

	$params[] = $title;
	$params[] = $title_without_suffix;
	$params[] = $title_with_suffix;

	if ( '' !== $article_type_slug ) {
		$params[] = $article_type_slug;
	}

	$sql .= " WHERE p.post_type IN ({$post_type_clauses})
		AND p.post_status IN ('publish', 'draft', 'pending', 'private', 'future')
		AND (
			p.post_title COLLATE utf8mb4_unicode_ci = %s COLLATE utf8mb4_unicode_ci
			OR TRIM(REPLACE(p.post_title COLLATE utf8mb4_unicode_ci, ' (КР)', '')) = %s COLLATE utf8mb4_unicode_ci
			OR p.post_title COLLATE utf8mb4_unicode_ci = %s COLLATE utf8mb4_unicode_ci
		)";

	if ( '' !== $article_type_slug ) {
		$sql .= " AND tt.taxonomy = 'article-type'
			AND t.slug = %s";
	}

	$sql .= " ORDER BY (p.post_status = 'publish') DESC, p.ID DESC LIMIT 1";

	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
}

function medvise_parser_rest_find_attachment_by_key( $post_id, $file_key ) {
	$query = new WP_Query(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'post_parent'    => $post_id,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => MEDVISE_PARSER_REST_ATTACHMENT_KEY_META,
					'value'   => $file_key,
					'compare' => '=',
				],
			],
		]
	);

	if ( ! empty( $query->posts ) ) {
		return (int) $query->posts[0];
	}

	return 0;
}

function medvise_parser_rest_get_parser_attachment_ids( $post_id ) {
	$query = new WP_Query(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'post_parent'    => $post_id,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => MEDVISE_PARSER_REST_ATTACHMENT_KEY_META,
					'compare' => 'EXISTS',
				],
			],
		]
	);

	return array_map( 'intval', $query->posts );
}

function medvise_parser_rest_refresh_post_attachment_meta( $post_id ) {
	$attachment_ids = medvise_parser_rest_get_parser_attachment_ids( $post_id );
	$pdf_attachment_id = 0;

	foreach ( $attachment_ids as $attachment_id ) {
		$role = (string) get_post_meta( $attachment_id, MEDVISE_PARSER_REST_ATTACHMENT_ROLE_META, true );
		$path = (string) get_attached_file( $attachment_id );

		if (
			'pdf' === $role
			|| 'application/pdf' === (string) get_post_mime_type( $attachment_id )
			|| 'pdf' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) )
		) {
			$pdf_attachment_id = $attachment_id;
			break;
		}
	}

	update_post_meta( $post_id, MEDVISE_PARSER_REST_ATTACHMENTS_META, $attachment_ids );

	if ( $pdf_attachment_id > 0 ) {
		update_post_meta( $post_id, MEDVISE_PARSER_REST_PDF_ATTACHMENT_META, $pdf_attachment_id );
	} else {
		delete_post_meta( $post_id, MEDVISE_PARSER_REST_PDF_ATTACHMENT_META );
	}

	return [
		'attachment_ids'    => $attachment_ids,
		'pdf_attachment_id' => $pdf_attachment_id,
	];
}

function medvise_parser_rest_allowed_mimes() {
	return [
		'pdf'  => 'application/pdf',
		'png'  => 'image/png',
		'jpg|jpeg|jpe' => 'image/jpeg',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'bmp'  => 'image/bmp',
		'tif|tiff' => 'image/tiff',
		'svg'  => 'image/svg+xml',
		'html|htm' => 'text/html',
		'txt'  => 'text/plain',
		'csv'  => 'text/csv',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'zip'  => 'application/zip',
		'rar'  => 'application/vnd.rar',
	];
}

function medvise_parser_rest_disease_status( WP_REST_Request $request ) {
	return medvise_parser_rest_post_status( $request );
}

function medvise_parser_rest_post_status( WP_REST_Request $request ) {
	$post_type       = medvise_parser_rest_get_request_post_type( $request );
	$raw_external_id = $request->get_param( 'external_id' );
	$raw_source_id   = $request->get_param( 'source_id' );
	$external_id     = null !== $raw_external_id ? sanitize_text_field( (string) $raw_external_id ) : '';
	$source_id       = null !== $raw_source_id ? sanitize_text_field( (string) $raw_source_id ) : '';

	if ( '' === $source_id && '' !== $external_id ) {
		$source_id = 'pi_' . $external_id;
	}

	if ( '' === $source_id ) {
		return new WP_REST_Response(
			[ 'ok' => false, 'error' => 'external_id or source_id required' ],
			400
		);
	}

	$post_id = medvise_parser_find_post_by_source_id( $source_id, $post_type );
	if ( $post_id ) {
		$attachment_ids = get_post_meta( $post_id, MEDVISE_PARSER_REST_ATTACHMENTS_META, true );
		if ( ! is_array( $attachment_ids ) ) {
			$attachment_ids = [];
		}

		return new WP_REST_Response(
			[
				'ok'                 => true,
				'exists'             => true,
				'post_id'            => $post_id,
				'post_status'        => (string) get_post_status( $post_id ),
				'source_id'          => $source_id,
				'attachments_synced' => '' !== (string) get_post_meta( $post_id, MEDVISE_PARSER_REST_SYNCED_AT_META, true ),
				'attachment_count'   => count( $attachment_ids ),
				'pdf_attachment_id'  => (int) get_post_meta( $post_id, MEDVISE_PARSER_REST_PDF_ATTACHMENT_META, true ),
			],
			200
		);
	}

	return new WP_REST_Response(
		[
			'ok'                 => true,
			'exists'             => false,
			'post_id'            => 0,
			'post_status'        => '',
			'source_id'          => $source_id,
			'attachments_synced' => false,
			'attachment_count'   => 0,
			'pdf_attachment_id'  => 0,
		],
		200
	);
}

add_filter(
	'determine_current_user',
	function ( $user ) {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		if ( false === strpos( $request_uri, '/wp-json/medvise/v1/' ) ) {
			return $user;
		}

		$username = null;
		$password = null;

		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			$username = sanitize_user( $_SERVER['PHP_AUTH_USER'] );
			$password = $_SERVER['PHP_AUTH_PW'];
		} elseif ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) || ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
			if ( 0 === strpos( $auth_header, 'Basic ' ) ) {
				$decoded = base64_decode( trim( substr( $auth_header, 6 ) ) );
				$parts   = explode( ':', (string) $decoded, 2 );
				if ( 2 === count( $parts ) ) {
					$username = sanitize_user( $parts[0] );
					$password = $parts[1];
				}
			}
		}

		if ( $username && $password ) {
			$user_obj = wp_authenticate( $username, $password );
			if ( ! is_wp_error( $user_obj ) && $user_obj instanceof WP_User ) {
				return $user_obj->ID;
			}
		}

		return $user;
	},
	20
);
