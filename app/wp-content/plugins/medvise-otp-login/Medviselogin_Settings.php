<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Medviselogin_Settings {

	public function __construct() {
		$this->init();
	}


	public function init() {
		add_action( 'carbon_fields_register_fields', [ $this, 'theme_fields' ] );
	}

	public function theme_fields() {
		$fields = [
			Field::make( 'text', 'otplogin_telegram_botname', 'Имя бота' )
			     ->set_required( true )
			     ->set_help_text(
				     'Без @, например Medvisement_Bot'
			     ),
			Field::make( 'text', 'otplogin_telegram_apikey', 'API ключ' )
			     ->set_required( true )
			     ->set_help_text(
				     'Выдается botfather, формата <bot_id>:<ключ> '
			     ),
			Field::make( 'text', 'otplogin_telegram_token', 'Токен' )
			     ->set_required( true )
			     ->set_help_text(
				     'Собственный токен, для подписи запросов X-Telegram-Bot-Api-Secret-Token '
			     ),
			Field::make( 'text', 'otplogin_encrypt_token', 'Токен симметричного шифрования' )
			     ->set_required( true )
				->set_attribute( 'maxLength', '16' )
			     ->set_help_text(
				     'Собственный токен, для AES-128 шифрования чувствительных данных'
			     ),
			Field::make( 'text', 'otplogin_workerman_channel_port', 'Workerman: Channel порт' )
			     ->set_required( true )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'max', '65535' )
			     ->set_attribute( 'min', '1' )
			     ->set_attribute( 'step', '1' )
			     ->set_default_value( 2206 )
			     ->set_help_text(
				     'Порт для обмена сообщениями между воркерами. ' .
				     'Каждый порт должен быть уникальным в рамках одного сервера и не быть занятым другим процессом. ' .
				     'Не забудьте перезапустить supervisor (supervisorctl restart workerman-prod:*).'
			     ),
			Field::make( 'text', 'otplogin_workerman_websocket_port', 'Workerman: websocket порт' )
			     ->set_required( true )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'max', '65535' )
			     ->set_attribute( 'min', '1' )
			     ->set_attribute( 'step', '1' )
			     ->set_default_value( 2346 )
			     ->set_help_text(
				     'Порт для подключения к websocket, он же указан в конфиге nginx для проксирования '
			     ),
			Field::make( 'text', 'otplogin_workerman_http_port', 'Workerman: http порт' )
			     ->set_required( true )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'max', '65535' )
			     ->set_attribute( 'min', '1' )
			     ->set_attribute( 'step', '1' )
			     ->set_default_value( 2345 )
			     ->set_help_text(
				     'Порт для передачи данных из бэкенда в Workerman и последующей отправкой в активное WS подключение '
			     )
		];

		Container::make( 'theme_options', 'Интеграция Telegram' )
		         ->set_page_parent( 'options-general.php' )
		         ->set_page_file( 'otp-login' )
		         ->where( 'current_user_role', 'IN', array( 'administrator' ) )
		         ->add_fields( $fields );
	}


}