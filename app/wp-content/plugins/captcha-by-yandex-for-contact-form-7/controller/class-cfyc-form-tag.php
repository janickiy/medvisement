<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CFYC_Form_Tag' ) ) {
	class CFYC_Form_Tag {

		const PANEL_BUTTON_NAME = 'Yandex Captcha';

		private static CFYC_Form_Tag $instance;

		public static function get_instance(): CFYC_Form_Tag {
			if ( empty( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Show this in popup when user does not configure plugin and tries to use it
		 *
		 * @return void
		 */
		public function cfyc_tag_generator_misconfigured(): void {
			$url = menu_page_url( 'wpcf7-integration', false );
			$url = add_query_arg( array(
				'service' => 'cfyc',
				'action'  => 'setup'
			), $url );
			/* translators: %s: url */
			$confText = sprintf( __( 'Please configure the plugin first. Go to <a href="%s">configuration</a>', 'captcha-by-yandex-for-contact-form-7' ), $url );

			?>
            <div class="alert-box">
				<?php echo wp_kses( $confText, array( 'a' => array( 'href' => true ) ) ) ?>
            </div>
			<?php
		}

		/**
		 * Backend: Add insert Yandex Captcha button (WP Admin > Contact Forms 7 > select one > see buttons at the top)
		 */
		public function cfyc_add_form_template_tag(): void {
			$service = CFYC_Service::get_instance();

			$tag_generator = WPCF7_TagGenerator::get_instance();
			$tag_generator->add(
				CFYC_Common::TAG_NAME,
				__( 'Yandex Captcha', 'captcha-by-yandex-for-contact-form-7' ),
				$service->is_active() ? array( $this, 'cfyc_tag_generator_configured' ) : array( $this, 'cfyc_tag_generator_misconfigured' ),
				array(
					'name-attr' => true,
					'version'   => '2'
				)
			);
		}

		function cfyc_tag_generator_configured( $contact_form, $options ) {
			$tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );
			?>
            <header class="description-box">
                <h3><?php echo esc_html( __( 'Yandex SmartCaptcha', 'captcha-by-yandex-for-contact-form-7' ) ); ?></h3>
                <p>
					<?php echo wp_kses(
						__( 'Добавьте капчу Яндекс в вашу форму. Выберите нужные опции ниже.', 'captcha-by-yandex-for-contact-form-7' ),
						array(
							'a'      => array( 'href' => true ),
							'strong' => array(),
						),
						array( 'http', 'https' )
					); ?>
                </p>
            </header>

            <div class="control-box">
                <fieldset style="display:none;">
                    <legend id="<?php echo esc_attr( $tgg->ref( 'type-legend' ) ); ?>"><?php
                        echo esc_html( __( 'Field type', 'contact-form-7' ) );
                    ?></legend>
                    <select data-tag-part="basetype" aria-labelledby="<?php echo esc_attr( $tgg->ref( 'type-legend' ) ); ?>">
                        <option value="<?php echo esc_attr( CFYC_Common::TAG_NAME ); ?>">
                            <?php echo esc_html( __( 'Yandex Captcha', 'captcha-by-yandex-for-contact-form-7' ) ); ?>
                        </option>
                    </select>
                </fieldset>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr( $tgg->ref( 'name-legend' ) ); ?>">
                                    <?php echo esc_html( __( 'Имя поля', 'captcha-by-yandex-for-contact-form-7' ) ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" data-tag-part="name" pattern="[A-Za-z][A-Za-z0-9_\-]*" aria-labelledby="<?php echo esc_attr( $tgg->ref( 'name-legend' ) ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php echo esc_html( __( 'Опции', 'captcha-by-yandex-for-contact-form-7' ) ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" data-tag-part="option" data-tag-option="test"/>
                                    <?php echo esc_html( __( 'Включить режим тестирования', 'captcha-by-yandex-for-contact-form-7' ) ); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" data-tag-part="option" data-tag-option="invisible"/>
                                    <?php echo esc_html( __( 'Использовать невидимую капчу', 'captcha-by-yandex-for-contact-form-7' ) ); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" data-tag-part="option" data-tag-option="hideShield"/>
                                    <?php echo esc_html( __( 'Скрыть блок Обработка Данных', 'captcha-by-yandex-for-contact-form-7' ) ); ?>
                                </label>
                                <br>
                                <i><?php echo esc_html( __( 'Вы обязаны уведомлять пользователей о том, что их данные обрабатывает SmartCaptcha. Если вы скрываете блок с уведомлением, сообщите пользователям иным способом о том, что SmartCaptcha обрабатывает их данные.', 'captcha-by-yandex-for-contact-form-7' ) ); ?></i>
                                <br><br>
                                <fieldset style="border: 1px solid;padding: 1em;">
                                    <legend><?php echo esc_html( __( 'Положение блока Обработка Данных:', 'captcha-by-yandex-for-contact-form-7' ) ); ?></legend>
                                    <div><label><input type="radio" data-tag-part="option" data-tag-option="shieldPosition:top-left" name="shieldPosition"/>top-left</label></div>
                                    <div><label><input type="radio" data-tag-part="option" data-tag-option="shieldPosition:center-left" name="shieldPosition"/>center-left</label></div>
                                    <div><label><input type="radio" data-tag-part="option" data-tag-option="shieldPosition:bottom-left" name="shieldPosition"/>bottom-left</label></div>
                                    <div><label><input type="radio" data-tag-part="option" data-tag-option="shieldPosition:top-right" name="shieldPosition"/>top-right</label></div>
                                    <div><label><input type="radio" data-tag-part="option" data-tag-option="shieldPosition:center-right" name="shieldPosition" checked/>center-right</label></div>
                                    <div><label><input type="radio" data-tag-part="option" data-tag-option="shieldPosition:bottom-right" name="shieldPosition"/>bottom-right</label></div>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php echo esc_html( __( 'Атрибут class', 'contact-form-7' ) ); ?></label>
                            </th>
                            <td>
                                <?php $tgg->print( 'class_attr' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="insert-box">
				<?php $tgg->print( 'insert_box_content' ); ?>
            </footer>
			<?php
		}

		/**
		 * Automatically make insert captcha tag without an extra dialog in popup
		 *
		 * @return void
		 */
		public function cfyc_tag_generator_configured_autoclick(): void {
			?>
            <script>jQuery(function ($) {
                $("a:contains('<?php echo esc_attr( self::PANEL_BUTTON_NAME ) ?>')").click(function () {
                  wpcf7.taggen.insert("[<?php echo esc_attr( CFYC_Common::TAG_NAME ) ?>]")
                })
                $(document).on('DOMNodeInserted', function () {
                  if ($('#TB_window .insert-box-cfyc').length) {
                    tb_remove()
                  }
                })
              })</script>
            <div class="insert-box insert-box-cfyc">
                <input type="text" name="<?php echo esc_attr( CFYC_Common::TAG_NAME ) ?>" class="tag code" readonly="readonly" value="<?php echo esc_attr( '[' . CFYC_Common::TAG_NAME . ']' ) ?>"/>
                <div class="submitbox">
                    <input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ) ?>"/>
                </div>
            </div>
			<?php
		}
	}
}
