<?php

class Medviselogin_Notifications {
	public function __construct() {
		$this->init();
	}

	public function init() {

		// Письмо с запросом на сброс пароля от аккаунта
		add_filter( 'retrieve_password_message', [ $this, 'retrieve_password_message' ], 10, 4 );

		// Уведомление о сбросе пароля
		add_filter( 'password_change_email', [ $this, 'password_change_email' ], 10, 3 );

		// Попытка смены Email адреса аккаунта. Используется только из админки
		add_filter( 'new_user_email_content', [ $this, 'new_user_email_content' ], 10, 2 );

		// Письмо после смены Email аккаунта. Используется только из админки
		add_filter( 'email_change_email', [ $this, 'email_change_email' ], 10, 3 );

		// Емейлы для удаления персональных данных. Не используется. Инструменты -> Удаление персональных данных
		// The email is sent to a user when their data erasure request is fulfilled by an administrator.
		// add_filter( 'user_erasure_fulfillment_email_content', [ $this, 'user_erasure_fulfillment_email_content' ], 10, 2 );
		// Filters the text of the email sent when an account action is attempted.
		// add_filter( 'user_request_action_email_content', [ $this, 'user_request_action_email_content' ], 10, 2 );
		// The email is sent to an administrator when a user request is confirmed.
		// add_filter( 'user_request_confirmed_email_content', [ $this, 'user_request_confirmed_email_content' ], 10, 2 );

	}

	public function retrieve_password_message( $message, $key, $user_login, $user_data ) {

		$user_name = $this->get_user_name( $user_data );

		return preg_replace( '/(Имя пользователя: )(.+)$/mu', '$1' . $user_name, $message );
	}

	public function password_change_email( $pass_change_email, $user, $userdata ) {

		$user = get_user_by( 'ID', $user['ID'] );

		$user_name = $this->get_user_name( $user );

		return str_replace( '###USERNAME###', $user_name, $pass_change_email );
	}

	public function new_user_email_content( $email_text, $new_user_email ) {

		$current_user = wp_get_current_user();

		$user_name = $this->get_user_name( $current_user );

		return str_replace( '###USERNAME###', $user_name, $email_text );
	}

	public function email_change_email( $email_change_email, $user, $userdata ) {

		$user = get_user_by( 'ID', $user['ID'] );

		$user_name = $this->get_user_name( $user );

		return str_replace( '###USERNAME###', $user_name, $email_change_email );
	}

	private function get_user_name( WP_User $user ) {

		$first_name = get_user_meta( $user->ID, 'first_name', true );

		if ( ! empty( $first_name ) ) {
			return $first_name;
		}

		if ( ! empty( $user->user_email ) ) {
			return $user->user_email;
		}

		return 'Пользователь';

	}
}