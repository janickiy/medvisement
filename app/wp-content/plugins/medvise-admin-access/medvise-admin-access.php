<?php
/*
Plugin Name: Medvisement - Авторы и Редакторы
Description: Права, преднаполнение данных (шаблон препаратов. авторство и т.д.)
Version: 0.0.0.1
Author: Roman Berdnikov
Text Domain: medvise-admin-access
*/

namespace MedvisementAdminAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDVISEADMINACCESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDVISEADMINACCESS_PLUGIN_FILE', __FILE__ );

spl_autoload_register(
	function ( $class ) {
		$prefix  = 'MedvisementAdminAccess\\';
		$basedir = MEDVISEADMINACCESS_PLUGIN_DIR . "classes/";

		$prefix_len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $prefix_len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $prefix_len );

		$file = $basedir . str_replace( '\\', '/', $relative_class ) . '.php';

		require_once( $file );
	}
);

function medvisement_admin_access_init() {

	Admin::factory();
	Media::factory();
	Post::factory();
	PostList::factory();
	Taxonomy::factory();
	User::factory();

}

medvisement_admin_access_init();



