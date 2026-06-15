<?php
/**
 * Plugin Name: Captcha by Yandex for Contact Form 7
 * Description: Allow use Yandex captcha for your forms with Contact Form 7
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: contact-form-7
 * Author URI:      https://www.linkedin.com/in/stasionok/
 * Author Telegram: https://t.me/stasionok
 * Author:          Stanislav Kuznetsov
 * Version:     1.2.5
 * License: GPLv3
 * Text Domain: captcha-by-yandex-for-contact-form-7
 * Domain Path: /languages
 *
 * Network: false
 */

defined( 'ABSPATH' ) || exit;

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

const CFYC_REQUIRED_PHP_VERSION = '7.4';
const CFYC_REQUIRED_WP_VERSION  = '5.0';

/**
 * Checks if the system requirements are met
 *
 * @return array Array of errors or false if all is ok
 */
function cfyc_requirements_met(): array {
	global $wp_version;

	$errors = [];

	load_plugin_textdomain( 'captcha-by-yandex-for-contact-form-7', false, 'captcha-by-yandex-for-contact-form-7/languages/' );

	if ( version_compare( PHP_VERSION, CFYC_REQUIRED_PHP_VERSION, '<' ) ) {
		$errors[] = printf( /* translators: %s: php version */
			esc_html__( 'Your server is running PHP version %1$s but this plugin requires at least PHP %2$s. Please run an upgrade.', 'captcha-by-yandex-for-contact-form-7' ),
			esc_attr(PHP_VERSION),
			esc_attr(CFYC_REQUIRED_PHP_VERSION)
		);
	}

	if ( version_compare( $wp_version, CFYC_REQUIRED_WP_VERSION, '<' ) ) {
		$errors[] = printf( /* translators: %s: WP version */
			esc_html__( 'Your Wordpress running version is %1$s but this plugin requires at least version %2$s. Please run an upgrade.', 'captcha-by-yandex-for-contact-form-7' ),
			esc_html( $wp_version ),
			esc_attr(CFYC_REQUIRED_WP_VERSION)
		);
	}

	// Проверка активности Contact Form 7 для Bedrock и стандартного WordPress
	$cf7_active = false;
	if (version_compare( $wp_version, '6.5', '<' )) {
		if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
			$cf7_active = true;
		}
		
		// Дополнительная проверка через существование класса WPCF7
		if (!$cf7_active && class_exists('WPCF7')) {
			$cf7_active = true;
		}
		
		// Проверка существования файла плагина
		if (!$cf7_active) {
			$cf7_file_exists = false;
			if (file_exists(WP_PLUGIN_DIR . '/contact-form-7/wp-contact-form-7.php')) {
				$cf7_file_exists = true;
			} elseif (file_exists(ABSPATH . 'wp-content/plugins/contact-form-7/wp-contact-form-7.php')) {
				$cf7_file_exists = true;
			}
			
			if (!$cf7_file_exists) {
				$errors[] = esc_html__( 'Please install and activate Contact Form 7 plugin first', 'captcha-by-yandex-for-contact-form-7' );
			}
		}
	}

	return $errors;
}

/**
 * Begins execution of the plugin.
 *
 * Plugin run entry point
 */
function cfyc_run(): void {
	$plugin = new CFYC_Common();
	$plugin->run();
}


/**
 * Check requirements and load main class
 * The main program needs to be in a separate file that only gets loaded if the plugin requirements are met.
 * Otherwise, older PHP installations could crash when trying to parse it.
 */
require_once( __DIR__ . '/controller/class-cfyc-common.php' );

$errors = cfyc_requirements_met();
if ( ! $errors ) {
	if ( method_exists( CFYC_Common::class, 'activate' ) ) {
		register_activation_hook( __FILE__, 'CFYC_Common::activate' );
	}

	cfyc_run();
} else {
	add_action( 'admin_notices', function () use ( $errors ) {
		require_once( dirname( __FILE__ ) . '/views/requirements-error.php' );
	} );
}

if ( method_exists( CFYC_Common::class, 'deactivate' ) ) {
	register_deactivation_hook( __FILE__, array( CFYC_Common::class, 'deactivate' ) );
}

if ( method_exists( CFYC_Common::class, 'uninstall' ) ) {
	register_uninstall_hook( __FILE__, array( CFYC_Common::class, 'uninstall' ) );
}

