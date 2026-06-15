<?php
/**
 * Plugin Name: Medvisement - Конструктор Опросников
 * Description: Позволяет зарегистрированным пользователям создавать и управлять онлайн-опросниками и тестами.
 * Version: 3.3
 * Author: Medvisement
 * Author URI: #
 * Text Domain: medvisement-quiz-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_QUIZ_BUILDER_PLUGIN_VERSION', '1.0.1.0' );
define( 'MY_QUIZ_BUILDER_PLUGIN_FILE', __FILE__ );

class My_Quiz_Builder {

	public function __construct() {
		register_activation_hook( MY_QUIZ_BUILDER_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( MY_QUIZ_BUILDER_PLUGIN_FILE, array( $this, 'deactivate' ) );
		add_action( 'init', array( $this, 'register_custom_quiz_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_quiz_data_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_quiz_data' ) );
		add_shortcode( 'my_quiz_builder', array( $this, 'quiz_builder_shortcode' ) );
		add_action( 'wp_ajax_my_quiz_builder_save_quiz', array( $this, 'handle_save_quiz' ) );
		add_action( 'wp_ajax_nopriv_my_quiz_builder_save_quiz', array( $this, 'handle_save_quiz' ) );
		add_action( 'wp_ajax_my_quiz_builder_get_results', array( $this, 'handle_get_quiz_results' ) );
		add_action( 'wp_ajax_nopriv_my_quiz_builder_get_results', array( $this, 'handle_get_quiz_results' ) );
		add_filter( 'template_include', array( $this, 'load_custom_quiz_template' ) );
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_filter( 'manage_custom_quiz_posts_columns', array( $this, 'add_frontend_edit_column' ) );
		add_action( 'manage_custom_quiz_posts_custom_column', array( $this, 'render_frontend_edit_column' ), 10, 2 );

		// **НАЧАЛО: Код для дублирования**
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_quiz_link' ), 10, 2 );
		add_action( 'admin_action_my_quiz_builder_duplicate', array( $this, 'handle_quiz_duplication' ) );
		// **КОНЕЦ: Код для дублирования**
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'my-quiz-builder', false,
			dirname( plugin_basename( MY_QUIZ_BUILDER_PLUGIN_FILE ) ) . '/languages/' );
	}

	public function activate() {
		$this->register_custom_quiz_post_type();
		flush_rewrite_rules();
	}

	public function register_custom_quiz_post_type() {
		$labels = array(
			'name'               => _x( 'Опросники', 'Post Type General Name', 'my-quiz-builder' ),
			'singular_name'      => _x( 'Опросник', 'Post Type Singular Name', 'my-quiz-builder' ),
			'menu_name'          => __( 'Опросники', 'my-quiz-builder' ),
			'name_admin_bar'     => __( 'Опросник', 'my-quiz-builder' ),
			'all_items'          => __( 'Все опросники', 'my-quiz-builder' ),
			'add_new_item'       => __( 'Добавить новый опросник', 'my-quiz-builder' ),
			'add_new'            => __( 'Добавить новый', 'my-quiz-builder' ),
			'new_item'           => __( 'Новый опросник', 'my-quiz-builder' ),
			'edit_item'          => __( 'Редактировать опросник', 'my-quiz-builder' ),
			'view_item'          => __( 'Просмотреть опросник', 'my-quiz-builder' ),
			'search_items'       => __( 'Искать опросник', 'my-quiz-builder' ),
			'not_found'          => __( 'Не найдено', 'my-quiz-builder' ),
			'not_found_in_trash' => __( 'Не найдено в корзине', 'my-quiz-builder' ),
		);
		$args   = array(
			'label'              => __( 'Опросник', 'my-quiz-builder' ),
			'description'        => __( 'Пользовательские опросники и тесты', 'my-quiz-builder' ),
			'labels'             => $labels,
			'supports'           => array( 'title', 'editor', 'author' ),
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-forms',
			'has_archive'        => false,
			'publicly_queryable' => true,
			'capability_type'    => 'post',
			'show_in_rest'       => true,
		);
		register_post_type( 'custom_quiz', $args );
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function add_frontend_edit_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( $key === 'title' ) {
				$new_columns['frontend_edit'] = __( 'Редактор', 'my-quiz-builder' );
			}
		}

		return $new_columns;
	}

	public function render_frontend_edit_column( $column, $post_id ) {
		if ( $column === 'frontend_edit' ) {
			$edit_page_url = get_permalink( get_page_by_path( 'create-quiz' ) );

			if ( $edit_page_url ) {
				$edit_link = add_query_arg( 'quiz_id', $post_id, $edit_page_url );
				echo '<a href="' . esc_url( $edit_link ) . '" class="button button-small" target="_blank">' . __( 'Редактировать на фронте',
						'my-quiz-builder' ) . '</a>';
			} else {
				echo '<i>' . __( 'Страница /create-quiz не найдена', 'my-quiz-builder' ) . '</i>';
			}
		}
	}

	public function add_quiz_data_meta_box() {
		add_meta_box( 'my_quiz_builder_quiz_data', __( 'Данные опросника', 'my-quiz-builder' ),
			array( $this, 'render_quiz_data_meta_box' ), 'custom_quiz', 'normal', 'high' );
	}

	public function render_quiz_data_meta_box( $post ) {
		wp_nonce_field( 'my_quiz_builder_save_quiz_data', 'my_quiz_builder_quiz_data_nonce' );
		$quiz_data = get_post_meta( $post->ID, '_my_quiz_builder_quiz_data', true );
		if ( empty( $quiz_data ) ) {
			$quiz_data = array( 'modalities' => array(), 'questions' => array(), 'results' => array() );
		}

		// **ИЗМЕНЕНИЕ: Создаем текстовое поле вместо простого вывода текста**
		echo '<textarea name="my_quiz_builder_raw_json_data" id="my_quiz_builder_raw_json_data" style="width: 100%; height: 400px; font-family: monospace;">';
		echo esc_textarea( json_encode( $quiz_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		echo '</textarea>';

		echo '<p class="description">' . __( 'Это необработанные данные опросника в формате JSON. Для редактирования используйте фронтенд-конструктор.',
				'my-quiz-builder' ) . '</p>';
	}

	public function save_quiz_data( $post_id ) {
		// Стандартные проверки безопасности
		if ( ! isset( $_POST['my_quiz_builder_quiz_data_nonce'] ) || ! wp_verify_nonce( $_POST['my_quiz_builder_quiz_data_nonce'],
				'my_quiz_builder_save_quiz_data' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( get_post_type( $post_id ) !== 'custom_quiz' ) {
			return;
		}

		// **ИЗМЕНЕНИЕ: Новый код для сохранения данных из админ-панели**
		if ( isset( $_POST['my_quiz_builder_raw_json_data'] ) ) {
			// Получаем JSON-строку из нашего текстового поля
			$raw_json_data = stripslashes( $_POST['my_quiz_builder_raw_json_data'] );

			// Пытаемся декодировать JSON в массив PHP
			$decoded_data = json_decode( $raw_json_data, true );

			// Проверяем, что декодирование прошло успешно и данные не пустые
			if ( json_last_error() === JSON_ERROR_NONE && ! empty( $decoded_data ) ) {
				// Если все в порядке, обновляем мета-поле в базе данных
				update_post_meta( $post_id, '_my_quiz_builder_quiz_data', $decoded_data );
			}
		}
	}

	public function quiz_builder_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p class="my-quiz-builder-message">' . sprintf( __( 'Вы должны <a href="%s">авторизоваться</a> для создания и редактирования опросников.',
					'my-quiz-builder' ), wp_login_url( get_permalink() ) ) . '</p>';
		}

		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}

		$quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;

		$quiz_data      = array();
		$quiz_title     = '';
		$current_status = 'new';

		if ( $quiz_id > 0 ) {
			$quiz_post = get_post( $quiz_id );

			if ( $quiz_post && $quiz_post->post_type === 'custom_quiz' ) {
				$is_author    = $quiz_post->post_author == get_current_user_id();
				$is_admin     = current_user_can( 'edit_others_posts' );
				$is_published = $quiz_post->post_status === 'publish';

				if ( ! $is_admin && ! ( $is_author && ! $is_published ) ) {
					return '<p class="my-quiz-builder-message">' . __( 'У вас нет прав для редактирования этого опросника. Вы можете редактировать только свои неопубликованные опросники.',
							'my-quiz-builder' ) . '</p>';
				}

				$quiz_data      = get_post_meta( $quiz_id, '_my_quiz_builder_quiz_data', true );
				$quiz_title     = $quiz_post->post_title;
				$current_status = $quiz_post->post_status;
			} else {
				return '<p class="my-quiz-builder-message">' . __( 'Опросник не найден.', 'my-quiz-builder' ) . '</p>';
			}
		} else {
			// Это новый опросник, разрешаем создание
		}

		ob_start();
		echo '<div id="my-quiz-builder-app" data-quiz-data=\'' . esc_attr( json_encode( $quiz_data,
				JSON_UNESCAPED_UNICODE ) ) . '\'>';
		include plugin_dir_path( MY_QUIZ_BUILDER_PLUGIN_FILE ) . 'templates/frontend-builder-form.php';
		echo '</div>';

		return ob_get_clean();
	}

	public function handle_save_quiz() {
		check_ajax_referer( 'my_quiz_builder_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Вы не авторизованы.', 'my-quiz-builder' ) ) );
		}
		$quiz_id = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;

		if ( $quiz_id > 0 ) {
			$quiz_post = get_post( $quiz_id );
			if ( ! $quiz_post ) {
				wp_send_json_error( array( 'message' => __( 'Опросник не найден.', 'my-quiz-builder' ) ) );
			}
			$is_author    = $quiz_post->post_author == get_current_user_id();
			$is_admin     = current_user_can( 'edit_others_posts' );
			$is_published = $quiz_post->post_status === 'publish';

			if ( ! $is_admin && ! ( $is_author && ! $is_published ) ) {
				wp_send_json_error( array(
					'message' => __( 'У вас нет прав для сохранения этого опросника.', 'my-quiz-builder' )
				) );
			}
		}

		$quiz_title   = sanitize_text_field( $_POST['quiz_title'] );
		$quiz_data    = json_decode( stripslashes( $_POST['quiz_data'] ), true );
		$action_type  = sanitize_text_field( $_POST['action_type'] );

		$sanitized_quiz_data = array( 'modalities' => array(), 'questions' => array(), 'results' => array() );

		if ( ! empty( $quiz_data['modalities'] ) && is_array( $quiz_data['modalities'] ) ) {
			foreach ( $quiz_data['modalities'] as $modality ) {
				$sanitized_quiz_data['modalities'][] = array(
					'id'   => sanitize_text_field( $modality['id'] ),
					'name' => sanitize_text_field( $modality['name'] )
				);
			}
		}

		if ( ! empty( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] ) ) {
			foreach ( $quiz_data['questions'] as $question ) {
				$sanitized_question = array(
					'id'            => sanitize_text_field( $question['id'] ),
					'question_text' => wp_kses_post( $question['question_text'] ),
					'modality_id'   => sanitize_text_field( $question['modality_id'] ),
					'is_required'   => isset( $question['is_required'] ) ? (bool) $question['is_required'] : true,
					'answers'       => array(),
				);
				if ( ! empty( $question['answers'] ) && is_array( $question['answers'] ) ) {
					foreach ( $question['answers'] as $answer ) {
						$sanitized_question['answers'][] = array(
							'id'          => sanitize_text_field( $answer['id'] ),
							'answer_text' => wp_kses_post( $answer['answer_text'] ),
							'score'       => isset( $answer['score'] ) ? floatval( $answer['score'] ) : 0,
						);
					}
				}
				$sanitized_quiz_data['questions'][] = $sanitized_question;
			}
		}

		if ( ! empty( $quiz_data['results'] ) && is_array( $quiz_data['results'] ) ) {
			foreach ( $quiz_data['results'] as $result ) {
				$sanitized_quiz_data['results'][] = array(
					'modality_id' => sanitize_text_field( $result['modality_id'] ),
					'min_score'   => floatval( $result['min_score'] ),
					'max_score'   => sanitize_text_field( $result['max_score'] ),
					'description' => wp_kses_post( $result['description'] ),
				);
			}
		}

		$post_status = ( $action_type === 'submit_for_review' ) ? ( current_user_can( 'publish_posts' ) ? 'publish' : 'pending' ) : 'draft';

		$post_args = array(
			'post_title'   => $quiz_title,
			'post_type'    => 'custom_quiz',
			'post_status'  => $post_status,
		);

		if ( $quiz_id > 0 ) {
			$post_args['ID'] = $quiz_id;
			wp_update_post( $post_args );
		} else {
			$post_args['post_author'] = get_current_user_id();
			$quiz_id                  = wp_insert_post( $post_args );
		}

		update_post_meta( $quiz_id, '_my_quiz_builder_quiz_data', $sanitized_quiz_data );

		$message = ( $post_status === 'draft' ) ? __( 'Опросник сохранен как черновик!',
			'my-quiz-builder' ) : __( 'Опросник отправлен на проверку!', 'my-quiz-builder' );
		if ( $post_status === 'publish' ) {
			$message = __( 'Опросник опубликован!', 'my-quiz-builder' );
		}

		wp_send_json_success( array(
			'message' => $message,
			'quiz_id' => $quiz_id,
			'status'  => get_post_field( 'post_status', $quiz_id ),
		) );
	}

	public function handle_get_quiz_results() {
		check_ajax_referer( 'my_quiz_builder_nonce', 'nonce' );

		$quiz_id              = isset( $_POST['quiz_id'] ) ? intval( $_POST['quiz_id'] ) : 0;
		$selected_answers_raw = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? $_POST['answers'] : array();

		if ( $quiz_id === 0 ) {
			wp_send_json_error( array( 'message' => __( 'ID опросника не указан.', 'my-quiz-builder' ) ) );
		}

		$quiz_data = get_post_meta( $quiz_id, '_my_quiz_builder_quiz_data', true );

		if ( empty( $quiz_data ) || ! isset( $quiz_data['questions'], $quiz_data['modalities'], $quiz_data['results'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Данные опросника не найдены или некорректны.', 'my-quiz-builder' )
			) );
		}

		$questions_data  = $quiz_data['questions'];
		$modalities_data = $quiz_data['modalities'];
		$results_data    = $quiz_data['results'];
		$modality_scores = array_fill_keys( array_column( $modalities_data, 'id' ), 0 );

		foreach ( $questions_data as $question ) {
			if ( ! empty( $question['is_required'] ) && ! isset( $selected_answers_raw[ $question['id'] ] ) ) {
				wp_send_json_error( array(
					'message' => sprintf( __( 'Пожалуйста, ответьте на все обязательные вопросы. Вопрос "%s" не отвечен.',
						'my-quiz-builder' ), wp_strip_all_tags( $question['question_text'] ) )
				) );
			}
		}

		foreach ( $selected_answers_raw as $question_id => $selected_answer_id ) {
			$found_question = null;
			foreach ( $questions_data as $q ) {
				if ( $q['id'] === $question_id ) {
					$found_question = $q;
					break;
				}
			}

			if ( $found_question && ! empty( $found_question['answers'] ) ) {
				$question_modality_id = $found_question['modality_id'];

				foreach ( $found_question['answers'] as $answer ) {
					if ( $answer['id'] === $selected_answer_id ) {
						if ( isset( $modality_scores[ $question_modality_id ] ) ) {
							$modality_scores[ $question_modality_id ] += floatval( $answer['score'] );
						}
						break;
					}
				}
			}
		}

		$calculated_results = array();
		foreach ( $modality_scores as $modality_id => $score ) {
			$modality_name = '';
			foreach ( $modalities_data as $modality ) {
				if ( $modality['id'] === $modality_id ) {
					$modality_name = $modality['name'];
					break;
				}
			}

			$description = __( 'Описание для вашего результата не найдено.', 'my-quiz-builder' );
			foreach ( $results_data as $result ) {
				if ( $result['modality_id'] === $modality_id ) {
					$min_score     = floatval( $result['min_score'] );
					$max_score_raw = trim( $result['max_score'] );

					$is_in_range = ( $score >= $min_score );

					if ( ! empty( $max_score_raw ) && $max_score_raw !== '-' ) {
						$is_in_range = $is_in_range && ( $score <= floatval( $max_score_raw ) );
					}

					if ( $is_in_range ) {
						$description = wp_kses_post( $result['description'] );
						break;
					}
				}
			}

			$calculated_results[] = array(
				'modality_name' => $modality_name,
				'score'         => round( $score, 2 ),
				'description'   => $description,
			);
		}

		wp_send_json_success( array( 'results' => $calculated_results ) );
	}

	public function load_custom_quiz_template( $template ) {
		if ( is_singular( 'custom_quiz' ) ) {
			$plugin_template = plugin_dir_path( MY_QUIZ_BUILDER_PLUGIN_FILE ) . 'templates/single-custom_quiz.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	public function enqueue_frontend_assets() {
		if (
			is_page_template( 'page-templates/template-full-width.php' )
			|| is_page( 'edit-quiz' )
			|| ( is_page() && has_shortcode( get_the_content(), 'my_quiz_builder' ) )
		) {

			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style(
				'my-quiz-builder-frontend-style',
				plugin_dir_url( MY_QUIZ_BUILDER_PLUGIN_FILE ) . 'assets/css/frontend-builder.css',
				array(),
				MY_QUIZ_BUILDER_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'my-quiz-builder-frontend-script',
				plugin_dir_url( MY_QUIZ_BUILDER_PLUGIN_FILE ) . 'assets/js/frontend-builder.js',
				array( 'jquery', 'wp-editor', 'jquery-ui-sortable' ),
				MY_QUIZ_BUILDER_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'my-quiz-builder-frontend-script',
				'myQuizBuilderAjax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'my_quiz_builder_nonce' ),
					'messages' => array(
						'error_title_empty'          => __( 'Название опросника не может быть пустым.',
							'my-quiz-builder' ),
						'error_no_questions'         => __( 'Опросник должен содержать хотя бы один вопрос.',
							'my-quiz-builder' ),
						'error_no_answers'           => __( 'Каждый вопрос должен содержать хотя бы один ответ.',
							'my-quiz-builder' ),
						'error_no_modalities'        => __( 'Должна быть определена хотя бы одна модальность оценки.',
							'my-quiz-builder' ),
						'error_question_no_modality' => __( 'Каждый вопрос должен быть привязан к модальности.',
							'my-quiz-builder' ),
						'error_no_results'           => __( 'Должно быть определено хотя бы одно описание результата.',
							'my-quiz-builder' ),
					)
				) );
		}

		if ( is_singular( 'custom_quiz' ) ) {

			wp_enqueue_style(
				'my-quiz-builder-quiz-runner-style',
				plugin_dir_url( MY_QUIZ_BUILDER_PLUGIN_FILE ) . 'assets/css/quiz-runner.css',
				array(),
				MY_QUIZ_BUILDER_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'my-quiz-builder-quiz-runner-script',
				plugin_dir_url( MY_QUIZ_BUILDER_PLUGIN_FILE ) . 'assets/js/quiz-runner.js',
				array( 'jquery' ),
				MY_QUIZ_BUILDER_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'my-quiz-builder-quiz-runner-script',
				'myQuizRunnerAjax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'my_quiz_builder_nonce' ),
					'quiz_id'  => get_the_ID(),
					'messages' => array(
						'getting_results'       => __( 'Получение результатов...', 'my-quiz-builder' ),
						'get_result_button'     => __( 'Получить результат', 'my-quiz-builder' ),
						'error_getting_results' => __( 'Произошла ошибка при получении результатов.',
							'my-quiz-builder' ),
						'score_label'           => __( 'баллов', 'my-quiz-builder' ),
						'no_results_found'      => __( 'Не удалось определить результаты.', 'my-quiz-builder' ),
						'your_results'          => __( 'Ваши результаты:', 'my-quiz-builder' ),
					)
				)
			);
		}
	}

	public function enqueue_admin_assets() {
	}

	/**
	 * **НОВАЯ ФУНКЦИЯ**
	 * Добавляет ссылку "Дублировать" в список опросников в админ-панели.
	 */
	public function add_duplicate_quiz_link( $actions, $post ) {
		if ( $post->post_type === 'custom_quiz' && current_user_can( 'edit_posts' ) ) {
			$duplicate_url        = wp_nonce_url( add_query_arg( array(
				'action' => 'my_quiz_builder_duplicate',
				'post'   => $post->ID,
			), 'admin.php' ), 'my_quiz_builder_duplicate_nonce', 'nonce' );
			$actions['duplicate'] = '<a href="' . esc_url( $duplicate_url ) . '">' . __( 'Дублировать',
					'my-quiz-builder' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * **НОВАЯ ФУНКЦИЯ**
	 * Обрабатывает действие дублирования опросника.
	 */
	public function handle_quiz_duplication() {
		if ( ! isset( $_GET['post'] ) || ! isset( $_GET['nonce'] ) ) {
			wp_die( __( 'Отсутствуют необходимые параметры.', 'my-quiz-builder' ) );
		}

		// Проверка nonce для безопасности
		if ( ! wp_verify_nonce( $_GET['nonce'], 'my_quiz_builder_duplicate_nonce' ) ) {
			wp_die( __( 'Ошибка безопасности.', 'my-quiz-builder' ) );
		}

		// Проверка прав пользователя
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'У вас нет прав для выполнения этого действия.', 'my-quiz-builder' ) );
		}

		$post_id_to_duplicate = absint( $_GET['post'] );
		$post_to_duplicate    = get_post( $post_id_to_duplicate );

		if ( ! $post_to_duplicate || $post_to_duplicate->post_type !== 'custom_quiz' ) {
			wp_die( __( 'Опросник для дублирования не найден.', 'my-quiz-builder' ) );
		}

		// Получаем данные опросника
		$quiz_data = get_post_meta( $post_id_to_duplicate, '_my_quiz_builder_quiz_data', true );

		// Подготавливаем данные для нового поста
		$new_post_args = array(
			'post_title'   => $post_to_duplicate->post_title . ' (' . __( 'Копия', 'my-quiz-builder' ) . ')',
			'post_content' => $post_to_duplicate->post_content,
			'post_type'    => 'custom_quiz',
			'post_status'  => 'draft', // Создаем как черновик
			'post_author'  => get_current_user_id(),
		);

		// Создаем новый пост (опросник)
		$new_post_id = wp_insert_post( $new_post_args );

		if ( $new_post_id && ! is_wp_error( $new_post_id ) ) {
			// Если пост успешно создан, копируем данные опросника
			if ( ! empty( $quiz_data ) ) {
				update_post_meta( $new_post_id, '_my_quiz_builder_quiz_data', $quiz_data );
			}
			// Перенаправляем пользователя обратно на страницу со списком опросников
			wp_redirect( admin_url( 'edit.php?post_type=custom_quiz' ) );
			exit;
		} else {
			wp_die( __( 'Не удалось создать копию опросника.', 'my-quiz-builder' ) );
		}
	}
}

new My_Quiz_Builder();