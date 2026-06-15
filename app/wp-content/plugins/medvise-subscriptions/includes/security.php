<?php

namespace MedviseSubscriptions\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function setup() {
	//Отключаем пароли приложений
	add_filter( 'wp_is_application_passwords_available', '__return_false' );

	//Отключаем REST для всех кроме админов и авторов
	add_action( 'plugins_loaded', __NAMESPACE__ . '\restrict_rest_api', 100);
}

function restrict_rest_api() {

	$current_user = wp_get_current_user();

	if ( ! is_user_logged_in() ||
	     ( ! in_array( 'administrator', $current_user->roles ) &&
	       ! in_array( 'author', $current_user->roles ) &&
	       ! in_array( 'editor', $current_user->roles )
	     )
	) {
		remove_action( 'rest_api_init', 'create_initial_rest_routes', 99 );

		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
	}

}