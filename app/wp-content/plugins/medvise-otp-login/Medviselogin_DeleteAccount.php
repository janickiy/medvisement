<?php

use MedviseSubscriptions\Subscriber\Subscriber;

class Medviselogin_DeleteAccount {

	public function __construct() {
		$this->init();
	}

	public function init() {
		// Добавляем в меню
		add_filter( 'woocommerce_get_query_vars', [ $this, 'woocommerce_get_query_vars' ], 10, 1 );

		// Изменение заголовка страницы
		add_filter( 'woocommerce_endpoint_delete-account_title', [ $this, 'custom_endpoint_page_title' ], 10, 1 );

		// Добавление пункта в меню My Account (после password-change)
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_delete_account_link_my_account' ], 999 );

		// Регистрация содержимого страницы
		add_action( 'woocommerce_account_delete-account_endpoint', [ $this, 'delete_account_form' ] );

        // Удаление аккаунта
        add_action( 'template_redirect', [ $this, 'delete_account_action' ] );
	}
	
	public function woocommerce_get_query_vars( $vars ) {
		$vars['delete-account'] = 'delete-account';

		return $vars;
	}

	public function custom_endpoint_page_title( $title ) {
		return 'Удалить аккаунт';
	}

	public function add_delete_account_link_my_account( $items ) {
		$new_items = [];

		foreach ( $items as $key => $value ) {
			$new_items[ $key ] = $value;

			// Вставляем после password-change
			if ( $key === 'password-change' ) {
				$new_items['delete-account'] = 'Удалить аккаунт';
			}
		}

		return $new_items;
	}

	public function delete_account_form() {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return;
		}

        $password_change_allowed = get_user_meta( $user->ID, 'password_change_allowed', true );
        $password_required = ! empty( $user->user_email ) && ! $password_change_allowed;

		wc_print_notices();

		$has_subscription = $this->user_can_delete_account( $user->ID );

		if ( $has_subscription ):
			?>
            <p>
                У вас есть активная подписка, для удаления аккаунта необходимо обратиться в техподдержку
                <a href="mailto:info@medvisement.com">info@medvisement.com</a> с запросом на удаление аккаунта.
            </p>
            <p>
                <strong>
                    Почта, с которой вы обращаетесь должна быть подтверждена в
                    <a href="/my-account/edit-account/">аккаунте</a>.
                </strong>
            </p>
			<?php
			return;
		endif;

		if ( $password_required ):
			?>
            <p>
                Вы можете удалить свой аккаунт, если у вас отсутствуют активные подписки.
                Для этого введите свой текущий пароль.
            </p>

            <form method="post" action="">
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="current_password">Текущий пароль&nbsp;<span class="required">*</span></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text"
                           name="current_password" id="current_password"/>
                </p>
                <p>
                    <button type="submit" class="button" name="delete_account">Удалить аккаунт</button>
                </p>
            </form>
		<?php else: ?>
            <p>
                Для удаления аккаунта введите слово «<strong>удалить</strong>» в поле подтверждения ниже
            </p>

            <form method="post" action="">
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="confirm_delete">Подтвердите удаление: <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input input-text" name="confirm_delete" id="confirm_delete">
                </p>
                <p>
                    <button type="submit" class="button" name="delete_account">Удалить аккаунт</button>
                </p>
            </form>
		<?php
		endif;
	}

    public function delete_account_action() {
	    //todo генерить nonce
        // Проверяем, что пользователь находится на странице удалении аккаунта
	    if ( ! isset( $_POST['delete_account'] ) || $_SERVER['REQUEST_URI'] !== '/my-account/delete-account/' ) {
            return;
	    }

	    $user = wp_get_current_user();

	    if ( ! $user->exists() ) {
		    return;
	    }

	    $password_change_allowed = get_user_meta( $user->ID, 'password_change_allowed', true );
	    $password_required = ! empty( $user->user_email ) && ! $password_change_allowed;

        // Проверяем ввод
        if ( $password_required ) {
	        $current_password = $_POST['current_password'] ?? '';

	        if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
		        wc_add_notice( 'Неправильный текущий пароль', 'error' );
                return;
	        }
        }
        else {
            if ( mb_strtolower($_POST['confirm_delete']) !== 'удалить' ) {
	            wc_add_notice( 'Проверьте ввод проверочного слова', 'error' );
	            return;
            }
        }

	    global $wpdb;
	    require_once ABSPATH . 'wp-admin/includes/user.php';

	    wp_delete_user( $user->ID );

	    // Принудительно разлогиниваем
	    wp_logout();

	    // Перенаправляем на главную
	    wp_safe_redirect( home_url() );
	    exit;
    }

	private function user_can_delete_account( $user_id ) {
		// Проверяем активнае подписки на специальности
		$specialties        = Subscriber::historySpecialties( $user_id );
		$current_date       = new DateTime();
		$active_specialties = array();
		if ( !empty( $specialties ) ) {
			foreach ( $specialties as $specialty ) {
				$expiry_date = new DateTime( $specialty->date_expiry );
				if ( $current_date < $expiry_date ) {
					array_push( $active_specialties, $specialty );
				}
			}
		}

		// Проверяем активные подписки
		$subscriptions = Subscriber::hasSubscription();

		return ! empty( $active_specialties ) || $subscriptions;
	}
}