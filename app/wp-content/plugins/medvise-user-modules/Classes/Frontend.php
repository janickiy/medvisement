<?php


namespace MedviseUserModules;

use MedviseSubscriptions\Subscriber\Subscriber;
use MedviseUserModules\Note;
use MedviseUserModules\Template;

class Frontend {

	private $allowed_posts = [ 'substance', 'disease' ];

	public static function getInstance() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		add_action( 'wp_enqueue_scripts', [ $this, 'load_scripts' ] );

		//Шорткод
		add_shortcode( 'medvise_user_notes_templates_container', [ $this, 'notes_templates_container_html' ] );

	}

	public function notes_templates_container_html( $atts ) {

		global $post;

		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( ! in_array( $post->post_type, $this->allowed_posts ) || ! Subscriber::hasAccess($post) ) {
			return '';
		}

		$allowed_tags = "<p><br><strong><em><span><ul><ol><li><a><table><tbody><tr><th><td>";
		$current_user = wp_get_current_user();
		
		$notes = Note::get( $current_user->ID, $post->ID);

		$original_post_content = preg_replace( '/<!--(?:.*?)-->/', '', $post->post_content );
		$original_post_content = strip_tags( $original_post_content, $allowed_tags );
		$original_post_content = trim( $original_post_content );

		wp_enqueue_editor();

		ob_start();
		?>
        <script type="text/javascript">
            <?php
            $medvise_um = [
	            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
	            'post_id'  => $post->ID,
	            'nonce'    => wp_create_nonce( 'um-nonce' ),
	            'notes'    => [
		            'title'            => $notes['title'],
		            'content'          => $notes['content'],
		            'content_original' => $original_post_content,
		            'replace_original' => (int) $notes['replace_original']
	            ],
	            'template' => [
		            'limit' => Template::getUserLimit(),
		            'items' => Template::get( $current_user->ID, $post->ID )
	            ]
            ];
            ?>
            window.medvise_um = <?= json_encode( $medvise_um, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
        </script>
        <div class="user-modules_container">

            <div id="user-modules_left" class="user-modules_left">
                <div id="user-modules_templates" class="user-modules_templates"></div>
            </div>

            <div id="user-modules_right" class="user-modules_right">

	            <?php if ( Subscriber::hasSubscription() ): ?>
                    <div id="user-modules_notes" class="user-modules_notes"></div>
	            <?php endif; ?>

                <div id="user-modules_timer" class="user-modules_timer">
                    <!-- todo запоминать таймер при обновлении страницы? -->
                    <div class="modules_timer__values">00:00:00</div>
                    <button class="modules_timer__start">Старт</button>
                    <button class="modules_timer__pause">Пауза</button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="umTemplateModal" data-bs-backdrop="static" tabindex="-1"
             aria-labelledby="umTemplateModalLabel" aria-hidden="true" expired="1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <input type="hidden" name="template_id">
                        <input type="text" name="title">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть">
                            <i class="fa fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="modal-body">

                        <textarea id="um-template-editor"></textarea>

                        <div class="um-controls">
                            <div class="um-controls_left">
                                <button class="um-controls_delete">Удалить</button>
                            </div>
                            <div class="um-controls_right">
                                <div class="loader-icon hidden"></div>
                                <button class="um-controls_save">Сохранить</button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="umNotesModal" data-bs-backdrop="static" tabindex="-1"
             aria-labelledby="umNotesLabel" aria-hidden="true" expired="1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Заметки по статье</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть">
                            <i class="fa fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="modal-body">

                        <textarea id="um-notes-editor"></textarea>

                        <div class="um-controls">
                            <div class="um-controls_left">
                                <button class="um-controls_delete">Удалить</button>
                                <button class="um-controls_copy">Вставить оригинал</button>
                            </div>
                            <div class="um-controls_right">

                                <div class="form-check-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" name="replace_original">
                                        <label class="form-check-label" for="replace_original">
                                            Заменять оригинал
                                        </label>
                                    </div>
                                    <div class="loader-icon hidden"></div>
                                </div>

                                <button class="um-controls_save">Сохранить</button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
		<?php
		return ob_get_clean();
	}

	public function load_scripts() {
		wp_enqueue_style( 'medvise_um-front', MEDVISE_USER_MODULES_URL . 'assets/front.css', [], MEDVISE_USER_MODULES_VERSION );

		wp_enqueue_script( 'easytimer', MEDVISE_USER_MODULES_URL . 'assets/easytimer.min.js', [ 'jquery' ], MEDVISE_USER_MODULES_VERSION, TRUE );

		wp_enqueue_script( 'medvise_um-front', MEDVISE_USER_MODULES_URL . 'assets/front.js', [
			'jquery',
			'easytimer',
            'bootstrap',
            'wp-tinymce-root',
            'wp-tinymce'
		], MEDVISE_USER_MODULES_VERSION, TRUE );
	}
}