<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'settings' => array(
		'index.number_of_shards'           => apply_filters( 'ep_default_index_number_of_shards', 5 ),
		'index.number_of_replicas'         => apply_filters( 'ep_default_index_number_of_replicas', 1 ),
		'index.mapping.total_fields.limit' => apply_filters( 'ep_total_field_limit', 5000 ),
		'index.mapping.ignore_malformed'   => apply_filters( 'ep_ignore_malformed', true ),
		'index.max_result_window'          => apply_filters( 'ep_max_result_window', 1000000 ),
		'index.max_shingle_diff'           => apply_filters( 'ep_max_shingle_diff', 8 ),
		'index.similarity'                 => [
			'custom_bm25' => [
				'type' => 'BM25',
				'k1'   => 0.8
			],
			'zero_bm25'   => [
				'type' => 'BM25',
				'k1'   => 0
			]
		],
		'analysis'                         => array(
			'analyzer'    => array(
				// Обычный текст
				'default' => array(
					'tokenizer' => 'standard',
					'filter'  => array(
						'lowercase',
						'ewp_word_delimiter'
					),
					'char_filter' => array(
						'html_strip',
						'yo_filter'
					),
					'language' => 'russian'
				),
				// Обычнный текст + стеммизация
				'c_text_stem' => array(
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter'  => array(
						'lowercase',
						'ewp_word_delimiter',
						'ru_stem_protect',
						'russian_snowball'
					),
					'char_filter' => array(
						'html_strip',
						'yo_filter'
					),
					'language' => 'russian'
				),
				// Обычнный текст + стеммизация + стоп слова
				'c_text_stem_stop' => array(
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter'  => array(
						'lowercase',
						'ewp_word_delimiter',
						'ru_stopwords',
						'ru_stem_protect',
						'russian_snowball'
					),
					'char_filter' => array(
						'html_strip',
						'yo_filter'
					),
					'language' => 'russian'
				),
				// Обычнный текст + стоп слова
				'c_text_stop' => array(
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter'  => array(
						'lowercase',
						'ewp_word_delimiter',
						'ru_stopwords'
					),
					'char_filter' => array(
						'html_strip',
						'yo_filter'
					),
					'language' => 'russian'
				),
				// Для буста коротких заголовков
				'c_length_analyzer'  => array(
					'tokenizer'   => 'standard',
					'char_filter' => [
						'html_strip',
					],
					'language'    => 'russian',
				),
				// От плагина
				'shingle_analyzer' => array(
					'type'      => 'custom',
					'tokenizer' => 'standard',
					'filter'    => array( 'lowercase', 'shingle_filter' ),
				),
				'ewp_lowercase'    => array(
					'type'      => 'custom',
					'tokenizer' => 'keyword',
					'filter'    => array( 'lowercase' ),
				),
			),
			'filter'      => array(
				'ewp_word_delimiter' => array(
					'type'              => 'word_delimiter_graph',
					'preserve_original' => true,
				),
				'ru_stopwords'       => [
					'type'      => 'stop',
					'stopwords' => 'а,без,более,бы,был,была,были,было,быть,в,вам,вас,весь,во,вот,все,всего,всех,вы,где,да,даже,для,до,его,ее,если,есть,еще,же,за,здесь,и,из,или,им,их,к,как,ко,когда,кто,ли,либо,мне,может,мы,на,надо,наш,не,него,нее,нет,ни,них,но,ну,о,об,однако,он,она,они,оно,от,очень,по,под,при,с,со,так,также,такой,там,те,тем,то,того,тоже,той,только,том,ты,у,уже,хотя,чего,чей,чем,что,чтобы,чье,чья,эта,эти,это,я,a'
				],
				'russian_snowball'   => array(
					'type'     => 'snowball',
					'language' => 'Russian',
				),
				'ru_stem_protect' => [
					'type'     => 'keyword_marker',
					'keywords' => [
						'нии',
						'ноу-хау',
						'соо',
						'гбфд',
						'коа',
						'орит',
						'ммоль',
						'нмоль',
						'в/м',
						'д/в', // это надо на токнайзере игнорировать...
						'в/в'
					],
					// или: 'keywords_path': 'путь/protected_words.txt'
					'ignore_case' => true
				],
				// От плагина
				'shingle_filter'     => array(
					'type'             => 'shingle',
					'min_shingle_size' => 2,
					'max_shingle_size' => 5,
				),
				'edge_ngram'         => array(
					'side'     => 'front',
					'max_gram' => 10,
					'min_gram' => 3,
					'type'     => 'edge_ngram',
				),
			),
			'char_filter' => array(
				'yo_filter' => [
					'type'     => 'mapping',
					'mappings' => [
						'ё => е',
						'Ё => Е'
					]
				],
			),
			'normalizer'  => array(
				'lowerasciinormalizer' => array(
					'type'   => 'custom',
					'filter' => array( 'lowercase', 'asciifolding' ),
				),
			),
		),
	),
	'mappings' => array(
		'_meta'             => array(
			'mapping_version' => '7-0.php',
		),
		'date_detection'    => false,
		'dynamic_templates' => array(
			array(
				'template_meta' => array(
					'path_match' => 'post_meta.*',
					'mapping'    => array(
						'type'   => 'text',
						'fields' => array(
							'{name}' => array(
								'type' => 'text',
							),
							'raw'    => array(
								'type'         => 'keyword',
								'ignore_above' => 10922,
							),
						),
					),
				),
			),
			array(
				'template_meta_types' => array(
					'path_match' => 'meta.*',
					'mapping'    => array(
						'type'       => 'object',
						'properties' => array(
							'value'    => array(
								'type'   => 'text',
								'fields' => array(
									'sortable' => array(
										'type'         => 'keyword',
										'ignore_above' => 10922,
										'normalizer'   => 'lowerasciinormalizer',
									),
									'raw'      => array(
										'type'         => 'keyword',
										'ignore_above' => 10922,
									),
								),
							),
							'raw'      => array( /* Left for backwards compat */
								'type'         => 'keyword',
								'ignore_above' => 10922,
							),
							'long'     => array(
								'type' => 'long',
							),
							'double'   => array(
								'type' => 'double',
							),
							'boolean'  => array(
								'type' => 'boolean',
							),
							'date'     => array(
								'type'   => 'date',
								'format' => 'yyyy-MM-dd',
							),
							'datetime' => array(
								'type'   => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss',
							),
							'time'     => array(
								'type'   => 'date',
								'format' => 'HH:mm:ss',
							),
						),
					),
				),
			),
			array(
				'template_terms' => array(
					'path_match' => 'terms.*',
					'mapping'    => array(
						'type'       => 'object',
						'properties' => array(
							'name'             => array(
								'type'   => 'text',
								'fields' => array(
									'raw'      => array(
										'type' => 'keyword',
									),
									'sortable' => array(
										'type'       => 'keyword',
										'normalizer' => 'lowerasciinormalizer',
									),
								),
							),
							'term_id'          => array(
								'type' => 'long',
							),
							'term_taxonomy_id' => array(
								'type' => 'long',
							),
							'parent'           => array(
								'type' => 'long',
							),
							'slug'             => array(
								'type' => 'keyword',
							),
							'facet'            => array(
								'type' => 'keyword',
							),
							'term_order'       => array(
								'type' => 'long',
							),
						),
					),
				),
			),
			array(
				'term_suggest' => array(
					'path_match' => 'term_suggest_*',
					'mapping'    => array(
						'type'     => 'completion',
						'analyzer' => 'default',
					),
				),
			),
		),
		'properties'        => array(
			'post_id'                => array(
				'type' => 'long',
			),
			'ID'                     => array(
				'type' => 'long',
			),
			'post_tree_level'        => array(
				'type' => 'byte'
			),
			'post_tree_position'     => array(
				'type' => 'short'
			),
			'post_author'            => array(
				'type'       => 'object',
				'properties' => array(
					'display_name' => array(
						'type'   => 'text',
						'fields' => array(
							'raw'      => array(
								'type' => 'keyword',
							),
							'sortable' => array(
								'type'       => 'keyword',
								'normalizer' => 'lowerasciinormalizer',
							),
						),
					),
					'login'        => array(
						'type'   => 'text',
						'fields' => array(
							'raw'      => array(
								'type' => 'keyword',
							),
							'sortable' => array(
								'type'       => 'keyword',
								'normalizer' => 'lowerasciinormalizer',
							),
						),
					),
					'id'           => array(
						'type' => 'long',
					),
					'raw'          => array(
						'type' => 'keyword',
					),
				),
			),
			'post_date'              => array(
				'type'   => 'date',
				'format' => 'yyyy-MM-dd HH:mm:ss',
			),
			'post_date_gmt'          => array(
				'type'   => 'date',
				'format' => 'yyyy-MM-dd HH:mm:ss',
			),
			'post_title'             => array(
				'type'   => 'text',
				'fields' => array(
					'post_title' => array(
						'type' => 'text',
					),
					'raw'        => array(
						'type'         => 'keyword',
						'ignore_above' => 10922,
					),
					'sortable'   => array(
						'type'         => 'keyword',
						'ignore_above' => 10922,
						'normalizer'   => 'lowerasciinormalizer',
					)
				)
			),
			// Заголовки статей (основное + альтернативные)
			'post_titles'            => array(
				'type'       => 'nested',
				'properties' => array(
					'title'          => array(
						'type'       => 'text',
						'fields'     => array(
							'title' => array(
								'type' => 'text',
							),
							'len'   => array(
								'type'     => 'token_count',
								'analyzer' => 'c_length_analyzer',
							)
						),
						'similarity' => 'zero_bm25'
					),
					'title_lemma'    => array(
						'type'       => 'text',
						'fields'     => array(
							'title_lemma' => array(
								'type' => 'text',
							),
							'suggest'     => array(
								'type'            => 'text',
								'analyzer'        => 'edge_ngram_analyzer',
								'search_analyzer' => 'standard'
							),
							'len'         => array(
								'type'     => 'token_count',
								'analyzer' => 'c_length_analyzer',
							)
						),
						'similarity' => 'zero_bm25'
					),
					'show_in_search' => array(
						'type' => 'boolean',
					)
				)
			),
			// Заголовки статей (основное + альтернативные)
			'post_titles_flat' => array(
				'type'       => 'text',
				'similarity' => 'zero_bm25'
			),
			'post_excerpt'           => array(
				'type' => 'text',
			),
			'post_password'         => array(
				'type' => 'text',
			),
			'post_content'           => array(
				'type'        => 'text',
				'analyzer'    => 'c_text_stem',
				'term_vector' => 'with_positions_offsets',
				'similarity'  => 'custom_bm25'
			),
			'post_content_filtered' => array(
				'type' => 'text',
			),
			'post_content_lemma'     => array(
				'type'       => 'text',
				'similarity' => 'custom_bm25'
			),
			'post_status'            => array(
				'type' => 'keyword',
			),
			'post_name'              => array(
				'type'   => 'text',
				'fields' => array(
					'post_name' => array(
						'type' => 'text',
					),
					'raw'       => array(
						'type'         => 'keyword',
						'ignore_above' => 10922,
					),
				),
			),
			'post_modified'          => array(
				'type'   => 'date',
				'format' => 'yyyy-MM-dd HH:mm:ss',
			),
			'post_modified_gmt'      => array(
				'type'   => 'date',
				'format' => 'yyyy-MM-dd HH:mm:ss',
			),
			'post_parent'            => array(
				'type' => 'long',
			),
			'post_type'              => array(
				'type'   => 'text',
				'fields' => array(
					'post_type' => array(
						'type' => 'text',
					),
					'raw'       => array(
						'type' => 'keyword',
					),
				),
			),
			'post_mime_type'         => array(
				'type' => 'keyword',
			),
			'permalink'              => array(
				'type' => 'keyword',
			),
			'guid'                   => array(
				'type' => 'keyword',
			),
			'terms'                  => array(
				'type' => 'object',
			),
			'post_meta'              => array(
				'type' => 'object',
			),
			'meta'                   => array(
				'type' => 'object',
			),
			'date_terms'             => array(
				'type'       => 'object',
				'properties' => array(
					'year'          => array( // 4 digit year (e.g. 2011).
						'type' => 'integer',
					),
					'month'         => array( // Month number (from 1 to 12) alternate name 'monthnum'.
						'type' => 'integer',
					),
					'm'             => array( // YearMonth (For e.g.: 201307).
						'type' => 'integer',
					),
					'week'          => array( // Week of the year (from 0 to 53) alternate name 'w'.
						'type' => 'integer',
					),
					'day'           => array( // Day of the month (from 1 to 31).
						'type' => 'integer',
					),
					'dayofweek'     => array( // Accepts numbers 1-7 (1 is Sunday).
						'type' => 'integer',
					),
					'dayofweek_iso' => array( // Accepts numbers 1-7 (1 is Monday).
						'type' => 'integer',
					),
					'dayofyear'     => array( // Accepts numbers 1-366.
						'type' => 'integer',
					),
					'hour'          => array( // Hour (from 0 to 23).
						'type' => 'integer',
					),
					'minute'        => array( // Minute (from 0 to 59).
						'type' => 'integer',
					),
					'second'        => array( // Second (0 to 59).
						'type' => 'integer',
					),
				),
			),
			'thumbnail'              => array(
				'type'       => 'object',
				'properties' => array(
					'ID'     => array(
						'type' => 'long',
					),
					'src'    => array(
						'type' => 'text',
					),
					'width'  => array(
						'type' => 'integer',
					),
					'height' => array(
						'type' => 'integer',
					),
					'alt'    => array(
						'type' => 'text',
					),
				),
			),
		)
	),
);