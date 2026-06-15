<?php

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

class Medviselogin_Woocommerce {

	public function __construct() {
		// Добавляем вкладку телеграм аккаунта
		add_filter( 'woocommerce_get_query_vars', [ $this, 'woocommerce_get_query_vars' ], 10, 1 );
		add_filter( 'woocommerce_endpoint_telegram-tab_title', [ $this, 'woocommerce_endpoint_telegram_tab_title' ], 10, 1 );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'woocommerce_account_menu_items' ], 101, 1 );
		add_action( 'woocommerce_account_telegram-tab_endpoint', [ $this, 'telegram_tab_content' ], 10, 1 );

		// Отвязка телеги
		add_action( 'template_redirect', [ $this, 'telegram_unlink_action' ] );

		// Перевод Woocommerce
		add_filter( 'gettext_woocommerce', [ $this, 'gettext_woocommerce' ], 10, 3 );
	}

	public function woocommerce_get_query_vars( $vars ) {
		$vars['telegram-tab'] = 'telegram-tab';

		return $vars;
	}

	public function woocommerce_endpoint_telegram_tab_title() {
		return "Telegram аккаунт";
	}

	public function woocommerce_account_menu_items( $items ) {

		$account_position = array_search( 'edit-account', array_keys( $items ) ) + 1;

		return array_slice( $items, 0, $account_position, true ) +
		       [ 'telegram-tab' => 'Telegram аккаунт' ] +
		       array_slice( $items, $account_position, count( $items ), true );
	}

	public function telegram_tab_content(): void {

		wc_print_notices();

		$current_user = wp_get_current_user();
        $telegram_account = Medviselogin_Telegram::telegram_user_get_by_id($current_user->ID);

        if ( $telegram_account === NULL ):
	        $bot_name = carbon_get_theme_option( 'otplogin_telegram_botname' );
	        $action = 'linkacc_';

	        $cipher_token = get_option( '_otplogin_encrypt_token', 'FDS#DQD#$@!@#' );
	        $iv = hex2bin("341f36f9164e8bd39cd37a41ccdd10ed");
	        $encrypted = openssl_encrypt($current_user->ID, 'AES-128-CBC', $cipher_token, OPENSSL_RAW_DATA, $iv);
	        $encrypted = bin2hex($encrypted);
            $action .= $encrypted;
            ?>
            <div class="templates-table-container">
                <p>
                    После прикрепления Telegram аккаунта вы получите возможность быстрой авторизации.
                    И доступ к одной статье, если ранее у вас не было доступа.
                </p>
                <p>
                    Для прикрепления - отсканируйте камерой или нажмите по QR коду
                </p>
                <a href="<?= "https://t.me/{$bot_name}?start=" . $action; ?>" target="_blank"
                   style="display: table; margin: 0 auto; text-decoration: none; border-bottom: none;">
                    <?= Medviselogin_Frontend::generate_telegram_qr($action); ?>
                </a>
            </div>
        <?php
        else:
	        ?>
            <div class="templates-table-container">
		        <?php if ( empty( $current_user->user_email ) ): ?>
                    <p>
                        Аккаунт на сайте не может существовать без прикрепления к вашему Telegram или Email.
                        Поэтому, для открепления Telegram от текущего аккаунта,
                        сначала необходимо прикрепить к нему ваш Email.
                        Прикрепление Email осуществляется в пункте
                        «<a href="/my-account/edit-account/" target="_blank">Анкета</a>» в личном кабинете.
                    </p>
                    <p>
                        Если вы не хотите прикреплять к аккаунту на сайте Telegram или Email, вы можете
                        удалить свой аккаунт в пункте
                        «<a href="/my-account/delete-account/" target="_blank">Удалить аккаунт</a>».
                    </p>
		        <?php else: ?>
                    <p>
                        К вашему аккаунту уже прикреплен Telegram.<br>
                        Вы можете открепить свой аккаунт, для этого введите слово
                        «<strong>открепить</strong>» в поле подтверждения ниже
                    </p>
                    <form method="post" action="">
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="confirm_tg_unlink">Подтвердите открепление: <span class="required">*</span></label>
                            <input type="text" class="woocommerce-Input input-text" name="confirm_tg_unlink"
                                   id="confirm_tg_unlink">
                        </p>
                        <p>
                            <button type="submit" class="button">Открепить аккаунт</button>
                        </p>
                    </form>
		        <?php endif; ?>
            </div>
        <?php
        endif;

	}

	public function telegram_unlink_action() {
		//todo генерить nonce
		// Проверяем, что пользователь находится на странице отвязки аккаунта
		if ( ! isset( $_POST['confirm_tg_unlink'] ) || $_SERVER['REQUEST_URI'] !== '/my-account/telegram-tab/' ) {
			return;
		}

        $user = wp_get_current_user();
        // Если у пользователя не прикреплен Email - не даем отвязывать
        if ( empty( $user->user_email ) ) {
            return;
        }

		if ( mb_strtolower( $_POST['confirm_tg_unlink'] ) !== 'открепить' ) {
			wc_add_notice( 'Проверьте ввод проверочного слова', 'error' );

			return;
		}

		Medviselogin_Telegram::telegram_user_delete_by_id( $user->ID );

		wc_add_notice( 'Telegram аккаунт был откреплен от вашего аккаунта', 'success' );
	}

	public function gettext_woocommerce( $translation, $text, $domain ) {

		if ( $domain !== 'woocommerce' ) {
			return $translation;
		}

		if ( $text === 'Account details changed successfully.' ) {
			return 'Данные успешно сохранены.';
		}

        return $translation;

	}

}