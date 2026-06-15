<?php

class Medviselogin_PasswordChange {

	public function __construct() {
		$this->init();
	}

	public function init() {
		// Добавляем в меню
		add_filter( 'woocommerce_get_query_vars', [ $this, 'woocommerce_get_query_vars' ], 10, 1 );

		// Изменение заголовка страницы
		add_filter( 'woocommerce_endpoint_password-change_title', [ $this, 'custom_endpoint_page_title' ], 10, 1 );

		// Добавление пункта в меню My Account (перед templates-tab)
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_password_change_link_my_account' ], 999 );

		// Ранняя обработка формы (до вывода страницы)
		add_action( 'template_redirect', [ $this, 'handle_password_change_early' ] );

		// Регистрация содержимого страницы
		add_action( 'woocommerce_account_password-change_endpoint', [ $this, 'password_change_form' ] );
	}

	public function woocommerce_get_query_vars( $vars ) {
		$vars['password-change'] = 'password-change';

		return $vars;
	}

	public function custom_endpoint_page_title( $title ) {
		return 'Пароль аккаунта';
	}

	public function add_password_change_link_my_account( $items ) {
		$new_items = [];

		foreach ( $items as $key => $value ) {
			if ( $key === 'templates-tab' ) {
				$new_items['password-change'] = 'Пароль аккаунта';
			}
			$new_items[ $key ] = $value;
		}

		return $new_items;
	}

	public function handle_password_change_early() {
		global $wp;

		// Проверяем, что мы на нужном эндпоинте
		if ( isset( $wp->query_vars['password-change'] ) ) {
			$user = wp_get_current_user();

			if ( ! $user->exists() ) {
				return;
			}

			if ( isset( $_POST['change_password'] ) ) {
				$current_password     = $_POST['current_password'] ?? '';
				$new_password         = $_POST['new_password'];
				$confirm_new_password = $_POST['confirm_new_password'];

				$allow_skip_current_password = get_user_meta( $user->ID, 'password_change_allowed', true );
				$show_current_password_field = ! $allow_skip_current_password;

				if ( $show_current_password_field && ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
					wc_add_notice( 'Неправильный текущий пароль', 'error' );
				} elseif ( $new_password !== $confirm_new_password ) {
					wc_add_notice( 'Пароли не совпадают', 'error' );
				} elseif ( strlen( $new_password ) < 6 ) {
					wc_add_notice( 'Пароль должен содержать минимум 6 символов', 'error' );
				} else {
					// Меняем пароль
					wp_set_password( $new_password, $user->ID );

					// Принудительно обновляем куки
					wp_clear_auth_cookie();
					wp_set_auth_cookie( $user->ID, true, is_ssl() );

					// Запускаем стандартные хуки WordPress после входа
					do_action( 'wp_login', $user->user_login, $user );

					// Сохраняем успех во временное хранилище
					set_transient( 'password_change_success_' . $user->ID, true, 30 );
				}

				// Если есть ошибки — сохраняем их во временное хранилище
				if ( wc_has_notice( '', 'error' ) ) {
					$notices = wc_get_notices();
					set_transient( 'password_change_errors_' . $user->ID, $notices['error'], 30 );
				}

				// Выполняем редирект, чтобы браузер получил новые куки
				$redirect_url = wc_get_endpoint_url( 'password-change', '', wc_get_page_permalink( 'myaccount' ) );

				if ( ! headers_sent() ) {
					wp_redirect( $redirect_url );
					exit;
				}
			}
		}
	}

	public function password_change_form() {
		$user = wp_get_current_user();

		// Если емейл не задан - не даем менять пароль
		if ( empty( $user->user_email ) ):
			?>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                Вам необходимо указать и подтвердить Email адрес перед установкой пароля.<br>
                Перейдите в пункт «<a href="/my-account/edit-account/">Анкета</a>»
            </p>
		<?php
        return;
		endif;

		// Проверяем наличие меты
		$allow_skip_current_password = get_user_meta( $user->ID, 'password_change_allowed', true );

		// Показываем сообщения об ошибках, если они были
		if ( $errors = get_transient( 'password_change_errors_' . $user->ID ) ) {
			foreach ( $errors as $error ) {
				wc_add_notice( $error, 'error' );
			}
			delete_transient( 'password_change_errors_' . $user->ID );
		}

		// Показываем сообщение об успехе
		if ( get_transient( 'password_change_success_' . $user->ID ) ) {
			wc_add_notice( 'Пароль успешно изменён.', 'success' );
			delete_user_meta( $user->ID, 'password_change_allowed' );
			$allow_skip_current_password = false;
			delete_transient( 'password_change_success_' . $user->ID );
		}

		wc_print_notices();
		?>
		<form method="post" action="">
			<?php if ( ! $allow_skip_current_password ): ?>
                <p>
                    В случае, если вы забыли свой пароль - пожалуйста, пройдите процедуру
                    <a href="/wp-login.php?action=lostpassword" target="_blank">восстановления пароля</a>.
                </p>
				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="current_password">Текущий пароль&nbsp;<span class="required">*</span></label>
					<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="current_password" id="current_password" />
				</p>
            <?php else: ?>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    Ранее вы не устанавливали пароль. Пожалуйста, установите желаемый пароль для входа по Email.
                </p>
			<?php endif; ?>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="new_password">Новый пароль&nbsp;<span class="required">*</span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="new_password" id="new_password" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="confirm_new_password">Повторите новый пароль&nbsp;<span class="required">*</span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="confirm_new_password" id="confirm_new_password" />
			</p>
			<p>
				<button type="submit" class="button" name="change_password">Изменить пароль</button>
			</p>
		</form>
		<?php
	}
}