<?php

namespace ElasticPress\SearchAlgorithm;

use Lemmatize;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Version_Medvisement extends \ElasticPress\SearchAlgorithm {
	/**
	 * Search algorithm slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'medvisement';
	}

	/**
	 * Search algorithm name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Medvisement';
	}

	/**
	 * Search algorithm description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Medvisement алгоритм поиска.';
	}

	/**
	 * Return the Elasticsearch `query` clause.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term Search term(s)
	 * @param array $search_fields Search fields
	 * @param array $query_vars Query vars
	 *
	 * @return array ES `query`
	 */
	protected function get_raw_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ): array {

		$lemmatizer       = new Lemmatize();
		$lemm_search_term = $lemmatizer->lemmatize_text( $search_term );

		$query = [
			'bool' => [
				'should' => [
					// Поиск по заголовкам (основной + альтернативные) с учетом общей длины
					[
						'nested' =>
							array(
								'path'       => 'post_titles',
								'score_mode' => 'max',
								'query'      =>
									array(
										'function_score' =>
											array(
												'query'      => [
													'bool' => [
														'should' => [
															[
																'match_phrase' => [
																	'post_titles.title_lemma.title_lemma' =>
																		[
																			'query' => $lemm_search_term,
																			'boost' => 4
																		],
																]
															],
															[
																'match' => [
																	'post_titles.title_lemma.title_lemma' => [
																		'query'    => $lemm_search_term,
																		'analyzer' => 'c_text_stop',
																		'operator' => 'and',
																		'boost'    => 1.5
																	],
																],
															],
															[
																'match' => [
																	'post_titles.title_lemma.suggest' => [
																		'query'    => $search_term,
																		'analyzer' => 'c_text_stop',
																		'operator' => 'and'
																	],
																]
															]
														]
													],
												],
												'boost'      => 6,
												'functions'  =>
													array(
														0 =>
															array(
																'weight' => 10,
															),
														1 =>
															array(
																'field_value_factor' =>
																	array(
																		'field'    => 'post_titles.title_lemma.len',
																		'modifier' => 'reciprocal',
																		'factor'   => 0.5,
																		'missing'  => 1,
																	),
															),
													),
												'boost_mode' => 'multiply',
												'score_mode' => 'multiply'
											),
									),
								'inner_hits' => array(
									'name'      => 'titles_hits',
									'size'      => 5,
									'highlight' => array(
										'fields' => array(
											'post_titles.title_lemma' => array(
												'fragment_size'       => 150,
												'number_of_fragments' => 10
											)
										)
									)
								)
							)
					],
					// Если слова в разных заголовках содержатся
					[
						'match' => [
							'post_titles_flat' => [
								'query'    => $lemm_search_term,
								'analyzer' => 'c_text_stop',
								'operator' => 'and',
								'boost'    => 3,
							],
						]
					],
					[
						'multi_match' => [
							'query'  => $lemm_search_term,
							'type'   => 'phrase',
							'slop'  => 2,
							'fields' => $search_fields,
							'boost'  => 3,
						],
					],
					[
						'multi_match' => [
							'query'    => $lemm_search_term,
							'type'     => 'phrase_prefix',
							'slop'    => 2,
							'fields'   => $search_fields,
							'operator' => 'and',
							'boost'    => 2,
						],
					],
					[
						'multi_match' => [
							'query'     => $lemm_search_term,
							'fields'    => $search_fields,
							'analyzer'  => 'c_text_stop',
							'operator'  => 'and',
							'boost'     => 1,
							'fuzziness' => 0,
						],
					],
					[
						'multi_match' => [
							'query'       => $lemm_search_term,
							'type'        => 'cross_fields',
							'fields'      => $search_fields,
							'boost'       => 1,
							'analyzer'    => 'c_text_stop',
							'tie_breaker' => 0.5,
							'operator'    => 'and',
						],
					],
				],
			],
		];

		return $query;
	}
}