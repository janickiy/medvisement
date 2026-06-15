<?php

add_action( 'wpcf7_init', 'remove_captcha_for_logged_in' );

function remove_captcha_for_logged_in() {

	if ( is_user_logged_in() ) {
		if ( class_exists( 'CFYC_Frontend' ) ) {
			remove_filter( 'wpcf7_validate_yandex_captcha', [ CFYC_Frontend::get_instance(), 'cfyc_validate_fills' ], 99, 2 );
			remove_filter( 'wpcf7_spam', [ CFYC_Frontend::get_instance(), 'cfyc_validate_captcha' ], 9, 2 );
		}

		wpcf7_remove_form_tag( 'yandex_captcha' );
	}

}

add_action( 'wp_head', 'show_captcha_not_logged_in' );

function show_captcha_not_logged_in() {

	if ( ! is_user_logged_in() ) {
		?>
        <style type="text/css">
            .wpcf7-form-control-wrap_captcha {
                display: block !important;
                margin-bottom: 12px;
            }
        </style>
		<?php
	}

}

add_filter( 'wpcf7_verify_nonce', '__return_true' );