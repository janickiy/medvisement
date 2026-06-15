<?php
/**
 * YITH Dashboard Class
 * handle WordPress Admin Dashboard
 *
 * @class   YITH_Dashboard
 * @package YITH\PluginFramework\Classes
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( 'YITH_Dashboard' ) ) {
	/**
	 * YITH_Dashboard class.
	 */
	class YITH_Dashboard {
		
		/**
		 * Enqueue Styles and Scripts for View Last Changelog widget
		 */
		public static function enqueue_scripts() {
			if ( function_exists( 'get_current_screen' ) && get_current_screen() && 'dashboard' === get_current_screen()->id ) {
				$script_path = defined( 'YIT_CORE_PLUGIN_URL' ) ? YIT_CORE_PLUGIN_URL : get_template_directory_uri() . '/core/plugin-fw';
				$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
				wp_enqueue_script( 'yith-dashboard', $script_path . '/assets/js/yith-dashboard' . $suffix . '.js', array( 'jquery-ui-dialog' ), yith_plugin_fw_get_version(), true );
				wp_enqueue_style( 'wp-jquery-ui-dialog' );
				$l10n = array(
					'buttons' => array(
						'close' => _x( 'Close', 'Button label', 'yith-plugin-fw' ),
					),
				);
				wp_localize_script( 'yith-dashboard', 'yith_dashboard', $l10n );
			}
		}
	}

	if ( apply_filters( 'yith_plugin_fw_show_dashboard_widgets', true ) ) {
		add_action( 'admin_enqueue_scripts', 'YITH_Dashboard::enqueue_scripts', 20 );
	}
}
