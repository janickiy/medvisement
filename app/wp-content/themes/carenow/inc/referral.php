<?php

use MedviseSubscriptions\ShareArticleAccess;

add_action( 'init', function () {

	// Данных о партнере или редиректе нет
	if ( ! isset( $_POST['u_id'] ) || ! isset( $_POST['p_id'] ) ) {
		return true;
	}

	$post_id = (int) $_POST['p_id'];
	$partner_id = (int) $_POST['u_id'];

	$permalink = get_post_permalink( $post_id );

	// Устанавливаем куку на 7 дней, если ее нет. К самому себе привязаться нельзя
	if ( ! isset( $_COOKIE['medrftoken'] ) && get_current_user_id() != $partner_id ) {
		setcookie( 'medrftoken', $partner_id, time() + 60 * 60 * 24 * 7 );
	}

	if ( $permalink ) {
		wp_redirect( $permalink );
		exit;
	}
}, 10 );

add_action( 'init', function () {

	$partner_id = null;

	// Привязка к партнеру по ссылке поделиться или открытию статьи
	if ( isset( $_COOKIE['medrftoken'] ) ) {
		$partner_id = (int) $_COOKIE['medrftoken'];
	} elseif ( isset( $_COOKIE['access_token'] ) ) {
		$token_data = ShareArticleAccess::get_share_token( $_GET['access_token'] );

		$partner_id = $token_data->user_id;
	}

	// Проверяем, если авторизован
	if ( get_current_user_id() && $partner_id ) {

		$user_id = get_current_user_id();


		// К самому себе привязаться нельзя
		if ( $user_id == $partner_id ) {
			return true;
		}

		// Есть ли оплаченные заказы
		$customer_orders = get_posts( array(
			'numberposts' => 1,
			'meta_key'    => '_customer_user',
			'meta_value'  => get_current_user_id(),
			'post_type'   => 'shop_order',
			'post_status' => 'wc-completed',
			'fields'      => 'ids',
		) );

		// Заказов оплаченных нет
		if ( count( $customer_orders ) < 1 ) {

			// Партнер не задан
			if ( '' == get_user_meta( $user_id, 'partner_id', true ) ) {
				update_user_meta( $user_id, 'partner_id', $partner_id );
				update_user_meta( $user_id, 'partner_time', time() );
			} // Зашел по ссылке от того же партнера - обновляем время
			elseif ( $partner_id == get_user_meta( $user_id, 'partner_id', true ) ) {
				update_user_meta( $user_id, 'partner_time', time() );
			} // Зашел по чужой ссылке и прошло 7 дней
			elseif ( time() > ( intval(get_user_meta( $user_id, 'partner_time', true )) + 60 * 60 * 24 * 7 ) ) {
				update_user_meta( $user_id, 'partner_id', $partner_id );
				update_user_meta( $user_id, 'partner_time', time() );
			}

		}

	}
}, 20 );

function medvise_referral_generate_post_url( string $post_id, string $user_id = '') {

	if ( empty($user_id) ) {
		$user_id = (string) get_current_user_id();
	}

	$referral_url = 'https://medvsm.com/';
	$replace_map = [
		1 => 'dZoYn',
		2 => 'FQDXH',
		3 => 'tSqBa',
		4 => 'wlmGR',
		5 => 'yVcgi',
		6 => 'epEAk',
		7 => 'svbCJ',
		8 => 'MPhxj',
		9 => 'WzuTN',
		0 => 'OKfUI'
	];

	$user_length = strlen($user_id);
	$post_length = strlen($post_id);

	for ( $i = 0; $i < $user_length; $i++ ) {
		$referral_url .= $replace_map[$user_id[$i]][rand(0,4)];
	}

	// Разделитель
	$referral_url .= 'r';

	for ( $i = 0; $i < $post_length; $i++ ) {
		$referral_url .= $replace_map[$post_id[$i]][rand(0,4)];
	}

	return $referral_url;
}