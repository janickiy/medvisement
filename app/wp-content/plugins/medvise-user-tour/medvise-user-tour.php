<?php
/*
Plugin Name: Medvisement - Инструкции для пользователей
Description:
Version: 1.0.0.3
Author: Roman Berdnikov
Text Domain: medvise-user-tour
*/

namespace MedvisementUserTour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDVISEUSERTOUR_PLUGIN_VERSION', '0.0.2.10' );
define( 'MEDVISEUSERTOUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDVISEUSERTOUR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDVISEUSERTOUR_PLUGIN_FILE', __FILE__ );

spl_autoload_register(
	function ( $class ) {
		$prefix  = 'MedvisementUserTour\\';
		$basedir = MEDVISEUSERTOUR_PLUGIN_DIR . "Classes/";

		$prefix_len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $prefix_len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $prefix_len );

		$file = $basedir . str_replace( '\\', '/', $relative_class ) . '.php';

		require_once( $file );
	}
);

function medvise_usertour_init() {

	Frontend::getInstance()->setup();;

}

medvise_usertour_init();