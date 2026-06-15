<?php
/**
 * Affiliate Factory class
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH/Affiliates/Classes
 * @version 2.0.0
 */

if ( ! defined( 'YITH_WCAF' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'YITH_WCAF_Affiliate_Factory' ) ) {
	/**
	 * Static class that offers methods to construct YITH_WCAF_Affiliate objects
	 *
	 * @since 2.0.0
	 */
	class YITH_WCAF_Affiliate_Factory {

		/**
		 * Returns a list of affiliates matching filtering criteria
		 *
		 * @param array $args Filtering criteria (@see \YITH_WCAF_Affiliate_Data_Store::query).
		 *
		 * @return YITH_WCAF_Affiliates_Collection|string[]|bool Result set; false on failure.
		 */
		public static function get_affiliates( $args = array() ) {
			try {
				$data_store = WC_Data_Store::load( 'affiliate' );

				$res = $data_store->query( $args );
			} catch ( Exception $e ) {
				return false;
			}

			return $res;
		}

		/**
		 * Returns an affiliate, given the token or id
		 * If no token is passed, retrieve current affiliate
		 *
		 * @param int|string $token Affiliate's ID or token.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure
		 */
		public static function get_affiliate( $token = null ) {
			if ( $token ) {
				return self::get_affiliate_by_token( $token );
			}

			return self::get_current_affiliate();
		}

		/**
		 * Returns object for current affiliate, if any; false otherwise
		 *
		 * @param string $context Context for the operation. Changes value returned: 'logged' returns current user affiliate, 'session' current session affiliate.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure
		 */
		public static function get_current_affiliate( $context = 'logged' ) {
			if ( 'session' === $context ) {
				return YITH_WCAF_Session()->get_affiliate();
			}

			if ( ! is_user_logged_in() ) {
				return false;
			}

			return self::get_affiliate_by_user_id( get_current_user_id() );
		}

		/**
		 * Retrieves an affiliate by token; false on failure
		 *
		 * @param string $token Token to search.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure
		 */
		public static function get_affiliate_by_token( $token ) {
			if ( ! $token ) {
				return false;
			}

			try {
				return new YITH_WCAF_Affiliate( $token );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Retrieves an affiliate by id; false on failure
		 *
		 * @param int $id ID to search.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure
		 */
		public static function get_affiliate_by_id( $id ) {
			if ( ! $id ) {
				return false;
			}

			try {
				return new YITH_WCAF_Affiliate( (int) $id );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Retrieves an affiliate by order id; false on failure
		 *
		 * @param int|WC_Order $order Order object or order id.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure
		 */
		public static function get_affiliate_by_order_id( $order ) {
			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order );
			}

			if ( ! $order ) {
				return false;
			}

			$token = $order->get_meta( '_yith_wcaf_referral' );

			if ( ! $token ) {
				return false;
			}

			return self::get_affiliate( $token );
		}

		/**
		 * Retrieves an affiliate by user id; false on failure
		 *
		 * @param int $user_id User id to search.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure
		 */
		public static function get_affiliate_by_user_id( $user_id ) {
			try {
				$data_store = WC_Data_Store::load( 'affiliate' );

				$token = $data_store->get_token_by_user_id( $user_id );

				if ( ! $token ) {
					return false;
				}

				return self::get_affiliate_by_token( $token );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Make any user an affiliates, optionally setting meta fields in the process
		 *
		 * @param int   $user_id Id of the user to make affiliate.
		 * @param array $fields Array of meta to save for the new affiliate.
		 *
		 * @return YITH_WCAF_Affiliate|bool Affiliate object, or false on failure.
		 */
		public static function make_user_an_affiliate( $user_id, $fields = array() ) {
			$affiliate = self::get_affiliate_by_user_id( $user_id );

			if ( ! $affiliate ) {
				$new_application = true;
				$affiliate       = new YITH_WCAF_Affiliate();

				$affiliate->set_user_id( $user_id );
				$affiliate->update_meta_data( 'application_date', current_time( 'mysql' ) );

				if ( yith_plugin_fw_is_true( get_option( 'yith_wcaf_referral_registration_auto_enable' ) ) ) {
					$affiliate->set_status( 'enabled' );
				}
			}

			// update affiliate fields.
			foreach ( $fields as $key => $value ) {
				$affiliate->update_meta_data( $key, $value );
			}

			$id = $affiliate->save();

			/**
			 * DO_ACTION: yith_wcaf_new_affiliate_application
			 *
			 * Allows to trigger some action when a customer sends the application to become an affiliate.
			 *
			 * @param int                 $id        Affiliate id.
			 * @param YITH_WCAF_Affiliate $affiliate Affiliate object.
			 */
			( ! empty( $new_application ) ) && do_action( 'yith_wcaf_new_affiliate_application', $id, $affiliate );

			return $affiliate;
		}
	}
}
