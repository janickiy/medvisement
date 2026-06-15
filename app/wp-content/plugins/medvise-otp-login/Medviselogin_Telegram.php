<?php

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use \MedviseSubscriptions\Subscriber\Subscriber;

class Medviselogin_Telegram
{
    private static $instance = null;
    private $settings;
	private $host;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'rest_api_init']);

        add_filter( 'carbon_fields_before_field_save', [ $this, 'update_telegram_webhook' ] );

        register_activation_hook(MEDVISE_LOGIN_PLUGIN_FILE, array($this, 'install'));

	    if ( false === getenv('OTP_WM_CHANNEL_PORT') ) {
		    $this->host = '127.0.0.1';
	    }
	    else {
		    $this->host = 'php-workerman';
	    }
    }

    public function rest_api_init()
    {

        $namespace = 'telegram/v1';

        register_rest_route($namespace, 'webhook', [
            'methods' => ['POST'],
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => function (WP_REST_Request $request) {

                $headers = $request->get_headers();

                //Проверяем токен
                if (
                    isset($headers['x_telegram_bot_api_secret_token'][0])
                    && $headers['x_telegram_bot_api_secret_token'][0] == get_option( '_otplogin_telegram_token', '' )
                ) {
                    return true;
                } else {
                    return false;
                }

            },
        ]);
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        $telegram = new Api( get_option( '_otplogin_telegram_apikey', '' ) );
        $data = $request->get_json_params();

		// Дебаг
	    //$date = new DateTime('now');
	    //file_put_contents(MEDVISE_LOGIN_PLUGIN_DIR . 'debug.txt', $date->format('Y-m-d H:i:s'), FILE_APPEND);
	    //file_put_contents(MEDVISE_LOGIN_PLUGIN_DIR . 'debug.txt', print_r($data, true) . "\n\n", FILE_APPEND);

	    // Если запрос на авторизацию
        $matches = [];
        if ( isset($data['message']['text']) ) {
	        preg_match('/start ([a-z0-9]+)$/m', $data['message']['text'], $matches);
        }

        //Авторизация по QR коду - команда start с токеном
        if (!empty($matches)) {

            //Проверяем, существует ли уже такой пользователь по ID телеги
            $telegram_user = $this->telegram_user_get($data['message']['from']['id']);

            //Пользователь есть - авторизуем
            if ($telegram_user) {

                $auth_cookies = $this->get_auth_cookies($telegram_user->user_id);
	            $workerman_http_port = get_option( '_otplogin_workerman_http_port', '' );

                $client = new \GuzzleHttp\Client();
                $client->request('POST', "http://{$this->host}:{$workerman_http_port}", [
                    'json' => [
                        'tg_user_id' => $telegram_user->tg_user_id,
                        'session_id' => $matches[1],
                        'auth_cookies' => $auth_cookies,
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ]
                ]);

                return true;
            } //Пользователя не существует - сохраняем сессию и запрашиваем номер телефона
            else {

	            $telegram->sendMessage( [
		            'chat_id'              => $data['message']['chat']['id'],
		            'text'                 => "Ваш Telegram аккаунт не связан с аккаунтом на сайте.\n\n" .
		                                      "Пожалуйста перейдите в раздел " .
		                                      "«[Telegram аккаунт](https://medvisement.com/my-account/telegram-tab/)» в личном кабинете сайта и прикрепите свой Telegram.",
		            'parse_mode'           => 'Markdown',
		            'link_preview_options' => json_encode( [
			            'is_disabled' => true
		            ] )
	            ] );

	            return true;
            }

        }

	    // Если запрос на прикрепление аккаунта к уже существующему
	    $matches = [];
	    if ( isset($data['message']['text']) ) {
		    preg_match('/start linkacc_([a-z0-9]+)$/m', $data['message']['text'], $matches);
	    }

	    //Авторизация по QR коду - команда start с токеном
	    if ( ! empty( $matches ) ) {

			// Декодируем ID юзера
		    $cipher_token = get_option( '_otplogin_encrypt_token', 'FDS#DQD#$@!@#' );
			$iv = hex2bin("341f36f9164e8bd39cd37a41ccdd10ed");
		    $decrypted_user_id = hex2bin($matches[1]);
		    $decrypted_user_id = (int) openssl_decrypt($decrypted_user_id, 'AES-128-CBC', $cipher_token, OPENSSL_RAW_DATA, $iv);

		    // Существует ли такой юзер
		    $user = get_userdata( $decrypted_user_id );
		    if ( $user === false ) {
			    $telegram->sendMessage( [
				    'chat_id' => $data['message']['chat']['id'],
				    'text'    => 'Указанный аккаунт не найден на сайте - невозможно прикрепить Telegram.',
				    'remove_keyboard' => true
			    ] );

			    return true;
		    }

		    // Прикреплен ли юзер на сайте к какой-то телеге
		    $internal_user = $this->telegram_user_get_by_id( $decrypted_user_id );
		    if ( $internal_user !== null ) {

				// Прикреплен к этой же телеге
				if ( $internal_user->tg_user_id == $data['message']['chat']['id'] ) {
					$telegram->sendMessage( [
						'chat_id'    => $data['message']['chat']['id'],
						'text'       => "Вы уже прикрепили свой аккаунт к этому Telegram аккаунту.",
						'remove_keyboard' => true
					] );

					return true;
				}
				else {
					$telegram->sendMessage( [
						'chat_id'    => $data['message']['chat']['id'],
						'text'       => "Ваш аккаунт на сайте уже прикреплен к Telegram аккаунту.\n" .
						                "Вы можете открепить аккаунт в личном кабинете на сайте, пункт " .
						                "«[Telegram аккаунт](https://medvisement.com/my-account/telegram-tab/)».",
						'parse_mode' => 'Markdown',
						'link_preview_options' => json_encode([
							'is_disabled' => true
						]),
						'remove_keyboard' => true
					] );

					return true;
				}
		    }

		    // Прикреплен ли этот аккаунт к другому юзеру
		    $telegram_user = $this->telegram_user_get($data['message']['from']['id']);
		    if ( $telegram_user !== NULL ) {

			    $user = get_user_by( 'id', $telegram_user->user_id );

				if ( ! $user ) {
					return false;
				}

			    if ( empty( $user->user_email ) ) {
				    $telegram->sendMessage( [
					    'chat_id'    => $data['message']['chat']['id'],
					    'text'       => "Данный Telegram аккаунт уже прикреплен к аккаунту на сайте.\n" .
					                    "Возможно, у вас уже имеется второй аккаунт на сайте. " .
					                    "[Удалите](https://medvisement.com/my-account/delete-account/) " .
					                    "один из них, затем [прикрепите](https://medvisement.com/my-account/edit-account/) " .
					                    "Email или Telegram к оставшемуся. ",
					    'link_preview_options' => json_encode([
						    'is_disabled' => true
					    ]),
					    'parse_mode' => 'Markdown',
				    ] );
			    }
				else {
					$btn = Keyboard::button( [
						'text'            => 'Открепить Аккаунт',
						'request_contact' => false,
					] );

					$keyboard = Keyboard::make( [
						'keyboard'          => [ [ $btn ] ],
						'resize_keyboard'   => true,
						'one_time_keyboard' => true
					] );

					$telegram->sendMessage( [
						'chat_id'      => $data['message']['chat']['id'],
						'text'         => "Ваш Telegram аккаунт уже прикреплен к аккаунту на сайте.\n" .
						                  "Вы можете открепить аккаунт, нажав кнопку «Открепить Аккаунт».",
						'reply_markup' => $keyboard
					] );
				}

			    return true;
		    }

		    // Награда
		    if ( Subscriber::available_for_award( $decrypted_user_id, $data['message']['chat']['id'] ) ) {
			    Subscriber::award( $decrypted_user_id );
		    }

		    // Прикрепляем аккаунт к телеграмму
		    $this->telegram_user_set( $data['message']['chat']['id'], $decrypted_user_id );
		    $telegram->sendMessage( [
			    'chat_id'         => $data['message']['chat']['id'],
			    'text'            => 'Ваш Telegram был успешно прикреплен к аккаунту.',
			    'remove_keyboard' => true
		    ] );



		    return true;
	    }

        // Регистрируем аккаунт
        if ( $data['message']['text'] === 'Подтвердить Регистрацию' ) {

            //Проверяем если пользователь уже зареган
            $existing_user = $this->telegram_user_get($data['message']['chat']['id']);

            //Получаем текущую сессию пользовтеля
            $current_user_session = $this->session_get($data['message']['chat']['id']);

            //Пользователь уже зареган
            if (!empty($existing_user)) {
                $telegram->sendMessage([
                    'chat_id' => $data['message']['chat']['id'],
                    'text' => 'Вы уже зарегистрированы!',
                    'remove_keyboard' => true
                ]);

                return true;
            }

            //Вставляем нового пользователя в WP
            $new_user_id = wp_insert_user(
                [
                    'user_login' => Medviselogin_Backend::generate_hash_login(),
                    'user_pass' => wp_generate_password(),
                    'role' => 'subscriber'
                ]
            );

            // Добавляем метку для беспрепятственной первой смены пароля
            update_user_meta($new_user_id, 'password_change_allowed', true);

	        // Награда
	        if ( Subscriber::available_for_award( $new_user_id, $data['message']['chat']['id'] ) ) {
		        Subscriber::award( $new_user_id );
	        }

            //Связываем с ID телеграмма
            $this->telegram_user_set($data['message']['chat']['id'], $new_user_id);

            $telegram->sendMessage([
                'chat_id' => $data['message']['chat']['id'],
                'text' => 'Вы были зарегистрированы!',
                'remove_keyboard' => true
            ]);

            $auth_cookies = $this->get_auth_cookies($new_user_id);
	        $workerman_http_port = get_option( '_otplogin_workerman_http_port', '' );

            //Передаем куки в браузер
            $client = new \GuzzleHttp\Client();
            $client->request('POST', "http://{$this->host}:{$workerman_http_port}", [
                'json' => [
                    'tg_user_id' => $data['message']['chat']['id'],
                    'session_id' => $current_user_session->session_qr_id,
                    'auth_cookies' => $auth_cookies,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            return true;
        }

		// Отвязываем аккаунт
	    if ( $data['message']['text'] === 'Открепить Аккаунт' ) {

		    $existing_user = $this->telegram_user_get($data['message']['chat']['id']);

			if ( empty( $existing_user ) ) {
				$telegram->sendMessage([
					'chat_id' => $data['message']['chat']['id'],
					'text' => 'Ваш аккаунт не прикреплен к какому-либо аккаунту на сайте.',
					'remove_keyboard' => true
				]);
				return true;
			}
			else {
				$this->telegram_user_delete($existing_user->tg_user_id);

				$telegram->sendMessage([
					'chat_id' => $data['message']['chat']['id'],
					'text' => "Ваш Telegram аккаунт был откреплен от аккаунта на сайте.\n" .
					          'Теперь вы можете [прикрепить Telegram](https://medvisement.com/my-account/telegram-tab/) к новому аккаунту на сайте.',
					'parse_mode' => 'Markdown',
					'link_preview_options' => json_encode([
						'is_disabled' => true
					]),
					'remove_keyboard' => true
				]);
				return true;
			}

	    }

        //Любое другое действие
	    if ( isset($data['message']['chat']['id']) ) {
		    $telegram->sendMessage([
			    'chat_id' => $data['message']['chat']['id'],
			    'text' => 'Для авторизации - перейдите на сайт ' . get_site_url() . ' и отсканируйте QR код.',
			    'remove_keyboard' => true
		    ]);
	    }

        return true;
    }

    //Связываем сессию браузера с ID телеграмма
    private function session_set($tg_user_id, $session_qr_id)
    {
        global $wpdb;

        return $wpdb->replace('telegram_sessions',
            [
                'tg_user_id' => $tg_user_id,
                'session_qr_id' => $session_qr_id
            ],
            ['%d', '%s']
        );
    }

    private function session_get($tg_user_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM telegram_sessions WHERE tg_user_id = %d;", $tg_user_id)
        );
    }

    public static function telegram_user_set($tg_user_id, $wp_user_id)
    {
        global $wpdb;

		// Исторические данные
	    $wpdb->query( $wpdb->prepare( 'INSERT INTO `telegram_historical` VALUES (%d) ON DUPLICATE KEY UPDATE tg_user_id=tg_user_id;', $tg_user_id ) );

	    return $wpdb->insert($wpdb->prefix . 'user_telegram',
            [
                'user_id' => $wp_user_id,
                'tg_user_id' => $tg_user_id
            ]
        );
    }

	public static function telegram_user_delete($tg_user_id)
	{
		global $wpdb;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}user_telegram WHERE tg_user_id = %d", $tg_user_id ) );
	}

	public static function telegram_user_delete_by_id( $user_id ) {
		global $wpdb;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}user_telegram WHERE user_id = %d", $user_id ) );
	}

    //Получаем пользователя сайта по ID телеграмма
    public static function telegram_user_get($tg_user_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}user_telegram WHERE tg_user_id = %d;", $tg_user_id)
        );
    }

	public static function telegram_user_get_by_id( $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}user_telegram WHERE user_id = %d;", $user_id)
		);
	}

    private function get_auth_cookies($user_id)
    {
        $user = get_user_by('ID', $user_id);

        //14 дней
	    $expiration_days = 14;
        $expiration = time() + 1209600;

        $manager = WP_Session_Tokens::get_instance($user->ID);
        $token = $manager->create($expiration);

        $auth_cookie = wp_generate_auth_cookie($user->ID, $expiration, 'secure_auth', $token);
        $logged_in_cookie = wp_generate_auth_cookie($user->ID, $expiration, 'logged_in', $token);

        $return = [
            [
                'path' => PLUGINS_COOKIE_PATH,
                'name' => SECURE_AUTH_COOKIE,
                'cookie' => $auth_cookie,
                'expire' => $expiration_days,
                'domain' => COOKIE_DOMAIN
            ],
            [
                'path' => ADMIN_COOKIE_PATH,
                'name' => SECURE_AUTH_COOKIE,
                'cookie' => $auth_cookie,
                'expire' => $expiration_days,
                'domain' => COOKIE_DOMAIN
            ],
            [
                'path' => COOKIEPATH,
                'name' => LOGGED_IN_COOKIE,
                'cookie' => $logged_in_cookie,
                'expire' => $expiration_days,
                'domain' => COOKIE_DOMAIN
            ]
        ];

        return $return;
    }

	public function update_telegram_webhook( \Carbon_Fields\Field\Field $field ) {

		if ( $field->get_base_name() !== 'otplogin_telegram_token' ) {
			return $field;
		}

		// Поля не изменились - не нужно обнвлять
		if (
			get_option( '_otplogin_telegram_apikey', '' ) === $_POST['carbon_fields_compact_input']['_otplogin_telegram_apikey'] &&
			get_option( '_otplogin_telegram_token', '' ) === $_POST['carbon_fields_compact_input']['_otplogin_telegram_apikey']
		) {
			return $field;
		}

		$telegram = new Api($_POST['carbon_fields_compact_input']['_otplogin_telegram_apikey']);

		//Выставляем вебхук для телеграмма
		$telegram->setWebhook([
			'url' => get_site_url(null, '/wp-json/telegram/v1/webhook', 'https'),
			'secret_token' => $_POST['carbon_fields_compact_input']['_otplogin_telegram_token']
		]);

		return $field;
	}

    public function install()
    {
        //Создаем таблицы
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = '';
        if (!empty($wpdb->charset)) {
            $charset_collate = " DEFAULT CHARACTER SET $wpdb->charset";
        }
        if (!empty($wpdb->collate)) {
            $charset_collate .= " COLLATE $wpdb->collate";
        }

        //Таблица связанных сессий для диалога браузер <-> телеграм бот
        $telegram_sessions_table = 'telegram_sessions';

        if ($wpdb->get_var('show tables like "' . $telegram_sessions_table . '"') != $telegram_sessions_table) {

            $sql = "CREATE TABLE " . $telegram_sessions_table . " (
                tg_user_id bigint(20) UNSIGNED NOT NULL,
                session_qr_id VARCHAR(255) NOT NULL,
                UNIQUE KEY tg_user_id (tg_user_id)
                )" . $charset_collate . ";";

            dbDelta($sql);
        }

        //Связываем ИД пользователя в телеграмме и на сайте
        $wp_user_telegram_table = $wpdb->prefix . 'user_telegram';

        if ($wpdb->get_var('show tables like "' . $wp_user_telegram_table . '"') != $wp_user_telegram_table) {

            $sql = "CREATE TABLE " . $wp_user_telegram_table . " (
                user_id bigint(20) UNSIGNED NOT NULL,
                tg_user_id bigint(20) UNSIGNED NOT NULL,
                PRIMARY KEY (tg_user_id),
                FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
                )" . $charset_collate . ";";

            dbDelta($sql);
        }

		// Исторические аккаунты телеграмма
	    $telegram_historical_table = 'telegram_historical';

	    if ($wpdb->get_var('show tables like "' . $telegram_historical_table . '"') != $telegram_historical_table) {

		    $sql = "CREATE TABLE " . $telegram_historical_table . " (
                tg_user_id bigint(20) UNSIGNED NOT NULL,
                PRIMARY KEY (tg_user_id)
                )" . $charset_collate . ";";

		    dbDelta($sql);
	    }
    }

}