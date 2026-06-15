<?php
/**
 * Template Name: Шаблон одного опросника
 * Template Post Type: custom_quiz
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'my-quiz-builder-quiz-runner-style' );
wp_enqueue_script( 'my-quiz-builder-quiz-runner-script' );

global $post;

$quiz_data = get_post_meta( $post->ID, '_my_quiz_builder_quiz_data', true );

get_header();

$quiz_content = '';
ob_start();
if ( ! empty( $quiz_data['questions'] ) && is_array( $quiz_data['questions'] ) ) : ?>
    <div id="quiz-container">
        <form id="my-quiz-form">
            <div class="quiz-questions-list">
				<?php foreach ( $quiz_data['questions'] as $question ) :
					$is_required = ! empty( $question['is_required'] );
					?>
                    <div class="quiz-question-item"
                         data-question-id="<?php echo esc_attr( $question['id'] ); ?>"
                         data-is-required="<?php echo $is_required ? 'true' : 'false'; ?>">
                        <p class="question-text"><?php echo wp_kses_post( $question['question_text'] ); ?></p>
                        <div class="quiz-required-message" style="display: none;">
							<?php _e( 'Пожалуйста, выберите ответ на этот обязательный вопрос.',
								'my-quiz-builder' ); ?>
                        </div>
                        <div class="quiz-answers-list">
							<?php if ( ! empty( $question['answers'] ) && is_array( $question['answers'] ) ): ?>
								<?php foreach ( $question['answers'] as $answer ) : ?>
                                    <label class="quiz-answer">
                                        <input type="radio"
                                               name="<?php echo esc_attr( $question['id'] ); ?>"
                                               value="<?php echo esc_attr( $answer['id'] ); ?>"
                                        >
                                        <span><?php echo wp_kses_post( $answer['answer_text'] ); ?></span>
                                    </label>
								<?php endforeach; ?>
							<?php endif; ?>
                        </div>
                    </div>
				<?php endforeach; ?>
            </div>
            <button type="submit" id="get-quiz-result"
                    class="button button-primary"><?php _e( 'Получить результат',
					'my-quiz-builder' ); ?></button>
        </form>
        <div id="quiz-results-display" class="my-quiz-builder-quiz-results"
             style="display: none;">
            <h3><?php _e( 'Ваши результаты:', 'my-quiz-builder' ); ?></h3>
            <div class="results-content">
            </div>
            <div class="quiz-print-actions">
                <button id="print-quiz-results"
                        class="button"><?php _e( 'Распечатать результаты',
						'my-quiz-builder' ); ?></button>
                <button id="print-full-quiz"
                        class="button"><?php _e( 'Распечатать весь опросник',
						'my-quiz-builder' ); ?></button>
            </div>
        </div>
    </div>
<?php endif;
$quiz_content .= ob_get_clean();

?>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="wrap-content-area">
                    <div id="primary" class="content-area">
                        <main id="main" class="main-content" role="main">

                            <h1 class="entry-title"><?php echo nl2br( esc_html( $post->post_title ) ); ?></h1>

                            <article
                                    id="post-<?= $post->ID; ?>" <?php post_class( 'my-quiz-builder-single-quiz-wrapper' ); ?>>
                                <div class="entry-content">
									<?php
									if ( str_contains( $post->post_content, '[my_quiz_force_position]' ) ) {
										echo str_replace( '[my_quiz_force_position]', $quiz_content, $post->post_content );
									} else {
										echo $post->post_content;
										echo $quiz_content;
									}
									?>
                                </div>
                            </article>

                        </main>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
get_sidebar();
get_footer();