<?php
/**
 * HTML-форма для фронтенд-конструктора опросников.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h1><?php _e( 'Конструктор Опросников', 'my-quiz-builder' ); ?></h1>
<p><?php _e( 'Используйте эту форму для создания или редактирования вашего опросника.', 'my-quiz-builder' ); ?></p>

<div id="quiz-message" class="my-quiz-builder-message" style="display: none;"></div>

<form id="quiz-builder-form">
    <input type="hidden" id="quiz-id-hidden" value="<?php echo esc_attr( $quiz_id ); ?>">
    <input type="hidden" id="quiz-current-status-hidden" value="<?php echo esc_attr( $current_status ); ?>">

    <div class="form-section">
        <label for="quiz-title-input"><?php _e( 'Название опросника:', 'my-quiz-builder' ); ?></label>
        <input type="text" id="quiz-title-input"
               placeholder="<?php esc_attr_e( 'Введите название опросника', 'my-quiz-builder' ); ?>"
               value="<?php echo esc_attr( $quiz_title ); ?>">
    </div>

    <h2><?php _e( 'Модальности оценки', 'my-quiz-builder' ); ?></h2>
    <p><?php _e( 'Определите категории или шкалы, по которым будут оцениваться ответы.', 'my-quiz-builder' ); ?></p>
    <div id="modality-names-container"></div>
    <button type="button" id="add-modality-name" class="button button-secondary"><?php _e( 'Добавить модальность',
			'my-quiz-builder' ); ?></button>

    <h2><?php _e( 'Вопросы опросника', 'my-quiz-builder' ); ?></h2>
    <p><?php _e( 'Добавляйте вопросы и варианты ответов с баллами для каждой модальности.', 'my-quiz-builder' ); ?></p>
    <div id="questions-container"></div>
    <button type="button" id="add-question" class="button button-secondary"><?php _e( 'Добавить вопрос',
			'my-quiz-builder' ); ?></button>

    <h2><?php _e( 'Результаты опросника', 'my-quiz-builder' ); ?></h2>
    <p><?php _e( 'Определите описания результатов для различных диапазонов баллов.', 'my-quiz-builder' ); ?></p>
    <div id="modality-results-container"></div>
    <button type="button" id="add-modality-result"
            class="button button-secondary"><?php _e( 'Добавить описание результата', 'my-quiz-builder' ); ?></button>

    <div class="submit-buttons">
        <button type="submit" name="action_type" value="save_draft"
                class="button button-large button-primary"><?php _e( 'Сохранить черновик',
				'my-quiz-builder' ); ?></button>
        <button type="submit" name="action_type" value="submit_for_review"
                class="button button-large button-success"><?php _e( 'Отправить на проверку',
				'my-quiz-builder' ); ?></button>
    </div>
</form>