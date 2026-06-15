<?php /** @noinspection SqlNoDataSourceInspection */
/*
Plugin Name: Medvisement - Котел (денежный)
Description: Отчисления по специальностям и на платформу
Version: 1.0.0
Author: Medvisement
Author URI: medvisement.com
Text Domain: medvise-money-pot
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MedviseMoneyPot\Woocommerce;
use MedviseMoneyPot\CarbonSettings;
use MedviseMoneyPot\Admin;
use MedviseMoneyPot\Frontend;
use MedviseSubscriptions\ArticleAccess\ArticleAccess;
use MedviseSubscriptions\SpecialtyAccess\SpecialtyAccess;

define( 'MEDVISE_MONEYPOT_PLUGIN_VERSION', '1.0.0' );
define( 'MEDVISE_MONEYPOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDVISE_MONEYPOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDVISE_MONEYPOT_PLUGIN_FILE', __FILE__ );

require_once( MEDVISE_MONEYPOT_PLUGIN_DIR . "install.php" );
require_once( MEDVISE_MONEYPOT_PLUGIN_DIR . "includes/Helper.php" );
require_once( MEDVISE_MONEYPOT_PLUGIN_DIR . "includes/Woocommerce.php" );
require_once( MEDVISE_MONEYPOT_PLUGIN_DIR . "includes/CarbonSettings.php" );
require_once( MEDVISE_MONEYPOT_PLUGIN_DIR . "includes/Admin.php" );
require_once( MEDVISE_MONEYPOT_PLUGIN_DIR . "includes/Frontend.php" );

function medvise_moneypot_install() {
	register_activation_hook( __FILE__, 'MedviseMoneyPot\install' );
}

medvise_moneypot_install();

function medvise_moneypot_constructor() {
	Woocommerce::getInstance()->setup();
	CarbonSettings::getInstance()->setup();
	Admin::getInstance()->setup();;
	Frontend::getInstance()->setup();;
}

add_action( 'plugins_loaded', 'medvise_moneypot_constructor' );