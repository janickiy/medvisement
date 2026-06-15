<?php
/*
Plugin Name: Medvisement - Вход на сайт
Description: Реализация входа на сайт с Telegram, Email
Version: 0.0.1
Author: Roman Berdnikov
Author URI: https://t.me/romaberdnikov
Text Domain: medvise-otp-login
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('MEDVISE_LOGIN_PLUGIN_VERSION', '0.0.1');
define('MEDVISE_LOGIN_PLUGIN_URL', plugin_dir_url(__FILE__) );
define('MEDVISE_LOGIN_PLUGIN_DIR', plugin_dir_path(__FILE__) );
define('MEDVISE_LOGIN_PLUGIN_FILE', __FILE__ );

require_once ( MEDVISE_LOGIN_PLUGIN_DIR . "vendor/autoload.php");

require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_Backend.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_Frontend.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_Telegram.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_Settings.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_EmailConfirmation.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_PasswordChange.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_DeleteAccount.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_Woocommerce.php');
require_once (MEDVISE_LOGIN_PLUGIN_DIR . 'Medviselogin_Notifications.php');

$Medviselogin_Backend = new Medviselogin_Backend();
$Medviselogin_Telegram = new Medviselogin_Telegram();
$Medviselogin_Frontend = new Medviselogin_Frontend();
$Medviselogin_Frontend = new Medviselogin_Settings();
$Medviselogin_EmailConfirmation = new Medviselogin_EmailConfirmation();
$Medviselogin_PasswordChange = new Medviselogin_PasswordChange();
$Medviselogin_DeleteAccount = new Medviselogin_DeleteAccount();
$Medviselogin_Woocommerce = new Medviselogin_Woocommerce();
$Medviselogin_Notifications = new Medviselogin_Notifications();

// Отключаем уведомление админу о новом пароле юзера
if ( ! function_exists( 'wp_password_change_notification' ) ) {
	function wp_password_change_notification( $user ) {
		return;
	}
}