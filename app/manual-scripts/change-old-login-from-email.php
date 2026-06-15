<?php
// Запуск: wp eval-file manual-scripts/change-old-login-from-email.php
// Удаление емейла в логине у аккаунтов

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$query = "SELECT ID, user_login FROM `{$wpdb->prefix}users` WHERE user_login LIKE '%@%';";

$users = $wpdb->get_results( $query );

foreach ( $users as $user ) {

	if ( ! is_email( $user->user_login) ) {
		continue;
	}

	$new_login = Medviselogin_Backend::generate_hash_login();

	$query = "UPDATE `{$wpdb->prefix}users` SET `user_login` = '{$new_login}' WHERE `ID` = {$user->ID};";

	$wpdb->query($query);

	echo "Заменен {$user->ID} {$user->user_login} на {$new_login} \n";
}