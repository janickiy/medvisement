<?php
/**
 * The core plugin class.
 *
 * It is used to define startup settings and requirements
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CFYC_Common' ) ) {

	class CFYC_Common {
		/**
		 * @var string Plugin common system name
		 */
		const PLUGIN_SYSTEM_NAME = 'captcha-by-yandex-for-contact-form-7';

		/**
		 * @var string Short code name
		 */
		const TAG_NAME = 'yandex_captcha';

		/**
		 * @var string Path to plugin root directory
		 */
		public string $plugin_base_path = '';

		public function __construct() {
			$this->plugin_base_path = self::get_plugin_root_path();

			$this->load_dependencies();
			$this->set_locale();
		}

		/**
		 * Plugin entry point
		 */
		public function run(): void {
			$this->define_common_hooks();
			$this->define_admin_hooks();
		}

		/**
		 * Add actions and work for common part of the plugin
		 */
		private function define_common_hooks(): void {
			$front = CFYC_Frontend::get_instance();
			add_action( 'wpcf7_init', array( $front, 'cfyc_add_shortcode' ) ); // handle shortcode in frontend
			add_filter( 'wpcf7_spam', array( $front, 'cfyc_validate_captcha' ), 9, 2 ); // validate captcha on form submit
			add_filter( 'wpcf7_validate_' . self::TAG_NAME, array( $front, 'cfyc_validate_fills' ), 99, 2 ); // check is captcha filled to produce error
		}

		/**
		 * Add actions and work for admin part of the plugin
		 */
		private function define_admin_hooks(): void {
			if ( is_admin() ) {
				add_action( 'wpcf7_init', array( $this, 'cfyc_register_service' ), 9, 0 ); // add block under integration page
				add_filter( "plugin_action_links_" . plugin_basename( dirname( __DIR__ ) . '/bootstrap.php' ), array( $this, 'plugin_action_links' ), 10, 4 ); // Add links under plugin name in a plugin list

				$tag = CFYC_Form_Tag::get_instance();
				add_action( 'wpcf7_admin_init', array( $tag, 'cfyc_add_form_template_tag' ), 69 ); // Add a tag in a form template
			}
		}

		/**
		 * Use for register module options
		 */
		public static function activate(): void {

		}

		/**
		 * Do all jobs when module deactivated
		 */
		public static function deactivate(): void {
		}

		/**
		 * Get a path or uri for plugin-based folder
		 *
		 * @param string $type switch a path or url for a result
		 *
		 * @return string
		 */
		public static function get_plugin_root_path( string $type = 'path' ): string {
			if ( 'url' == $type ) {
				return plugin_dir_url( dirname( __FILE__ ) );
			}

			return plugin_dir_path( dirname( __FILE__ ) );
		}

		/**
		 * Load plugin files
		 */
		private function load_dependencies(): void {
			require_once $this->plugin_base_path . 'controller/class-cfyc-service.php';
			require_once $this->plugin_base_path . 'controller/class-cfyc-form-tag.php';
			require_once $this->plugin_base_path . 'controller/class-cfyc-frontend.php';
		}

		/**
		 * Add localization support
		 */
		private function set_locale(): void {
			load_plugin_textdomain(
				self::PLUGIN_SYSTEM_NAME,
				false,
				self::PLUGIN_SYSTEM_NAME . '/languages/'
			);
		}

		/**
		 * Registers the Yandex Captcha service.
		 */
		public function cfyc_register_service(): void {
			$integration = WPCF7_Integration::get_instance();
			$integration->add_service( 'cfyc', CFYC_Service::get_instance() );
		}

		/**
		 * Add link to plugin settings page im plugins list
		 *
		 * @param $actions
		 *
		 * @return mixed
		 */
		public static function plugin_action_links( $actions ) {
			$url = menu_page_url( 'wpcf7-integration', false );
			$url = add_query_arg( array(
				'service' => 'cfyc',
				'action'  => 'setup'
			), $url );
			array_unshift( $actions,
				sprintf( '<a href="%s" aria-label="%s">%s</a>',
					$url,
					esc_html__( 'Configure', 'captcha-by-yandex-for-contact-form-7' ),
					esc_html__( 'Configure', 'captcha-by-yandex-for-contact-form-7' )
				)
			);

			return $actions;
		}
	}
}
