<?php
/**
 * Rate rule Factory class
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH/Affiliates/Classes
 * @version 2.0.0
 */

if ( ! defined( 'YITH_WCAF' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'YITH_WCAF_Rate_Rule_Factory' ) ) {
	/**
	 * Static class that offers methods to construct YITH_WCAF_Rate_Rule objects
	 *
	 * @since 2.0.0
	 */
	class YITH_WCAF_Rate_Rule_Factory {

		/**
		 * Returns a list of rules matching filtering criteria
		 *
		 * @param array $args Filtering criteria (@see \YITH_WCAF_Rate_Rule_Data_Store::query).
		 *
		 * @return YITH_WCAF_Rate_Rules_Collection|string[]|bool Result set; false on failure.
		 */
		public static function get_rules( $args = array() ) {
			try {
				$data_store = WC_Data_Store::load( 'rate_rule' );

				$res = $data_store->query( $args );
			} catch ( Exception $e ) {
				return false;
			}

			return $res;
		}

		/**
		 * Returns a rule, given the id
		 *
		 * @param int $id Rule's ID.
		 *
		 * @return YITH_WCAF_Rate_Rule|bool Rule object, or false on failure
		 */
		public static function get_rule( $id ) {
			if ( ! $id ) {
				return false;
			}

			try {
				return new YITH_WCAF_Rate_Rule( $id );
			} catch ( Exception $e ) {
				return false;
			}
		}
	}
}
