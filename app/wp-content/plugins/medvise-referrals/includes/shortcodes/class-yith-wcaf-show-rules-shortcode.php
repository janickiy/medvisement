<?php
/**
 * Affiliate Dashboard shortcode - Rules
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH/Affiliates/Classes
 * @version 2.0.0
 */

if ( ! defined( 'YITH_WCAF' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'YITH_WCAF_Show_Rules_Shortcode' ) ) {
	/**
	 * Offer methods for basic shortcode handling
	 *
	 * @since 2.0.0
	 */
	class YITH_WCAF_Show_Rules_Shortcode extends YITH_WCAF_Abstract_Shortcode {

		/**
		 * Current dashboard section
		 *
		 * It matches the endpoint if any; default section is summary
		 * This base class manages summary only; child classes will handle other sections
		 *
		 * @var string
		 */
		protected $section;

		/* === INIT === */

		/**
		 * Performs any required init operation
		 */
		public function init() {
			// configure shortcode basics.
			$this->tag         = 'yith_wcaf_show_rules';
			$this->title       = "Правила";
			$this->section     = 'rules';
			$this->template    = "dashboard-{$this->section}.php";
			$this->description = "Правила партнерскойп рограммы";
			$this->supports    = array();
		}

		/* === SECTION HANDLING === */

		public function render_section( $atts = array(), $content = '' ) {
			return wpautop( carbon_get_theme_option('wcaf_rules') );
		}

	}
}
