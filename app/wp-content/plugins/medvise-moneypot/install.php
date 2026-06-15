<?php /** @noinspection SqlNoDataSourceInspection */

namespace MedviseMoneyPot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function install() {
	global $wpdb;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$charset_collate = '';
	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate = " DEFAULT CHARACTER SET $wpdb->charset";
	}
	if ( ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE $wpdb->collate";
	}

	$transactions_table = $wpdb->prefix . 'medvise_transactions';

	if ( $wpdb->get_var( 'show tables like "' . $transactions_table . '"' ) != $transactions_table ) {

		$sql = "CREATE TABLE " . $transactions_table . " (    
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `source` varchar(20) NOT NULL,
                `source_id` bigint(20) UNSIGNED NOT NULL,
                `target` varchar(20) NOT NULL,
                `target_id` bigint(20) NOT NULL,
                `amount` mediumint NOT NULL,
                `created_at` date NOT NULL,
                `note` text NULL DEFAULT NULL,
                PRIMARY KEY (id)
                )" . $charset_collate . ";";

		dbDelta( $sql );
	}


}