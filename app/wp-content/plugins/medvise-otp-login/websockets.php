<?php
require_once __DIR__ . '/vendor/autoload.php';

//Wordpress
const WP_USE_THEMES      = false;
const COOKIE_DOMAIN      = false;
require_once dirname(__DIR__,3) . "/wp-load.php";

use Workerman\Worker;
use Telegram\Bot\Api;

if ( false === getenv('OTP_WM_CHANNEL_PORT') ) {
	$host = '127.0.0.1';
}
else {
	$host = '0.0.0.0';
}


// Сервер каналов (для их коммуникации меж собой)
$channel_server = new Channel\Server( $host, get_option( '_otplogin_workerman_channel_port', 0 ) );

// Вебсокеты. url - /websocket (прописан в nginx)
$ws_worker = new Worker( "websocket://{$host}:" . get_option( '_otplogin_workerman_websocket_port', 0 ) );
$ws_logins = [];

$ws_worker->onWorkerStart = function($worker) use ($host) {
	Channel\Client::connect( $host, get_option( '_otplogin_workerman_channel_port', 0 ) );

    // Пришел запрос с сервера - оповещаем нужное подключение
    Channel\Client::on('broadcast', function($data)use($worker){

        $telegram = new Api( get_option( '_otplogin_telegram_apikey', '' ) );

        //Ищем нужное подключение
        global $ws_logins;
        $data = json_decode($data, true);
        $connection_id = array_search($data['session_id'], $ws_logins);

        //Отправляем данные для авторизации
        if ($connection_id && isset($worker->connections[$connection_id])) {
            $worker->connections[$connection_id]->send(json_encode($data['auth_cookies']));
            $telegram->sendMessage([
                'chat_id' => $data['tg_user_id'],
                'text' => 'Вы были успешно авторизованы.'
            ]);
        }
        else {
            $telegram->sendMessage([
                'chat_id' => $data['tg_user_id'],
                'text' => "Ссылка для авторизации устарела, пожалуйста, обновите страницу и попробуйте снова.\n" .
                          "Возможно, вы открыли сайт на мобильном устройстве в режиме предпросмотра. Скопируйте ссылку и откройте сайт напрямую в браузере."
            ]);
        }

    });
};

$ws_worker->onMessage = function ($connection, $json_string) {
    global $ws_logins;

    $data = parseJSON($json_string);

    //ID клиента всегда 56 символов
    if ( empty($data['id']) || strlen($data['id']) != 56) {
        $connection->close(json_encode('Security error'));
        return true;
    }

    //Связываем ID подключения с ID клиента
    $ws_logins[$connection->id] = $data['id'];

    var_dump($ws_logins); echo "\n";
    return true;
};


$ws_worker->onClose = function ($connection) {
    global $ws_logins;

    //При закрытии подключения убираем связь с ID клиента
    unset($ws_logins[$connection->id]);

    return true;
};


/* ----------------------------------- */
// Далее идет часть для запросов со стороны сервера
$http_worker = new Worker( "http://{$host}:" . get_option( '_otplogin_workerman_http_port', 0 ) );

$http_worker->onWorkerStart = function($worker) use ($host) {
    // Channel client connect to Channel Server.
	Channel\Client::connect( $host, get_option( '_otplogin_workerman_channel_port', 0 ) );
};

//Здесь приходит запрос с локального сервера при успешной авторизации пользователя - передаем в браузер
$http_worker->onMessage = function ($connection, $request) {
    //Оповещаем подключения websocket
    Channel\Client::publish('broadcast', json_encode($request->post()));
    $connection->close('OK!');
    return true;
};

// Run worker
Worker::runAll();

// Helpers
function parseJSON($json_string) {
    $data = json_decode($json_string, true);

    if (empty($json_string) || ! is_string($json_string) || ! is_array($data) || empty($data) || json_last_error() != 0)
        return false;

    return $data;
}