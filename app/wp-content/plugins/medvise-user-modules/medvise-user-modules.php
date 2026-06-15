<?php
/*
Plugin Name: Medvisement - Пользовательский функционал
Description: Заметки, шаблоны, таймер
Version: 1.0.1
Author: Roman Berdnikov
Text Domain: medvise-user-modules
*/

/*
 * Notes - это заметки по статье в единственном числе
 * Template - это шаблоны к статье, во множественном числе
 */

namespace MedviseUserModules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MedviseUserModules\Frontend;
use MedviseUserModules\Note;
use MedviseUserModules\Template;

define( 'MEDVISE_USER_MODULES_VERSION', '1.0.3' );
define( 'MEDVISE_USER_MODULES_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDVISE_USER_MODULES_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDVISE_USER_MODULES_FILE', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/Classes/Frontend.php';
require_once __DIR__ . '/Classes/Note.php';
require_once __DIR__ . '/Classes/Template.php';
require_once __DIR__ . '/Classes/Woocommerce.php';

function init_medvise_user_modules() {
	Note::getInstance()->setup();
	Template::getInstance()->setup();

	Frontend::getInstance()->setup();
	Woocommerce::getInstance()->setup();
}

init_medvise_user_modules();

function install() {
	global $wpdb;

	$charset_collate = '';
	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate = " DEFAULT CHARACTER SET $wpdb->charset";
	}
	if ( ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE $wpdb->collate";
	}

	$user_notes_table = $wpdb->prefix . 'medvise_user_notes';
	if ( $wpdb->get_var( 'show tables like "' . $user_notes_table . '"' ) != $user_notes_table ) {

		$sql = "CREATE TABLE " . $user_notes_table . " (    
                    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `post_id` BIGINT(20) UNSIGNED NOT NULL,
                    `title` TEXT,
                    `content` LONGTEXT,
                    `replace_original` TINYINT(1) DEFAULT 0,
                    PRIMARY KEY (id),
                    FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
                )" . $charset_collate . "; \n";

		$sql .= "ALTER TABLE `{$wpdb->prefix}medvise_user_notes` " .
		        "ADD UNIQUE `unique_index`(`post_id`, `user_id`); \n";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	$user_templates_table = $wpdb->prefix . 'medvise_user_templates';
	if ( $wpdb->get_var( 'show tables like "' . $user_templates_table . '"' ) != $user_templates_table ) {

		$sql = "CREATE TABLE " . $user_templates_table . " (    
                    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `post_id` BIGINT(20) UNSIGNED NOT NULL,
                    `title` TEXT,
                    `content` LONGTEXT,
                    PRIMARY KEY (id),
                    FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
                )" . $charset_collate . "; \n";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

}

register_activation_hook( MEDVISE_USER_MODULES_FILE, __NAMESPACE__ . '\install' );