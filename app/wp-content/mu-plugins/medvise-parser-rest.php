<?php
/**
 * Plugin Name: Medvise Parser REST Loader
 * Description: Автозагрузка REST-эндпоинтов парсера в MU-режиме.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_file = WP_CONTENT_DIR . '/plugins/medvise-parser-rest/medvise-parser-rest.php';

if ( file_exists( $plugin_file ) ) {
	require_once $plugin_file;
}
