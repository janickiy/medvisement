<?php
/*
Plugin Name: Medvisement - Подписки
Description: Подписки и защищенный контент
Version: 0.0.1
Author: Roman Berdnikov
Author URI: https://t.me/romaberdnikov
Text Domain: medvise-member-area
*/

namespace MedviseSubscriptions;

use MedviseSubscriptions\Security;
use MedviseSubscriptions\Subscriber\Subscriber;
use MedviseSubscriptions\Woocommerce\Woocommerce;
use MedviseSubscriptions\Specialty\Specialty;
use MedviseSubscriptions\ArticleAccess\ArticleAccess;
use MedviseSubscriptions\SpecialtyAccess\SpecialtyAccess;
use MedviseSubscriptions\ThemePackAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDVISESUB_URL', plugin_dir_url( __FILE__ ) );
define( 'MEDVISESUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDVISESUB_FILE', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/Woocommerce.php';
require_once __DIR__ . '/includes/Subscriber.php';
require_once __DIR__ . '/includes/Specialty.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/ArticleAccess.php';
require_once __DIR__ . '/includes/ShareArticleAccess.php';
require_once __DIR__ . '/includes/ShareArticleWoocommerce.php';
require_once __DIR__ . '/includes/SpecialtyAccess.php';
require_once __DIR__ . '/includes/ThemePackAccess.php';

function init_medvise_subscriptions() {

	Security\setup();

	$woocommerce = new Woocommerce();
	$woocommerce->init();

	$subscriber = new Subscriber();
	$subscriber->init();

	$specialty = new Specialty();
	$specialty->init();

	$admin = new Admin();
	$admin->init();

	$article_access = new ArticleAccess();
	$article_access->init();

	$share_article_access = new ShareArticleAccess();
	$share_article_access->init();

	$share_article_woocommerce = new ShareArticleWoocommerce();
	$share_article_woocommerce->init();

	$specialty_access = new SpecialtyAccess();
	$specialty_access->init();

	$theme_pack_access = new ThemePackAccess();
	$theme_pack_access->init();
}

init_medvise_subscriptions();

function install() {
	global $wpdb;

	$charset_collate = '';
	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate = " DEFAULT CHARACTER SET $wpdb->charset";
	}
	if ( ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE $wpdb->collate";
	}

	$page_views_table = $wpdb->prefix . 'medvise_page_views';
	if ( $wpdb->get_var( 'show tables like "' . $page_views_table . '"' ) != $page_views_table ) {

		$sql = "CREATE TABLE " . $page_views_table . " (    
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` bigint(20) UNSIGNED NOT NULL,
                `post_id` bigint(20) UNSIGNED NOT NULL,
                `source` VARCHAR(36) DEFAULT '' NOT NULL,
                `date_open` datetime NOT NULL,
                `date_expiry` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_user_post_source` (`user_id`, `post_id`, `source`),
                INDEX `idx_user_post_expiry` (`user_id`, `post_id`, `date_expiry`),
                INDEX `idx_source` (`source`),
                CONSTRAINT FK_PostID FOREIGN KEY (post_id)
                REFERENCES {$wpdb->prefix}posts(ID)
                ON DELETE CASCADE
                )" . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	$subscriptions_table = $wpdb->prefix . 'medvise_subscriptions';
	if ( $wpdb->get_var( 'show tables like "' . $subscriptions_table . '"' ) != $subscriptions_table ) {

		$sql = "CREATE TABLE " . $subscriptions_table . " (    
                    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `order_id` BIGINT(20) UNSIGNED NOT NULL,
                    `start_date` datetime NOT NULL,
                    `end_date` datetime NOT NULL,
                    PRIMARY KEY (id),
                    FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
                    FOREIGN KEY (order_id) REFERENCES {$wpdb->prefix}wc_orders(id) ON DELETE CASCADE
                )" . $charset_collate . "; \n";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	$specialty_views_table = $wpdb->prefix . 'medvise_specialty_views';
	if ( $wpdb->get_var( 'show tables like "' . $specialty_views_table . '"' ) != $specialty_views_table ) {

		$sql = "CREATE TABLE " . $specialty_views_table . " (    
                `user_id` bigint(20) UNSIGNED NOT NULL,
                `specialty_id` bigint(20) UNSIGNED NOT NULL,
                `date_open` datetime NOT NULL,
                `date_expiry` datetime NOT NULL,
                PRIMARY KEY (`user_id`, `specialty_id`),
                CONSTRAINT FK2_UserID FOREIGN KEY (user_id)
                REFERENCES {$wpdb->prefix}users(ID)
                ON DELETE CASCADE,
                CONSTRAINT FK2_SpecialtyID FOREIGN KEY (specialty_id)
                REFERENCES {$wpdb->prefix}terms(term_id)
                ON DELETE CASCADE
                )" . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}

register_activation_hook( MEDVISESUB_FILE, __NAMESPACE__ . '\install' );