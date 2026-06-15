<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use MedviseSubscriptions\Specialty\Specialty;

class MedUserProfile {

	public function __construct() {
		$this->init();
	}

	public function init() {
		//Отключаем выбор цветовой схемы
		add_filter( 'admin_head', [ $this, 'disable_profile_color_scheme' ] );

		add_action( 'show_user_profile', [ $this, 'public_user_profile' ] );
		add_action( 'edit_user_profile', [ $this, 'public_user_profile' ] );

		add_action( 'carbon_fields_register_fields', [ $this, 'carbon_fields_register_fields' ] );
		add_action( 'carbon_fields_user_meta_container_admin_only_access', [
			$this,
			'carbon_fields_user_meta_container_admin_only_access'
		], 10, 3 );

		//Редирект при выходе из аккаунта
		add_action( 'wp_logout', [ $this, 'wp_logout' ] );
		add_filter( 'template_redirect', [ $this, 'account_address_redirect' ], 10 );

		add_filter( 'woocommerce_account_menu_items', [$this, 'woocommerce_account_menu_items'], 10, 1 );

		//Редирект если у пользователя не указана специальность
		add_filter( 'template_redirect', [ $this, 'fill_specialty_redirect' ], 10 );
		//Cпециальность - обязательное поле
		add_filter( 'woocommerce_save_account_details_required_fields', [ $this, 'woocommerce_save_account_details_required_fields' ] );
		// Меняем поля аккаунта при сохранении
        add_filter( 'woocommerce_save_account_details_errors', [ $this, 'woocommerce_save_account_details_errors' ], 10, 2 );
		//Валидация переданной специальности
		add_action( 'woocommerce_save_account_details_errors', [ $this, 'user_specialty_validate' ], 10, 2 );
		//Cохраняем специальность
		add_action( 'woocommerce_save_account_details', [ $this, 'user_specialty_save' ], 10 );
        // Сохраняем параметры страницы
		add_action( 'woocommerce_save_account_details', [ $this, 'save_account_details_keep_get_params' ], 999 );
	}

	// Отключаем выбор цветовой схемы
	public function disable_profile_color_scheme() {
		$screen = get_current_screen();
		if ( in_array( $screen->id, [ 'profile', 'user-edit' ] ) ) {
			remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
		}
	}

	public function public_user_profile( $user ) {
		//Добавляем HTML поле на био профиля
		wp_enqueue_editor();
		?>
        <script>
            document.addEventListener("DOMContentLoaded", function (event) {
                var id = 'description';

                wp.editor.initialize(id, {
                    tinymce: {
                        wpautop: true,
                        toolbar1: 'formatselect | bold italic underline | bullist numlist | link, unlink | alignleft aligncenter alignright | outdent indent'
                    },
                    quicktags: true
                });
            });
        </script>
		<?php
	}

	// Карбон поля в профиле пользователя
	public function carbon_fields_register_fields() {

	    // Способы оплаты
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$excluded_payment_methods_by_default = [ 'nowpayments' => 'nowpayments' ];
		$payment_gateways_names = [];

		foreach ($payment_gateways as $gateway_name => $gateway_data) {
			$payment_gateways_names[$gateway_name] = $gateway_data->title;
		}

		// Подписчик
		$med_subscriber_fields = [
			Field::make( 'set', 'med_subscriber_payment', __( 'Разрешенные способы оплаты' ) )
			     ->add_options( $payment_gateways_names )
			     ->set_default_value( array_keys(array_diff_key($payment_gateways_names, $excluded_payment_methods_by_default)) )
		];

		Container::make( 'user_meta', 'Подписчик' )
		         ->where( 'user_capability', 'CUSTOM', function ( $user_id ) {

			         //Новый пользователь
			         if ( $user_id == 0 ) {
				         return false;
			         }

			         $user_id = empty( $user_id ) ? $_POST['user_id'] : $user_id;

			         $user = get_user_by( 'ID', $user_id );

			         return in_array( 'subscriber', $user->roles );
		         } )
		         ->add_tab( 'Способы оплаты', $med_subscriber_fields );
	}

	public function carbon_fields_user_meta_container_admin_only_access( $access, $title, $object ) {

		if ( $title === 'Подписчик' ) {
			$access = true;
		}

		return $access;
	}

	// Редирект при выходе из сайта
	public function wp_logout() {
		wp_redirect( home_url() );
		exit();
	}

	// Редирект со страницы адресов в анкету
	public function account_address_redirect() {

		$currentUrl = parse_url( $_SERVER["REQUEST_URI"], PHP_URL_PATH );

		$myaccount_page_id   = get_option( 'woocommerce_myaccount_page_id' );
		$myaccount_page_slug = "/" . get_post_field( 'post_name', $myaccount_page_id );

		$editAddressSlug = $myaccount_page_slug . "/" . get_option( 'woocommerce_myaccount_edit_address_endpoint', 'edit-address' );
		$editAccountSlug = $myaccount_page_slug . "/" . get_option( 'woocommerce_myaccount_edit_account_endpoint', 'edit-account' );

		$redirect_slugs = [
			$editAddressSlug,
			$editAddressSlug . '/shipping',
			$editAddressSlug . '/billing'
		];

		foreach ( $redirect_slugs as $redirect_slug ) {
			if ( str_contains( $currentUrl, $redirect_slug ) ) {
				wp_safe_redirect( home_url( $editAccountSlug ) );
				die();
			}
		}
	}

	public function woocommerce_account_menu_items( $items ) {
		//Убираем редактирование адреса
		unset( $items['edit-address'] );

        // Переименовываем "Панель управления"
        $items['dashboard'] = 'Мой аккаунт';

		return $items;
	}

	//Редирект если у пользователя не указана специальность
	public function fill_specialty_redirect() {
		$userId        = get_current_user_id();
        $user = wp_get_current_user();
		$hasToRedirect = is_cart() || is_checkout() || is_singular( [ 'substance', 'disease' ] );
		$hasToRedirect = $hasToRedirect && $userId;

		if ( ! $hasToRedirect ) {
			return;
		}

		if ( ! self::user_has_specialty( $userId ) || ! self::user_has_age_specialty( $userId ) || empty($user->user_email) ) {
			$editAccountEndpoint = get_option( 'woocommerce_myaccount_edit_account_endpoint', 'edit-account' );
			$editAccountUrl      = wc_get_account_endpoint_url( $editAccountEndpoint );

			$move_to_url = urlencode( THEMESFLAT_PROTOCOL . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

			$shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );

			wc_add_notice(
				"Для продолжения использования сайта вам необходимо заполнить анкету. " .
				"После заполнения анкеты вам будет доступен раздел «препараты», древо по препаратам и заболеваниям. " .
				"Приобрести подписку можно в разделе <a href='{$shop_page_url}'>Тарифы</a>.",
				'error'
			);

			wp_safe_redirect( $editAccountUrl . '?move_to=' . $move_to_url );
			die();
		}
	}

	//Специальность - обязательное поле
	public function woocommerce_save_account_details_required_fields( $required_fields ) {
		$required_fields['account_specialty'] = 'Специальность';

		if ( ! empty( $_POST['account_specialty'] ) && $_POST['account_specialty'] === '-1' ) {
			$required_fields['account_other_specialty'] = 'Другая специальность';
		}

		$required_fields['account_age_specialty'] = 'Возрастная специализация';

		// Отображаемое имя - не обязательное. Формируем из имя+фамилия
		unset($required_fields['account_display_name']);

		return $required_fields;
	}

	public function woocommerce_save_account_details_errors( &$errors, &$user ) {

	    // Отображаемое имя - имя + фамилия
	    $user->display_name = $user->first_name . " " . $user->last_name;

	}

	//Валидация переданной специальности
	public function user_specialty_validate( $errors, $user ) {
		$specialty          = (int) $_POST['account_specialty'];
		$allowedSpecialties = Specialty::get_allowed_specialties();

		$age_specialties = (array) $_POST['account_age_specialty'] ?? [];
		$age_terms_ids = get_terms('age', [
			'hide_empty' => false,
            'fields' => 'ids'
		]);

		$specialty_error = true;
		if ( ! empty( $specialty ) ) {
			if ( in_array( $specialty, $allowedSpecialties ) ) {
				$specialty_error = false;
			}

			if ( $specialty === - 1 && ! empty( $_POST['account_other_specialty'] ) ) {
				$specialty_error = false;
			}
		}

		// Вывод ошибки
		if ( $specialty_error ) {
			$errors->add( 'not_allowed_account_specialty', 'Указана неверная специальность' );
		}

		$age_specialty_error = false;

		if ( ! empty($age_specialties) ) {
			foreach ($age_specialties as $age_specialty) {
			    if ( ! in_array( $age_specialty, $age_terms_ids) ) {
				    $age_specialty_error = true;
			    }
			}
		}
		else {
			$age_specialty_error = true;
		}

		// Вывод ошибки
		if ( $age_specialty_error ) {
			$errors->add( 'not_allowed_account_age_specialty', 'Указана неверная возрастная специализация' );
		}

	}

	//Сохраняем специальность
	public function user_specialty_save( $user_id ) {
		if ( ! empty( $_POST['account_specialty'] ) ) {
			update_user_meta( $user_id, 'specialty', (int) $_POST['account_specialty'] );
		}

		if ( ! empty( $_POST['account_other_specialty'] ) ) {
			update_user_meta( $user_id, 'other_specialty', $_POST['account_other_specialty'] );
		}

		if ( ! empty( $_POST['account_age_specialty'] ) ) {
			update_user_meta( $user_id, 'age_specialty', $_POST['account_age_specialty'] );
		}
	}

    public function save_account_details_keep_get_params( $user_id ) {
	    if ( 0 === wc_notice_count( 'error' ) ) {

		    if ( ! empty( $_REQUEST['move_to'] ) ) {
			    wc_add_notice( 'Данные успешно сохранены.<br> Сейчас вы будете перенаправлены на другую страницу...' );
		    }
            else {
	            wc_add_notice( 'Данные успешно сохранены.' );
            }

		    wp_safe_redirect( THEMESFLAT_PROTOCOL . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		    exit;
	    }
    }

	public static function get_user_specialty_id( $user_id ) {
		return (int) get_user_meta( $user_id, 'specialty', true );
	}

	public static function get_user_other_specialty( $user_id ) {
		return (string) get_user_meta( $user_id, 'other_specialty', true );
	}

	public static function user_has_specialty( $userId ) {
		$id      = self::get_user_specialty_id( $userId );
		$allowed = Specialty::get_allowed_specialties();

		if ( $id === - 1 ) {
			$otherSpecialty = self::get_user_other_specialty( $userId );

			return ! empty( $otherSpecialty );
		}

		return $id > 0 && in_array( $id, $allowed );
	}


	public static function get_user_age_specialties( $user_id ) {
		return (array) get_user_meta( $user_id, 'age_specialty', true );
	}

	public static function user_has_age_specialty( $userId ) {
		$user_age_specialties      = self::get_user_age_specialties( $userId );
		$age_terms_ids = get_terms('age', [
			'hide_empty' => false,
			'fields' => 'ids'
		]);

		foreach ($user_age_specialties as $age_specialty) {
		    if ( in_array( $age_specialty, $age_terms_ids) ) {
			    return true;
		    }
		}

		return false;
	}

}

$MedUserProfile = new MedUserProfile();