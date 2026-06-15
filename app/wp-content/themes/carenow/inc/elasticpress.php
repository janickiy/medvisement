<?php

use Medvisement\Models\Disease;
use Medvisement\Models\Substance;
use ElasticPress\SearchAlgorithms;
use ElasticPress\SearchAlgorithm\Version_Medvisement;

class MedviseElasticPress {

	public function __construct() {
		$this->init();
	}

	private function init() {
		// Отключаем интеграцию
		add_filter( 'ep_skip_query_integration', array( $this, 'ep_skip_query_integration' ), 10, 2 );

		// Маппинг
		add_filter( 'ep_post_mapping_file', [ $this, 'ep_post_mapping_file' ], 10, 1 );

		// Поисковый алгоритм регистрация
		add_action( 'after_setup_theme', [ $this, 'add_search_algorithm' ], 11 );
		// Поисковый алгоритм включение
		add_filter( 'ep_post_search_algorithm', [ $this, 'post_search_algorithm' ], 10, 4 );

		// Разрешенные типы постов при индексации и в поиске
		add_filter( 'ep_indexable_post_types', [ $this, 'allowed_post_types' ] );
		add_filter( 'ep_searchable_post_types', [ $this, 'allowed_post_types' ] );

		// Изменение данных для индексации
		add_filter( 'ep_post_sync_args', [ $this, 'ep_post_sync_args' ], 10, 2 );

		// Настройки веса в админке - добавляем свои поля
		add_filter( 'ep_weighting_fields_for_post_type', [ $this, 'ep_weighting_fields_for_post_type' ], 10, 2 );

		// Поисковые подсказки - Did you mean
		add_filter( 'ep_search_suggestion_analyzer', [ $this, 'ep_search_suggestion_analyzer' ], 10, 4 );

		// Подсказки поиска должны ходить в текущий домен, а не в сохраненный старый endpoint.
		add_filter( 'ep_autosuggest_options', [ $this, 'ep_autosuggest_options' ] );

		// Настройки поиска из формы (поиск по типу статей и т.д.)
		add_filter( 'ep_formatted_args', [ $this, 'query_from_search_form' ], 999, 2 );

		// Вывод постов - подменяем контент на подсвеченное эластиком
		add_filter( 'posts_pre_query', [ $this, 'search_highlight_get_es_posts' ], 11, 2 );

		// Финальные правки запроса
		add_filter( 'ep_formatted_args', [ $this, 'final_ep_formatted_args' ], 999, 2 );
		// Это при генерации из PHP, выпадающий сделан в JS
		add_filter( 'ep_es_query_results', [ $this, 'replace_post_title_with_alternative_name' ], 10, 5 );
		// Обрабатываем результат подсветки
		add_filter( 'ep_es_query_results', [ $this, 'prepare_highlight' ], 99, 5 );
	}

	public function ep_skip_query_integration( $skip, $query ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$uri = $_SERVER['REQUEST_URI'] ?? '';

			if ( str_contains( $uri, '/wp-json/carbon-fields/v1/association/options' ) ) {
				return true;
			}
		}

		return $skip;
	}

	public function ep_post_mapping_file( $mapping_file ) {
		return THEMESFLAT_DIR . 'inc/ep/7-0.php';
	}

	public function add_search_algorithm() {
		SearchAlgorithms::factory()->register( new Version_Medvisement() );
	}

	public function post_search_algorithm( $search_algorithm, $search_text, $search_fields, $query_vars ) {
		return 'medvisement';
	}

	public static function allowed_post_types() {
		return [ 'disease', 'substance', 'custom_quiz' ];
	}

	public function ep_post_sync_args( $post_args, $post_id ) {

		$post_args['post_tree_level']    = null;
		$post_args['post_tree_position'] = null;

		// Ищем совпадения в древе заболевания или препаратов
		$disease   = Disease::where( 'post_id', $post_id )->first();
		$substance = Substance::where( 'post_id', $post_id )->first();

		// Совпадение по по болезням
		if ( ! empty( $disease ) ) {
			// Чиним на всякий случай, на самом деле это не должно быть так - почему не создаются ноды в closure?
			$disease->perfectNode();
			$post_args['post_tree_level']    = $disease->getAncestors()->count();
			$post_args['post_tree_position'] = $disease->position;
		}

		// Совпадение по по препаратам
		if ( ! empty( $substance ) ) {
			// Чиним на всякий случай
			$substance->perfectNode();

			$substance_level = $substance->getAncestors()->count();

			// Еще не задано или по уровню выше (0 самый высокий)
			if ( empty( $post_args['post_tree_level'] ) || $substance_level < $post_args['post_tree_level'] ) {
				$post_args['post_tree_level']    = $substance_level;
				$post_args['post_tree_position'] = $substance->position;
			}
		}

		// Опросник - индексируем вопросы, ответы и т.д.
		$quiz_data = get_post_meta( $post_id, '_my_quiz_builder_quiz_data', true );
		if ( ! empty( $quiz_data['questions'] ) ) {
			foreach ( $quiz_data['questions'] as $question_data ) {
				// Вопрос
				$post_args['post_content'] .= "\n" . $question_data['question_text'];
				// Ответы
				foreach ( $question_data['answers'] as $answer_data ) {
					$post_args['post_content'] .= "\n" . $answer_data['answer_text'];
				}
			}
		}
		// Результаты опросника
		if ( ! empty( $quiz_data['results'] ) ) {
			foreach ( $quiz_data['results'] as $result_data ) {
				$post_args['post_content'] .= "\n" . $result_data['description'];
			}
		}

		// Заголовки статей (основное + альтернативные)
		$post_args['post_titles']   = array();
		$post_args['post_titles'][] = array(
			'title'          => $post_args['post_title'],
			'show_in_search' => true
		);

		// Заголовки статей (основное + альтернативные) строкой
		$post_args['post_titles_flat'] = '';

		$med_article_alternative_names = carbon_get_post_meta( $post_id, 'med_article_alternative_names' );

		if ( ! empty( $med_article_alternative_names ) && is_array( $med_article_alternative_names ) ) {
			foreach ( $med_article_alternative_names as $alternative_name ) {

				$post_args['post_titles'][] = array(
					'title'          => $alternative_name['title'],
					'show_in_search' => (bool) $alternative_name['show_in_search']
				);
			}
		}

		// Удаляем дополнительную литературу спойлер
		$re                        = '/<details class="wp-block-details"><summary>(?:Источники литературы|Список литературы)[\s\S]+?<!-- \/wp:details -->/miu';
		$post_args['post_content'] = preg_replace( $re, "", $post_args['post_content'] );
		// Удаляем HTML теги кастомной функцией
		$post_args['post_content'] = es_clean_html( $post_args['post_content'] );
		// Удаляем экранированный html
		$post_args['post_content'] = preg_replace( "/&#?[a-z0-9]+;/i", "", $post_args['post_content'] );
		// Windows перенос строк откуда-то?
		$post_args['post_content'] = str_replace( "\r\n", "\n", $post_args['post_content'] );
		// Удаляем шорткоды оставшиеся
		$post_args['post_content'] = preg_replace( '#\[[^\]]+\]#', '', $post_args['post_content'] );
		// Лишние переносы строк
		$post_args['post_content'] = preg_replace( "/(\r?\n){2,}/", "\n\n", $post_args['post_content'] );

		// Лемматизация
		$lemmatize                       = new Lemmatize();
		$post_args['post_content_lemma'] = $lemmatize->lemmatize_text( $post_args['post_content'] );

		foreach ( $post_args['post_titles'] as $k => $post_title ) {
			$post_args['post_titles'][ $k ]['title_lemma'] = $lemmatize->lemmatize_text( $post_title['title'] );

			$post_args['post_titles_flat'] .= ' ' . $post_args['post_titles'][ $k ]['title_lemma'];
		}

		$post_args['post_titles_flat'] = trim( $post_args['post_titles_flat'] );

		return $post_args;
	}

	public function ep_weighting_fields_for_post_type( $fields, $post_type ) {

		//Ищем по альтернативным названиям
		if ( in_array( $post_type, [ 'substance', 'disease' ] ) ) {
			$fields['attributes']['children']['post_content_lemma'] = array(
				'key'   => 'post_content_lemma',
				'label' => 'Лемм. содержимое',
			);
		}

		return $fields;
	}

	public function ep_search_suggestion_analyzer( $search_analyzer, $formatted_args, $args, $wp_query ) {
		$search_analyzer = [
			'phrase' =>
				[
					'field'      => 'post_title',
					'max_errors' => 2,
					'collate'    => [
						'query'  => [
							'source' => [
								'match' => [
									'post_title' => [
										"query"     => "{{suggestion}}",
										"fuzziness" => "2",
										"operator"  => "and"
									]
								]
							]
						],
						'params' => [
							"field_name" => "post_title"
						],
						'prune'  => "true"
					]
				],
		];

		return $search_analyzer;
	}

	public function ep_autosuggest_options( $options ) {
		$options['endpointUrl'] = esc_url( untrailingslashit( home_url( '/ep-autosuggest' ) ) );

		return $options;
	}

	public function query_from_search_form( $formatted_args, $args ) {

		// Фильтрация по типу постов
		if ( isset( $_GET['section_name'] ) ) {
			$section_names = (array) $_GET['section_name'];

			foreach ( $section_names as $k => $section_name ) {
				if ( str_contains( $section_name, '_' ) && $section_name !== 'custom_quiz' ) {
					$section_names[ $k ] = substr( $section_name, 0, strpos( $section_name, "_" ) );
				} else {
					$section_names[ $k ] = $section_name;
				}
			}

			$section_names = array_unique( $section_names );

			// Ищем фильтр нужный и меняем
			foreach ( $formatted_args['post_filter']['bool']['must'] as $k => $v ) {
				if ( isset( $formatted_args['post_filter']['bool']['must'][ $k ]['terms']['post_type.raw'] ) ) {
					$formatted_args['post_filter']['bool']['must'][ $k ]['terms']['post_type.raw'] = array_values( $section_names );
					break;
				}
			}
		}

		// Убираем фильтр по посту, по которому не ищем
		if ( ! empty( $section_names ) ) {
			foreach ( $formatted_args['query']['bool']['should'] as $k => $v ) {
				if ( ! in_array( $v['bool']['filter'][0]['match']['post_type.raw'], $section_names ) ) {
					unset( $formatted_args['query']['bool']['should'][ $k ] );
				}
			}
		}

		// Сбрасываем порядок ключей
		$formatted_args['query']['bool']['should'] = array_values( $formatted_args['query']['bool']['should'] );

		// Фильтрация по типу статьи заболеваний
		if (
			isset( $_GET['section_name'] )
			&& ( in_array( 'disease_article', $_GET['section_name'] )
			     || in_array( 'disease_clinical-guidelines', $_GET['section_name'] )
			)
		) {
			$disease_types = (array) $_GET['section_name'];

			foreach ( $disease_types as $k => $disease_type ) {
				if ( str_contains( $disease_type, '_' ) ) {
					$disease_types[ $k ] = substr( $disease_type, ( strpos( $disease_type, "_" ) + 1 ) );
				} else {
					unset( $disease_types[ $k ] );
				}
			}

			$disease_types = array_unique( $disease_types );

			if ( ! empty( $disease_types ) ) {
				foreach ( $formatted_args['query']['bool']['should'] as $ptype_k => $ptype_v ) {
					if ( $ptype_v['bool']['filter'][0]['match']['post_type.raw'] === 'disease' ) {
						$formatted_args['query']['bool']['should'][ $ptype_k ]['bool']['must'][]['terms']['terms.article-type.slug'] =
							array_values( $disease_types );
					}
				}
			}
		}

		// Поиск по определенным специальностям
		if ( isset( $_GET['specialties'] ) ) {
			$specialties                                     = (array) $_GET['specialties'];
			$formatted_args['post_filter']['bool']['must'][] = [
				'terms' => [
					'terms.specialty.term_id' => array_values( $specialties )
				]
			];
		}

		return $formatted_args;
	}

	public function search_highlight_get_es_posts( $posts, $query ) {
		if ( ! $query->elasticsearch_success ) {
			return $posts;
		}

		foreach ( $posts as $post ) {

			if ( strpos( $post->post_content, 'ep-highlight' ) === false ) {
				$post->post_content = wp_trim_words( $post->post_content, 55, '...' );
			}

		}

		return $posts;
	}

	public function final_ep_formatted_args( $formatted_args, $args ) {

		// Переименовываем inner_hits для альтернативных названий (ES создает для каждого типа поста)
		foreach ( $formatted_args['query']['bool']['should'] as $ptype_k => $ptype_v ) {
			foreach ( $ptype_v['bool']['must'][0]['bool']['should'] as $should_k => $should_v ) {
				if ( ! isset( $should_v['nested'] ) ) {
					continue;
				}

				$post_type = $formatted_args['query']['bool']['should'][ $ptype_k ]['bool']['filter'][0]['match']['post_type.raw'];

				$formatted_args['query']['bool']['should'][ $ptype_k ]['bool']['must'][0]['bool']['should'][ $should_k ]['nested']['inner_hits']['name'] =
					$post_type . '_titles_hits';
			}
		}

		// Подсветка
		$formatted_args['highlight'] = [
			'order' => 'score',
			"fields" => [
				"post_content" => [
					'type' => 'fvh',
					'number_of_fragments' => 5,
					'fragment_size' => 200,
					'boundary_scanner' => 'word',
					'boundary_scanner_locale' => 'ru-RU',
					'pre_tags' => ["<mark class='ep-highlight'>"],
					'post_tags' => ["</mark>"],
					'highlight_query' => [
						'bool' => [
							'should' => [
								[
									'match_phrase' => [
										'post_content' => [
											'query' => $args['s'],
											'boost' => 3,
											'slop' => 2
										],
									]
								],
								[
									'match' => [
										'post_content' => [
											'query' => $args['s'],
											'analyzer' => 'c_text_stem_stop',
											'operator' => 'and'
										]
									]
								]
							]
						]
					]
				]
			]
		];

		return $formatted_args;
	}

	public function replace_post_title_with_alternative_name( $results, $response, $query, $query_args, $query_object ) {

		// Подменяем название статьи на альтернативное
		foreach ( $results['documents'] as $document_k => $document ) {
			$inner_hits_name = $document['post_type'] . '_titles_hits';
			if ( empty( $response['hits']['hits'][ $document_k ]['inner_hits'][$inner_hits_name]['hits']['hits'] ) ) {
				continue;
			}

			// Ищем первое поле с включенным show_in_search
			$matches = $response['hits']['hits'][ $document_k ]['inner_hits'][$inner_hits_name]['hits']['hits'];

			foreach ( $matches as $match ) {
				if ( $match['_source']['show_in_search'] === true ) {
					$results['documents'][ $document_k ]['post_title'] = $match['_source']['title'];
					break;
				}
			}
		}

		return $results;
	}

	public function prepare_highlight( $results, $response, $query, $query_args, $query_object ) {

		foreach ( $results['documents'] as $document_k => $document ) {

			if ( empty( $document['highlight'] ) ) {
				continue;
			}

			if ( empty( $document['highlight']['post_content'] ) ) {
				continue;
			}

			$post_content = '';

			// Удаляем дубликаты (иногда это в предисловии и потом в тексте)
			$document['highlight']['post_content'] = array_unique( $document['highlight']['post_content'] );

			foreach ( $document['highlight']['post_content'] as $k => $highlight ) {
				if ( $k === 3 ) {
					break;
				}
				$highlight = preg_replace( "/\n/i", " ", $highlight );;
				$highlight    = preg_replace( '/\s+/', ' ', $highlight );
				$highlight = trim( $highlight );
				$post_content .= $highlight . "...\n";
				$post_content = preg_replace( '/\.\.\.+/', '...', $post_content );
			}

			$results['documents'][ $document_k ]['post_content'] = $post_content;
			// ВАЖНО, иначе в дальнейшем перезапишет из этого содержимого наше форматирование
			unset( $results['documents'][ $document_k ]['highlight'] );
		}

		return $results;
	}

}

$MedviseElasticPress = new MedviseElasticPress();
