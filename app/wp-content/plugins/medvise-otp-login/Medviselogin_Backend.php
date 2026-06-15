<?php

class Medviselogin_Backend {

	public function __construct() {
		$this->init();
	}


	public function init() {
		add_filter( 'login_url', [ $this, 'login_url' ], 10, 3 );

		add_action( 'template_redirect', [ $this, 'template_redirect' ] );

		add_filter('wp_password_change_notification_email', [ $this, 'wp_password_change_notification_email' ], 10, 1);
	}

	// Ссылка на вход - меняем на страницу входа (после сброса пароля)
	public function login_url( $login_url, $redirect, $force_reauth ) {

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'resetpass' ) {
			return get_site_url() . '/login?form=email';
		}

		return $login_url;
	}

	public function template_redirect() {

		// Редирект со страницы логина в аккаунт
		if ( '/login/' === strtok($_SERVER['REQUEST_URI'], '?') && is_user_logged_in() ) {
			wp_safe_redirect( get_site_url() . '/my-account/' );
			exit();
		}

		// Редирект со страницы регистрации в аккаунт
		if ( '/register/' === strtok($_SERVER['REQUEST_URI'], '?') && is_user_logged_in() ) {
			wp_safe_redirect( get_site_url() . '/my-account/' );
			exit();
		}

	}

	public function wp_password_change_notification_email( $wp_password_change_notification_email ) {
		$wp_password_change_notification_email['to'] = '';
		return $wp_password_change_notification_email;
	}

	public static function generate_hash_login( $prefix = 'user' ) {
		do {
			$hash = substr(md5(uniqid(rand(), true)), 0, rand(16, 32));
			$login = $prefix . '_' . $hash;
		} while (username_exists($login));
		
		return $login;
	}

}