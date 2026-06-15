<?php
/*
Plugin Name: Medvisement - Визуальное дерево
Description: Для отображения заболеваний или препаратов в упорядоченном и древовидном виде
Version: 0.1.0.6
Author: Roman Berdnikov
Text Domain: medvise-visualtree
*/


namespace Medvisement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Medvisement\Classes\Helpers;
use Medvisement\Classes\Admin;
use Medvisement\Classes\AdminAjax;
use Medvisement\Classes\Frontend;
use Medvisement\Classes\REST;

define( 'MEDVISETREE_PLUGIN_VERSION', '0.1.6' );
define( 'MEDVISETREE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDVISETREE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDVISETREE_PLUGIN_FILE', __FILE__ );

require_once( MEDVISETREE_PLUGIN_DIR . "vendor/autoload.php" );

function medvise_visualtree_init() {

	//Оборачиваем в Eloquent
	$app = new Container();
	Facade::setFacadeApplication( $app );

	$app->singleton( 'db', function () use ( $app ) {
		global $wpdb;
		$capsule = new Manager;

		$capsule->addConnection( [
			'driver'    => 'mysql',
			'host'      => DB_HOST,
			'database'  => DB_NAME,
			'username'  => DB_USER,
			'password'  => DB_PASSWORD,
			'charset'   => $wpdb->charset,
			'collation' => $wpdb->collate,
			'prefix'    => 'wp_',
		] );

		$capsule->setEventDispatcher( new Dispatcher( $app ) );
		$capsule->setAsGlobal();
		$capsule->bootEloquent();

		return $capsule;
	} );

	DB::connection()->getPdo();

	REST::getInstance()->setup();

	//Админка
	if ( is_admin() ) {
		AdminAjax::getInstance()->setup();
		Admin::getInstance()->setup();
	}

	Frontend::getInstance()->setup();
}

medvise_visualtree_init();