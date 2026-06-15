<?php

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\ValidationException;
use MedviseMoneyPot\Helper;

class Medviselogin_Frontend
{

	public function __construct()
	{
		$this->init();
	}

	public function init()
	{
		add_shortcode('medvise_loginform', [$this, 'render_loginform']);
		add_shortcode('medvise_loginbutton', [$this, 'render_loginbutton']);

		add_shortcode('medvise_registrationform', [$this, 'render_registrationform']);

		//Подгрузка QR кода
		add_action( 'wp_ajax_nopriv_medvise_telegramqr', [$this, 'get_telegram_qr_ajax'] );
		add_action( 'wp_ajax_medvise_telegramqr', [$this, 'get_telegram_qr_ajax'] );

		add_action( 'wp_ajax_nopriv_ajaxlogin', [ $this, 'ajax_login'] );
		add_action( 'wp_ajax_nopriv_ajaxregistration', [ $this, 'ajax_registration'] );
		add_action( 'wp_ajax_nopriv_medvise_email_login_code_send', [ $this, 'ajax_email_login_code_send'] );
		add_action( 'wp_ajax_nopriv_medvise_email_login_code_verify', [ $this, 'ajax_email_login_code_verify'] );

		// Ссылка ошибка сброса пароля
		add_filter( 'gettext_default', [ $this, 'gettext_wp_login' ], 10, 3 );
	}

	public function render_loginform() {
		if ( is_user_logged_in() ) {
			return TRUE;
		}

		$active_form = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : 'email';
		if ( 'email-code' === $active_form ) {
			$active_form = 'email';
		}
		if ( ! in_array( $active_form, [ 'email', 'password', 'telegram' ], true ) ) {
			$active_form = 'email';
		}

		?>
		<div class="login-form">

			<div class="vertical-selector__wrapper">
                <div class="vertical-selector__input">
                    <input type="radio" name="type" id="type_email" value="email"
						<?= 'email' === $active_form ? 'checked' : '' ?>>
                    <label for="type_email">Email</label>
                </div>
				<div class="vertical-selector__input">
					<input type="radio" name="type" id="type_password" value="password"
						<?= 'password' === $active_form ? 'checked' : '' ?>>
					<label for="type_password">Пароль</label>
				</div>
				<div class="vertical-selector__input">
					<input type="radio" name="type" id="type_telegram" value="telegram"
						<?= 'telegram' === $active_form ? 'checked' : '' ?>>
                    <label for="type_telegram">Telegram</label>
				</div>
			</div>

			<form id="login-form__email" class="login-form__email"
				  style="<?= 'email' === $active_form ? 'display:block;' : '' ?>">

				<div class="login-form__email-code-request">
					<p class="login-form__email-code-intro">
						Укажите ваш email, и мы пришлем 6-значный код подтверждения для входа.
					</p>

					<input type="email" name="email" placeholder="Введите Email" autocomplete="email"
						   value="<?= empty($_GET['registered_email']) ? '' : esc_html($_GET['registered_email']); ?>">

					<button class="themesflat-button js-email-login-code-send" type="button">
						<span>Получить код</span>
						<i class="fa-solid fa-arrow-right"></i>
					</button>
				</div>

				<?php wp_nonce_field( 'ajax-login-nonce', 'security' ); ?>

				<div class="alert" role="alert" style="display:none;"></div>

				<div class="login-form__email-code-confirm" style="display:none;">
					<p class="login-form__email-code-notice">
						Если такая почта зарегистрирована, на нее отправлен 6-значный код, введите код в окошке ниже.
					</p>

					<div class="login-form__code-digits" aria-label="6-значный код подтверждения">
						<input class="login-form__code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="Первая цифра кода">
						<input class="login-form__code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Вторая цифра кода">
						<input class="login-form__code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Третья цифра кода">
						<input class="login-form__code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Четвертая цифра кода">
						<input class="login-form__code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Пятая цифра кода">
						<input class="login-form__code-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Шестая цифра кода">
						<input type="hidden" name="email_code" value="">
					</div>

					<p class="login-form__email-code-hint">
						Если не можете найти письмо, то проверьте папки "спам", "рассылки", "промо-акции".
					</p>

					<button class="themesflat-button js-email-login-code-verify" type="button">
						<span>Войти</span>
						<i class="fa-solid fa-right-to-bracket"></i>
					</button>

					<a href="#" class="login-form__resend-link js-email-login-code-resend">
						Отправить код повторно <span class="js-email-login-code-timer">(5:00)</span>
					</a>

					<a href="#" class="login-form__change-email js-email-login-code-change-email">
						Изменить email
					</a>
				</div>
			</form>

			<form id="login-form__password" class="login-form__password"
                  style="<?= 'password' === $active_form ? 'display:block;' : '' ?>">

				<input type="email" name="email" placeholder="Email" autocomplete="email"
                       value="<?= empty($_GET['registered_email']) ? '' : esc_html($_GET['registered_email']); ?>">

				<div class="login-form__password-wrap">
					<input type="password" name="password" placeholder="Пароль" autocomplete="on">
					<i class="fa-solid fa-eye"></i>
				</div>

				<?php wp_nonce_field( 'ajax-login-nonce', 'security' ); ?>

                <?php if ( empty($_GET['registered_email']) ): ?>
                    <div class="alert" role="alert" style="display:none;"></div>
                <?php else: ?>
                    <div class="alert alert-warning" role="alert">
                        Введите пароль, который был отправлен на указанную вами почту.
                    </div>
                <?php endif; ?>

				<button class="themesflat-button" type="submit">
					<span>Вход</span>
					<i class="fa-solid fa-right-to-bracket"></i>
				</button>

				<a class="themesflat-button forgot-password" href="/wp-login.php?action=lostpassword">
					Забыли пароль?
				</a>
			</form>

            <form id="login-form__telegram" class="login-form__telegram" expired="1"
                  style="<?= isset( $_GET['form'] ) && $_GET['form'] === 'telegram' ? 'display:block;' : '' ?>">

                <a href="#" class="qr-code" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>
                </a>

                <button class="refresh-qr-code themesflat-button" type="button" style="display: none;">
                    <span>Обновить QR код</span>
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>

                <p>
                    <strong>Инструкция:</strong>
                </p>
                <ol>
                    <li>Зарегистрируйтесь на сайте и привяжите Telegram в личном кабинете</li>
                    <li>Откройте сайт напрямую в браузере (без использования режима предпросмотра на мобильном устройстве)</li>
                    <li>Отсканируйте или нажмите по QR коду.</li>
                    <li>Запустите бота Medvisement</li>
                    <li>Если у вас не получается пройти авторизацию, попробуйте смените браузер. Проблема может наблюдаться в браузер на базе Chrome</li>
                </ol>
            </form>

		</div>

		<script type="text/javascript">
			(function ($) {

				/* Form Switch */
				$( ".login-form input[name='type']" ).on( "change", function() {

					let type = $(this).val();

					$(`.login-form form:not(.login-form__${type})`).hide().attr("formnovalidate");
					$(`.login-form .login-form__${type}`).show();

				} );

				/* WS */
				const loginForm = document.getElementById('login-form__telegram');

				refresh_tgqr_code();

				$( ".login-form__telegram button.refresh-qr-code" ).on( "click", function() {
					refresh_tgqr_code();
				});

				function refresh_tgqr_code() {

					// Проверяем, нужно ли обновить соединение
					if ($(loginForm).attr('expired') == 0)
						return false;

					$.ajax({
						url: '<?= admin_url( 'admin-ajax.php' ); ?>',
						dataType: 'json',
						method: 'POST',
						data: {
							'action': 'medvise_telegramqr'
						},
						success: function (data) {

							if (data.success) {
								let link = $(loginForm).find('a.qr-code');

								link.html(data.qr_code);
								link.attr('href', data.bot_url);
								handle_ws_connection(data.connection_id);
								$(loginForm).attr('expired', 0);
                                $( ".login-form__telegram button.refresh-qr-code" ).hide();
							}
							else {
								console.log('Ошибка получения QR кода.')
							}

							console.log(data);
						},
						error: function () {
							alert('Ошибка сервера при получении QR кода!');
						}
					});
				}

				function expire_tgqr_code() {
					$(loginForm).attr('expired', 1);
					$(loginForm).find('a.qr-code').html('');
                    $( ".login-form__telegram button.refresh-qr-code" ).show();
				}

				function handle_ws_connection(connection_id) {
					let socket = new WebSocket("wss://<?= $_SERVER['SERVER_NAME']; ?>/websocket");

					socket.onopen = function (e) {
						console.log("[ws] Соединение установлено");

						//Отправлем ID на сервер
						let data = {'id': connection_id}
						socket.send(JSON.stringify(data));

						//Чтобы подключение не закрывалось
						return false;
					};

					//Получение ответа со стороны сервера
					socket.onmessage = function (event) {

						var data = JSON.parse(event.data);

						console.log(event.data);

						if (typeof data === 'object') {
							$.each(data, function (key, value) {
								console.log(value);
								//Авторизация
								Cookies.set(value.name, value.cookie, {expires: value.expire, path: value.path})
							});

                            const params = new URLSearchParams(window.location.search);
                            const moveTo = params.get('move_to');
                            // Если указан явно редирект - переходим по нему
                            if (moveTo) {
                                window.location.href = decodeURIComponent(moveTo);
                            }
                            else {
                                window.location.reload();
                            }
						}

					};

					//Закрытие подключения
					socket.onclose = (event) => {
						expire_tgqr_code();
						console.log("[ws] Подключение было закрыто");
					};
				}

				/* Show Password */

				$(".login-form__password-wrap i ").click(function () {
					$(this).toggleClass("fa-eye fa-eye-slash");

					var input = $('.login-form__password-wrap input');

					if (input.attr("type") == "password") {
						input.attr("type", "text");
					} else {
						input.attr("type", "password");
					}
				});

				/* Authorization + Registration */
				function medviseLoginRedirect() {
					const params = new URLSearchParams(window.location.search);
					const moveTo = params.get('move_to');

					if (moveTo) {
						window.location.href = decodeURIComponent(moveTo);
					}
					else {
						window.location.reload();
					}
				}

				function medviseShowLoginAlert(formSelector, status, message) {
					$(`${formSelector} div.alert`).removeClass('alert-success alert-danger alert-warning');
					$(`${formSelector} div.alert`).addClass(`alert-${status}`);
					$(`${formSelector} div.alert`).html(message);
					$(`${formSelector} div.alert`).slideDown();
				}

					function medviseValidateEmail(email) {
						return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
					}

					const emailCodeLifetimeSeconds = 5 * 60;
					let emailCodeTimer = null;

					function medviseGetEmailCode() {
						return $('#login-form__email .login-form__code-digit').map(function () {
							return $(this).val();
						}).get().join('');
					}

					function medviseClearEmailCode() {
						$('#login-form__email .login-form__code-digit').val('');
						$('#login-form__email input[name="email_code"]').val('');
					}

					function medviseStartEmailCodeTimer(seconds) {
						const timerElement = $('#login-form__email .js-email-login-code-timer');
						const resendLink = $('#login-form__email .js-email-login-code-resend');
						let secondsLeft = seconds;

						clearInterval(emailCodeTimer);
						resendLink.addClass('is-disabled').attr('aria-disabled', 'true');

						function renderTimer() {
							const minutes = Math.floor(secondsLeft / 60);
							const secondsPart = String(secondsLeft % 60).padStart(2, '0');
							timerElement.text(`(${minutes}:${secondsPart})`);

							if (secondsLeft <= 0) {
								clearInterval(emailCodeTimer);
								timerElement.text('');
								resendLink.removeClass('is-disabled').attr('aria-disabled', 'false');
								return;
							}

							secondsLeft -= 1;
						}

						renderTimer();
						emailCodeTimer = setInterval(renderTimer, 1000);
					}

					function medviseShowEmailCodeConfirm() {
						$('#login-form__email .login-form__email-code-request').hide();
						$('#login-form__email .login-form__email-code-confirm').fadeIn(150);
						medviseClearEmailCode();
						medviseStartEmailCodeTimer(emailCodeLifetimeSeconds);
						$('#login-form__email .login-form__code-digit').first().trigger('focus');
					}

					function medviseShowEmailCodeRequest() {
						clearInterval(emailCodeTimer);
						$('#login-form__email .js-email-login-code-resend').removeClass('is-disabled').attr('aria-disabled', 'false');
						$('#login-form__email div.alert').hide();
						$('#login-form__email .login-form__email-code-confirm').hide();
						$('#login-form__email .login-form__email-code-request').fadeIn(150);
						$('#login-form__email input[name="email"]').trigger('focus');
					}

					function medviseSendEmailCode(button) {
						const email = $('#login-form__email input[name="email"]').val().trim();

						if (!medviseValidateEmail(email)) {
							medviseShowLoginAlert('#login-form__email', 'danger', 'Проверьте правильность ввода Email.');
							return;
						}

						button.prop('disabled', true).addClass('is-loading');

						$.ajax({
							type: 'POST',
							dataType: 'json',
							url: '<?= admin_url( 'admin-ajax.php' ); ?>',
							data: {
								'action': 'medvise_email_login_code_send',
								'email': email,
								'security': $('#login-form__email input[name="security"]').val()
							},
							success: function (data) {
								if (data.code_sent) {
									$('#login-form__email div.alert').hide();
									medviseShowEmailCodeConfirm();
								}
								else {
									medviseShowLoginAlert('#login-form__email', data.status, data.message);
								}
							},
							complete: function () {
								button.prop('disabled', false).removeClass('is-loading');
							}
						});
					}

					$('.js-email-login-code-send').on('click', function () {
						medviseSendEmailCode($(this));
					});

					$('.js-email-login-code-verify').on('click', function () {
						const email = $('#login-form__email input[name="email"]').val().trim();
						const code = medviseGetEmailCode();

						$('#login-form__email input[name="email_code"]').val(code);

						if (!medviseValidateEmail(email)) {
							medviseShowLoginAlert('#login-form__email', 'danger', 'Проверьте правильность ввода Email.');
							return;
						}

						if (!/^\d{6}$/.test(code)) {
							medviseShowLoginAlert('#login-form__email', 'danger', 'Введите 6-значный код из письма.');
							return;
						}

						$.ajax({
							type: 'POST',
						dataType: 'json',
						url: '<?= admin_url( 'admin-ajax.php' ); ?>',
							data: {
								'action': 'medvise_email_login_code_verify',
								'email': email,
								'code': code,
								'security': $('#login-form__email input[name="security"]').val()
							},
							success: function (data) {
								medviseShowLoginAlert('#login-form__email', data.status, data.message);

								if ( "loggedin" in data && data.loggedin == true) {
									medviseLoginRedirect();
								}
							}
						});
					});

					$('.js-email-login-code-resend').on('click', function (e) {
						e.preventDefault();
						if ($(this).hasClass('is-disabled')) {
							return;
						}
						medviseSendEmailCode($(this));
					});

					$('.js-email-login-code-change-email').on('click', function (e) {
						e.preventDefault();
						medviseShowEmailCodeRequest();
					});

					$('#login-form__email .login-form__code-digit').on('input', function () {
						const input = $(this);
						const value = input.val().replace(/\D/g, '').slice(-1);
						input.val(value);

						if (value) {
							input.nextAll('.login-form__code-digit').first().trigger('focus');
						}
					});

					$('#login-form__email .login-form__code-digit').on('paste', function (e) {
						const pasted = (e.originalEvent.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);

						if (pasted.length <= 1) {
							return;
						}

						e.preventDefault();

						$('#login-form__email .login-form__code-digit').each(function (index) {
							$(this).val(pasted[index] || '');
						});

						$('#login-form__email .login-form__code-digit').eq(Math.min(pasted.length, 6) - 1).trigger('focus');
					});

					$('#login-form__email .login-form__code-digit').on('keydown', function (e) {
						if (e.key === 'Enter') {
							e.preventDefault();
							$('.js-email-login-code-verify').trigger('click');
						}

						if (e.key === 'Backspace' && !$(this).val()) {
							$(this).prevAll('.login-form__code-digit').first().trigger('focus');
						}
					});

					$('#login-form__email').on('submit', function (e) {
						e.preventDefault();
						if ($('.login-form__email-code-confirm').is(':visible')) {
							$('.js-email-login-code-verify').trigger('click');
						}
						else {
							$('.js-email-login-code-send').trigger('click');
						}
					});

					$('#login-form__password').on('submit', function (e) {
						$.ajax({
							type: 'POST',
							dataType: 'json',
							url: '<?= admin_url( 'admin-ajax.php' ); ?>',
							data: {
								'action': 'ajaxlogin', //calls wp_ajax_nopriv_ajaxlogin
								'username': $('#login-form__password input[name="email"]').val(),
								'password': $('#login-form__password input[name="password"]').val(),
								'security': $('#login-form__password input[name="security"]').val()
							},
							success: function (data) {

								$('#login-form__password div.alert').removeClass('alert-success alert-danger');
								$('#login-form__password div.alert').addClass(`alert-${data.status}`);
								$('#login-form__password div.alert').html(data.message);
								$('#login-form__password div.alert').slideDown();

								if ( "loggedin" in data && data.loggedin == true) {
									medviseLoginRedirect();
							}
						}
					});
					e.preventDefault();
				});

				/* Forgot Password */

			})(jQuery);
		</script>
		<?php
	}

	public function render_loginbutton()
	{
        global $post;
		ob_start();

		if (is_user_logged_in()) {
			?>
			<a class="nav-item" href="/cart/">
				<span class="badge badge-pill"><?= WC()->cart->get_cart_contents_count(); ?></span>
				<span><i class="fas fa-shopping-cart"></i></span>
			</a>
			<div class="nav-item me-3 me-lg-0 dropdown">
				<a class="dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
					<i class="fas fa-user"></i>
				</a>
				<ul  class="dropdown-menu" aria-labelledby="userMenuButton">
					<li>
						<a class="dropdown-item" href="<?= get_permalink( wc_get_page_id( 'myaccount' ) ); ?>">Мой Аккаунт</a>
					</li>
					<li>
						<a class="dropdown-item" href="<?= get_permalink( wc_get_page_id( 'shop' ) ); ?>">Тарифы</a>
					</li>
					<?php if ( YITH_WCAF_Affiliate_Factory::get_current_affiliate() ): ?>
						<li>
							<a class="dropdown-item" href="/affiliate-dashboard/">Партнерская программа</a>
						</li>
					<?php endif; ?>
					<?php if ( Helper::can_see_moneypot( get_current_user_id() ) ): ?>
                        <li>
                            <a class="dropdown-item" href="/moneypot/">Денежный котел</a>
                        </li>
					<?php endif; ?>
					<li>
						<hr class="dropdown-divider">
					</li>
					<li>
						<a class="dropdown-item" href="<?= wp_logout_url(home_url()); ?>">
							Выйти <i class="fa-solid fa-right-from-bracket"></i>
						</a>
					</li>
				</ul>
			</div>
			<?php
		} else {
			$move_to_url = '';
			$post_type = ( $post instanceof WP_Post ) ? $post->post_type : '';
			if ( in_array( $post_type, [ 'disease', 'substance' ], true ) ) {
				$move_to_url = '?move_to=' . urlencode( THEMESFLAT_PROTOCOL . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			}
			?>
            <a href="/login/<?= $move_to_url; ?>" class="button-auth login themesflat-button">
                Войти <i class="fa-solid fa-right-to-bracket"></i>
            </a>
            <a href="/register/<?= $move_to_url; ?>" class="button-register">
                Регистрация
            </a>
			<?php
		}

		return ob_get_clean();
	}

	public function render_registrationform() {
		if ( is_user_logged_in() ) {
			return TRUE;
		}
		?>
		<div class="login-form registration-form">
			<form id="registration-form__email" class="login-form__email" style="display: block;">

				<p style="font-style: italic;">
                    Если у вас еще нет аккаунта - введите ваш Email, вам будет отправлено письмо с паролем.
                </p>

				<input type="text" name="email" placeholder="Email" autocomplete="on">

				<?php wp_nonce_field( 'ajax-register-nonce', 'security' ); ?>

				<div class="alert" role="alert" style="display:none;">
					Сообщение!
				</div>

				<button class="themesflat-button" type="submit">
					<span>Регистрация</span>
				</button>
				
			</form>
		</div>

		<script type="text/javascript">
			(function ($) {
				/* Registration */
				$('#registration-form__email').on('submit', function (e) {

                    const params = new URLSearchParams(window.location.search);

                    $.ajax({
						type: 'POST',
						dataType: 'json',
						url: '<?= admin_url( 'admin-ajax.php' ); ?>',
						data: {
							'action': 'ajaxregistration', //calls wp_ajax_nopriv_ajaxregistration
							'email': $('#registration-form__email input[name="email"]').val(),
							'security': $('#registration-form__email input[name="security"]').val(),
                            'move_to_url': params.get('move_to')
						},
						success: function (data) {

							$('#registration-form__email div.alert').removeClass('alert-success alert-danger');
							$('#registration-form__email div.alert').addClass(`alert-${data.status}`);
							$('#registration-form__email div.alert').html(data.message);
							$('#registration-form__email div.alert').slideDown();

                            $('#registration-form__email button[type="submit"]').prop('disabled', true);

                            setTimeout(() => {
                                $('#registration-form__email button[type="submit"]').prop('disabled', false);
                            }, 10000)
						}
					});
					e.preventDefault();
				});

			})(jQuery);
		</script>
		<?php 
	}

	public static function generate_telegram_qr( $action )
	{
		$bot_name = carbon_get_theme_option( 'otplogin_telegram_botname' );

		if ( empty( $bot_name ) ) {
			return '';
		}

		$qrCode = QrCode::create('https://t.me/' . $bot_name . '?start=' . $action)->setSize(250)->setMargin(0);
		$logo = Logo::create(MEDVISE_LOGIN_PLUGIN_DIR . '/assets/logo.svg')
			->setResizeToWidth(60)
			->setResizeToHeight(60);

		$svgWriter = new SvgWriter();
		$result = $svgWriter->write($qrCode, $logo);

		return $result->getString();
	}

	public function get_telegram_qr_ajax() {
		$bot_name = carbon_get_theme_option( 'otplogin_telegram_botname' );

		if ( empty( $bot_name ) ) {
			$this->ajax_response( array(
				'success' => false
			) );
		}

		$uniq_id = strtolower(wp_generate_password(56, false, false));
		$url = "https://t.me/{$bot_name}?start=" . $uniq_id;

		$this->ajax_response( array(
			'success' => true,
			'connection_id' => $uniq_id,
			'qr_code' => $this->generate_telegram_qr($uniq_id),
			'bot_url' => $url
		) );
	}

	public function ajax_login() {

		check_ajax_referer( 'ajax-login-nonce', 'security' );

		$user_email = $_POST['username'];

		if ( ! is_email( $user_email ) || ! email_exists( $user_email ) ) {
			echo json_encode( [
				'status' => 'danger',
				'message'  => __( 'Проверьте правильность ввода Email' )
			] );
			die();
		}

		$user = get_user_by( 'email', $user_email );
		$user_login = $user->user_login;

		// Nonce is checked, get the POST data and sign user on
		$info = array();
		$info['user_login'] = $user_login;
		$info['user_password'] = $_POST['password'];
		$info['remember'] = true;

		$user_signon = wp_signon( $info, FALSE );

		if ( is_wp_error( $user_signon ) ) {
			echo json_encode( [
				'status' => 'danger',
				'message'  => 'Неправильный Email или пароль.'
			] );
		} else {

			// Выставляем куки
			wp_set_current_user( $user_signon->ID );
			wp_set_auth_cookie( $user_signon->ID, true );

			echo json_encode( [
				'status'   => 'success',
				'loggedin' => TRUE,
				'message'  => __( 'Успешный вход, перенаправление.' )
			] );
		}

		die();

	}

	public function ajax_email_login_code_send() {
		check_ajax_referer( 'ajax-login-nonce', 'security' );

		$user_email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

			if ( ! is_email( $user_email ) ) {
				echo json_encode( [
					'status'  => 'danger',
					'message' => __( 'Проверьте правильность ввода Email.' )
				] );
				die();
			}

			$code_sent_message = __( 'Если такая почта зарегистрирована, на нее отправлен 6-значный код, введите код в окошке ниже.' );
			$rate_limit_key    = 'medvise_email_login_code_' . md5( strtolower( $user_email ) );

			if ( get_transient( $rate_limit_key ) ) {
				echo json_encode( [
					'status'    => 'success',
					'message'   => $code_sent_message,
					'code_sent' => true,
				] );
				die();
			}

			$user = get_user_by( 'email', $user_email );
			if ( ! $user instanceof WP_User ) {
				set_transient( $rate_limit_key, 1, MINUTE_IN_SECONDS );

				echo json_encode( [
					'status'    => 'success',
					'message'   => $code_sent_message,
					'code_sent' => true,
				] );
				die();
			}

			$code = (string) wp_rand( 100000, 999999 );

			update_user_meta( $user->ID, 'medvise_email_login_code_hash', wp_hash_password( $code ) );
			update_user_meta( $user->ID, 'medvise_email_login_code_expires', time() + 5 * MINUTE_IN_SECONDS );
			set_transient( $rate_limit_key, 1, MINUTE_IN_SECONDS );

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$sent      = wp_mail(
				$user_email,
				"Код для входа на {$site_name}",
				"Ваш 6-значный код для входа на сайт {$site_name}: {$code}\n\n" .
				"Код действует 5 минут. Если вы не запрашивали вход, просто проигнорируйте это письмо.",
				$this->get_email_login_code_headers()
			);

		if ( ! $sent ) {
			delete_user_meta( $user->ID, 'medvise_email_login_code_hash' );
			delete_user_meta( $user->ID, 'medvise_email_login_code_expires' );
			delete_transient( $rate_limit_key );

			echo json_encode( [
				'status'  => 'danger',
				'message' => __( 'Не удалось отправить письмо с кодом. Пожалуйста, попробуйте позже.' )
			] );
			die();
		}

			echo json_encode( [
					'status'    => 'success',
					'code_sent' => true,
					'message'   => $code_sent_message
				] );
		die();
	}

	public function ajax_email_login_code_verify() {
		check_ajax_referer( 'ajax-login-nonce', 'security' );

		$user_email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$code       = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( ! is_email( $user_email ) ) {
			echo json_encode( [
				'status'  => 'danger',
				'message' => __( 'Проверьте правильность ввода Email.' )
			] );
			die();
		}

			if ( ! preg_match( '/^\d{6}$/', $code ) ) {
				echo json_encode( [
					'status'  => 'danger',
					'message' => __( 'Введите 6-значный код из письма.' )
				] );
				die();
			}

			$invalid_code_message = __( 'Неверный код или срок действия кода истёк.' );
			$user = get_user_by( 'email', $user_email );
			if ( ! $user instanceof WP_User ) {
				echo json_encode( [
					'status'  => 'danger',
					'message' => $invalid_code_message
				] );
				die();
			}

		$code_hash = get_user_meta( $user->ID, 'medvise_email_login_code_hash', true );
		$expires   = (int) get_user_meta( $user->ID, 'medvise_email_login_code_expires', true );

			if ( empty( $code_hash ) || $expires < time() ) {
				delete_user_meta( $user->ID, 'medvise_email_login_code_hash' );
				delete_user_meta( $user->ID, 'medvise_email_login_code_expires' );

				echo json_encode( [
					'status'  => 'danger',
					'message' => $invalid_code_message
				] );
				die();
			}

			if ( ! wp_check_password( $code, $code_hash, $user->ID ) ) {
				echo json_encode( [
					'status'  => 'danger',
					'message' => $invalid_code_message
				] );
				die();
			}

		delete_user_meta( $user->ID, 'medvise_email_login_code_hash' );
		delete_user_meta( $user->ID, 'medvise_email_login_code_expires' );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		echo json_encode( [
			'status'   => 'success',
			'loggedin' => TRUE,
			'message'  => __( 'Успешный вход, перенаправление.' )
		] );
		die();
	}

	private function get_email_login_code_headers() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		if ( '' === $host || 'localhost' === $host ) {
			$host = 'medvisement.local';
		}

		$host = preg_replace( '/[^a-z0-9\.\-]/', '', $host );
		if ( ! $host || ! str_contains( $host, '.' ) ) {
			$host = 'medvisement.local';
		}

		$from_email = 'no-reply@' . $host;
		$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		return [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $site_name . ' <' . $from_email . '>',
		];
	}

	public function ajax_registration() {

		check_ajax_referer( 'ajax-register-nonce', 'security' );

		$info = array();
		$info['user_email'] = $_POST['email'];
		$info['user_login'] = Medviselogin_Backend::generate_hash_login();
		$info['user_password'] = wp_generate_password(12, true, false);
        $move_to_url = $_POST['move_to_url'] ?? '';

		if ( !is_email( $info['user_email'] ) ) {
			echo json_encode( [
				'status' => 'danger',
				'message'  => __( 'Проверьте правильность ввода Email.' )
			] );
			die();
		}

		if ( email_exists( $info['user_email'] ) ) {
			echo json_encode( [
				'status' => 'danger',
				'message'  => 'Пользователь с таким Email уже зарегистрирован.<br>' .
                              'Если это ваш аккаунт - вы можете удалить его в личном кабинете, ' .
                              'пройдя процедуру <a href="/wp-login.php?action=lostpassword" target="_blank">восстановления пароля</a>. ' .
                              'А затем прикрепить Email к аккаунту, ранее созданному через Telegram.'
			] );
			die();
		}

		if ( username_exists( $info['user_email'] ) ) {
			echo json_encode( [
				'status' => 'danger',
				'message'  => 'Пользователь с таким логином уже зарегистрирован.'
			] );
			die();
		}

		$user_detail = array(
			'user_login' => $info['user_login'],
			'user_email' => $info['user_email'],
			'role'       => 'subscriber'
		);

		add_filter('send_password_change_email', '__return_false');
		add_filter('send_email_change_email', '__return_false');

		$user_id = wp_insert_user( $user_detail );

		if ( $user_id && ! is_wp_error( $user_id ) ) {

			wp_set_password( $info['user_password'], $user_id );

			clean_user_cache( $user_id );
			wp_cache_delete( $user_id, 'users' );

			remove_filter('send_password_change_email', '__return_false');
			remove_filter('send_email_change_email', '__return_false');

			// Связываем с партнером, если стоит кука
			if ( isset( $_COOKIE['medrftoken'] ) && get_userdata( $_COOKIE['medrftoken'] ) ) {
				update_user_meta( $user_id, 'partner_id', $_COOKIE['medrftoken'] );
				update_user_meta( $user_id, 'partner_time', time() );
			}

			wp_mail(
				$info['user_email'],
				'Ваш аккаунт',
				"Ваши данные для входа:\n Логин: " .
				$info['user_email'] .
				"\nПароль: " .
				$info['user_password']
			);

            // Если указана ссылка на редирект
            $move_to_param = '';
			if ( ! empty( $move_to_url ) ) {
				if ( wp_http_validate_url( $move_to_url ) ) {
					$move_to_param = '&move_to=' . urlencode( $move_to_url );
				}
			}

			echo json_encode( [
				'status'  => 'success',
				'message' => __(
					'Ваш аккаунт был успешно создан! Данные для входа отправлены на почту. ' .
					'Перейти на <a href="/login/?form=password&registered_email=' .
					urlencode( $info['user_email'] ) . $move_to_param .
					'">страницу входа</a>. '
				)
			] );
			die();
		};

		echo json_encode( [
			'status' => 'danger',
			'message'  => __( 'Ошибка при создании аккаунта. Пожалуйста, напишите в техподдержку.' )
		] );
		die();
	}

    public function gettext_wp_login( $translation, $text, $domain ) {

        if ($domain !== 'default') {
            return $translation;
        }

        if ( $text === '<strong>Error:</strong> The email could not be sent. Your site may not be correctly configured to send emails. <a href="%s">Get support for resetting your password</a>.' ) {
            return '<strong>Ошибка сброса пароля:</strong> пожалуйста напишите в техподдержку <a href="%s">info@medvisement.com</a>.';
        }

        if ( $text === 'https://wordpress.org/documentation/article/reset-your-password/' ) {
            return 'mailto:info@medvisement.com';
        }

        if ( $text === 'Username or Email Address' ) {
            return 'Введите ваш Email';
        }

        if ( $text === 'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' ) {
            return 'Пожалуйста, введите ваш Email. Вам будет отправлено письмо с инструкцией по восстановлению пароля.';
        }

        return $translation;
    }

	private function ajax_response( $output ) {
		echo json_encode( $output );
		die();
	}
}
