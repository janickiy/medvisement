<?php
/*
Plugin Name: Medvisement - Рейтинг и Заметки для статей
Description: ...
Version: 1.0.0
Author: Medvisement
Text Domain: medvise-post-rating
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
};

include 'autoload.php';

new \MedvisementPostRating\Autoload();

register_activation_hook( __FILE__, [ '\\MedvisementPostRating\\Base', 'pluginActivation' ] );
register_deactivation_hook( __FILE__, [ '\\MedvisementPostRating\\Base', 'pluginDeactivation' ] );

\MedvisementPostRating\Base::init();