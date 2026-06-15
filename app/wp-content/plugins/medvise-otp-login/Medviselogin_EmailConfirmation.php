<?php

class Medviselogin_EmailConfirmation {

	public function __construct() {
		$this->init();
	}

	public function init() {
		// Обрабатываем поле account_email формы анкеты
		add_action( 'template_redirect', [ $this, 'account_form_email_verification_handler' ], 1 );

		// Обработка перехода по ссылке подтверждения email
		add_action( 'init', [ $this, 'email_verification_handler' ] );

		// Повторная отправка ссылки активации почты
		add_action( 'wp_ajax_email_verification_resend', [ $this, 'email_verification_resend' ] );
        // Отмена активации почты
		add_action( 'wp_ajax_email_verification_cancel', [ $this, 'email_verification_cancel' ] );

		// Проверка существования Email
		add_action( 'wp_ajax_email_check_exist', [ $this, 'email_check_exist' ] );
	}

	public function account_form_email_verification_handler() {
		if (
			is_user_logged_in() &&
			is_account_page() &&
			is_wc_endpoint_url( 'edit-account' ) &&
			!empty( $_POST['action'] ) &&
			$_POST['action'] === 'save_account_details'
		) {
			// Если мейл занял, уходим, сработает стандартное уведомление о занятом мейле
			$user_exists = (bool) get_user_by( 'email', $_POST['account_email'] );
			if ( $user_exists || ! is_email($_POST['account_email']) ) {
				return;
			}

			$user    = wp_get_current_user();
			$user_id = $user->ID;

			if ( isset( $_POST['account_email'] ) && $_POST['account_email'] !== $user->user_email ) {
				
				$new_email = $_POST['account_email'];

				// Генерируем токен подтверждения
				$verification_token = md5( uniqid( $user_id . time(), true ) );
				update_user_meta( $user_id, 'new_email_token', $verification_token );
				update_user_meta( $user_id, 'new_email', $new_email );

				// Формируем ссылку и отправляем письмо
				$sent = $this->email_verification_link_sender();
			}

			if ( empty( $user->user_email ) ) {
				$_POST['account_email'] = 'temp@email.local';

				// Отключаем уведомление о смене email
				add_filter( 'send_email_change_email', '__return_false', 999 );

				global $wp_filter;

				if ( isset( $wp_filter['is_email'] ) && is_a( $wp_filter['is_email'], 'WP_Hook' ) ) {
					foreach ( $wp_filter['is_email']->callbacks as $priority => $callbacks ) {
						foreach ( $callbacks as $callback_key => $callback ) {
							remove_filter( 'is_email', $callback['function'], $priority );
						}
					}
				}

				if ( isset( $wp_filter['email_exists'] ) && is_a( $wp_filter['email_exists'], 'WP_Hook' ) ) {
					foreach ( $wp_filter['email_exists']->callbacks as $priority => $callbacks ) {
						foreach ( $callbacks as $callback_key => $callback ) {
							remove_filter( 'email_exists', $callback['function'], $priority );
						}
					}
				}

				add_action( 'woocommerce_save_account_details', function( $user_id ) use ( $user ) {
					wp_update_user( [
						'ID'         => $user_id,
						'user_email' => '',
					] );
				}, 5, 1 );
			} else {
				$_POST['account_email'] = $user->user_email;
			}
		}
	}

	public function email_verification_handler() {

		// Email был успешно изменен
		if ( ! empty( $_GET['email_changed'] ) ) {
			wc_add_notice( 'Ваш Email был успешно изменен.', 'success' );
		}

		// Проверяем, был ли переданы параметры в URL
		if ( ! isset( $_GET['verify_email'] ) || ! isset( $_GET['user_id'] ) ) {
			return;
		}

        // Проверяем валидность данных в запросе
		$user_id = (int) $_GET['user_id'];
		$verification_token = sanitize_text_field( $_GET['verify_email'] );
		$stored_token       = get_user_meta( $user_id, 'new_email_token', true );
        $authorized_user_id = get_current_user_id();

		if ( $verification_token !== $stored_token ) {
			echo "Похоже, ваша ссылка для подтверждения Email адреса истекла, попробуйте отправить ее повторно.<br>";
            echo "<a href='/'>Вернуться на главную</a>";
			die();
		}

		// Проверяем, что новый email существует и является правильным
		$new_email = get_user_meta( $user_id, 'new_email', true );
		if ( empty( $new_email ) || ! is_email( $new_email ) ) {
			echo "Неправильно указан новый Email, попробуйте задать новый Email в личном кабинете повторно.<br>";
			echo "<a href='/'>Вернуться на главную</a>";
			die();
		}

		// Проверяем, есть ли уже пользователь с таким email
		$existing_user = get_user_by('email', $new_email);
		if ( $existing_user ) {
			echo "Пользователь с Email '{$new_email}' уже существует. Попробуйте задать новый Email в личном кабинете повторно.<br>";
			echo "<a href='/'>Вернуться на главную</a>";
			die();
		}

		// Меняем email
		wp_update_user([
			'ID' => $user_id,
			'user_email' => $new_email
		]);
		update_user_meta( $user_id, 'billing_email', $new_email );

		// Обновляем куки аутентификации (чтобы пользователь не разлогинился или залогинился, если не залогинен)
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $user_id );

		// Очищаем мету относящуюся к верификации мейла
		delete_user_meta( $user_id, 'new_email_token' );
		delete_user_meta( $user_id, 'new_email' );

		wc_add_notice( 'Ваш Email был успешно изменен.', 'success' );

		// Возвращаемся на страницу редактирования аккаунта
		$edit_account_endpoint = get_option( 'woocommerce_myaccount_edit_account_endpoint', 'edit-account' );
        // Если не был авторизован - выводим сообщение хаком
		$edit_account_url      = wc_get_account_endpoint_url( $edit_account_endpoint );
		if ( ! $authorized_user_id ) {
			$edit_account_url .= "?email_changed=1";
		}
		wp_safe_redirect( $edit_account_url );
		exit;
	}

	public static function form_notice( $user_id ) {
		$new_email = get_user_meta( $user_id, 'new_email', true );
		if ( empty( $new_email ) ) {
			return;
		}

		?>
			<div style="margin-top:20px;line-height:1.5;display:block;" class="wc-block-components-notice-banner is-warning js-email-verification-notice">
				На адрес <b><?php echo $new_email; ?></b> было отправлено письмо для подтверждения.
				<br>
                <a class="js-email-verification-resend" href="javascript:void(0);">Отправить письмо повторно</a>
                <br>
                <a class="js-email-verification-cancel" href="javascript:void(0);">Отменить</a>
			</div>
			<script>
				jQuery(document).ready(function ($) {
					$('.js-email-verification-resend').click(function(event) {
						if ( !$(this).hasClass('sending') ) {

							$(this).addClass('sending');

							$.ajax({
								url: '<?php echo admin_url('admin-ajax.php'); ?>',
								type: 'POST',
								data: {
									action: 'email_verification_resend',
								},
								async: false,
								success: function(response) {
									console.log(response);
									isSent = response.sent;
									if ( isSent ) {
										$('.js-email-verification-notice')
											.addClass('is-success')
											.removeClass('is-warning')
											.html('Письмо было отправлено повторно');
									}
								}
							});
						}
					});

                    $('.js-email-verification-cancel').click(function(event) {
                        if ( !$(this).hasClass('sending') ) {

                            $(this).addClass('sending');

                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                type: 'POST',
                                data: {
                                    action: 'email_verification_cancel',
                                },
                                async: false,
                                success: function(response) {
                                    $('.js-email-verification-notice').slideUp();
                                }
                            });
                        }
                    });
				});
			</script>
		<?php
	}

	public function email_verification_resend() {
		$this->email_verification_link_sender();

		wp_send_json( ['sent' => true] );
	}

    public function email_verification_cancel() {

        $user_id = get_current_user_id();

        delete_user_meta( $user_id, 'new_email_token' );
        delete_user_meta( $user_id, 'new_email' );

	    wp_send_json( ['cancelled' => true] );
    }

	public function email_check_exist() {
		check_ajax_referer( 'save_account_details', 'nonce' );

        $user = wp_get_current_user();

		echo json_encode( [
			'email_exists' => is_email( $_POST['email'] ) &&
                              email_exists( $_POST['email'] ) &&
                              $user->user_email !== $_POST['email'],
		] );

		die();
	}

	private function email_verification_link_sender() {
        $user = wp_get_current_user();

		$new_email_token = get_user_meta( $user->ID, 'new_email_token', true );
		$new_email       = get_user_meta( $user->ID, 'new_email', true );

		$verification_link = add_query_arg( [
			'verify_email' => $new_email_token,
			'user_id'      => $user->ID
		], wc_get_account_endpoint_url( 'dashboard' ) );

		$subject = 'Запрос на изменение Email адреса';
        $message = "Здравствуйте! Кто-то указал почту {$new_email} для прикрепления к аккаунту на сайте medvisement.com.\n" .
                   "Если это были не вы, то проигнорируйте данное письмо.\n\n" .
                   "Если это были вы, то перейдите по ссылке ниже для подтверждения.\n";
		if ( ! empty( $user->user_email ) ) {
			$message .= "Ваш текущий Email {$user->user_email}.\n";
		}
		$message .= $verification_link;

		return wp_mail( $new_email, $subject, $message );
	}

}