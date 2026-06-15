<?php
defined( 'ABSPATH' ) || exit;

// Универсальный путь для Bedrock и стандартного WordPress
if (file_exists(WP_PLUGIN_DIR . '/contact-form-7/includes/integration.php')) {
    require_once WP_PLUGIN_DIR . '/contact-form-7/includes/integration.php';
} elseif (file_exists(ABSPATH . 'wp-content/plugins/contact-form-7/includes/integration.php')) {
    require_once ABSPATH . 'wp-content/plugins/contact-form-7/includes/integration.php';
} else {
    // Попытка найти файл через WordPress функции
    $cf7_path = plugin_dir_path(dirname(plugin_dir_path(__FILE__))) . 'contact-form-7/includes/integration.php';
    if (file_exists($cf7_path)) {
        require_once $cf7_path;
    }
}

if ( ! class_exists( 'WPCF7_Service' ) ) {
	return;
}

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CFYC_Service' ) ) {
	class CFYC_Service extends WPCF7_Service {

		private static CFYC_Service $instance;
		private $sitekeys;

		public static function get_instance(): CFYC_Service {
			if ( empty( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		private function __construct() {
			$this->sitekeys = WPCF7::get_option( 'cfyc' );
		}

		public function get_title(): string {
			return esc_html( __( 'Yandex Captcha', 'captcha-by-yandex-for-contact-form-7' ) );
		}

		public function is_active(): bool {
			$sitekey = $this->get_sitekey();
			$secret  = $this->get_secret( $sitekey );

			return $sitekey && $secret;
		}

		public function get_categories() {
			return array( 'spam_protection' );
		}

		public function icon() {
		}

		public function link() {
			echo wp_kses( wpcf7_link(
				'https://console.cloud.yandex.ru/',
				'console.cloud.yandex.ru'
			), array( 'a' => array( 'href' => true ) ) );
		}

		public function get_global_sitekey() {
			static $sitekey = '';

			if ( $sitekey ) {
				return $sitekey;
			}

			if ( defined( 'CFYC_SITEKEY' ) ) {
				$sitekey = stripslashes( sanitize_text_field( CFYC_SITEKEY ) );
			}

			$sitekey = apply_filters( 'cfyc_sitekey', $sitekey );

			return $sitekey;
		}

		public function get_global_secret() {
			static $secret = '';

			if ( $secret ) {
				return $secret;
			}

			if ( defined( 'CFYC_SECRET' ) ) {
				$secret = stripslashes( sanitize_text_field( CFYC_SECRET ) );
			}

			$secret = apply_filters( 'cfyc_secret', $secret );

			return $secret;
		}

		public function get_sitekey() {
			if ( $this->get_global_sitekey() and $this->get_global_secret() ) {
				return $this->get_global_sitekey();
			}

			if ( empty( $this->sitekeys ) or ! is_array( $this->sitekeys ) ) {
				return false;
			}

			$sitekeys = array_keys( $this->sitekeys );

			return $sitekeys[0];
		}

		public function get_secret( $sitekey ) {
			if ( $this->get_global_sitekey() and $this->get_global_secret() ) {
				return $this->get_global_secret();
			}

			$sitekeys = (array) $this->sitekeys;

			if ( isset( $sitekeys[ $sitekey ] ) ) {
				return $sitekeys[ $sitekey ];
			} else {
				return false;
			}
		}

		protected function menu_page_url( $args = '' ): string {
			$args = wp_parse_args( $args, array() );

			$url = menu_page_url( 'wpcf7-integration', false );
			$url = add_query_arg( array( 'service' => 'cfyc' ), $url );

			if ( ! empty( $args ) ) {
				$url = add_query_arg( $args, $url );
			}

			return $url;
		}

		protected function save_data(): void {
			WPCF7::update_option( 'cfyc', $this->sitekeys );
		}

		protected function reset_data(): void {
			$this->sitekeys = null;
			$this->save_data();
		}

		public function load( $action = '' ): void {
			if ( 'setup' == $action and 'POST' == $_SERVER['REQUEST_METHOD'] ) {
				check_admin_referer( 'wpcf7-cfyc-setup' );

				$isReset = stripslashes( sanitize_text_field( $_POST['reset'] ?? '' ) );
				if ( ! empty( $isReset ) ) {
					$this->reset_data();
					$redirect_to = $this->menu_page_url( 'action=setup' );
				} else {
					$sitekey = stripslashes( sanitize_text_field( $_POST['sitekey'] ?? '' ) );
					$secret  = stripslashes( sanitize_text_field( $_POST['secret'] ?? '' ) );

					$isCorrect = $this->verifySiteKey( $sitekey );

					if ( $sitekey and $secret and $isCorrect ) {
						$this->sitekeys = array( $sitekey => $secret );
						$this->save_data();

						$redirect_to = $this->menu_page_url( array(
							'message' => 'success',
						) );
					} else {
						$redirect_to = $this->menu_page_url( array(
							'action'  => 'setup',
							'message' => 'invalid',
						) );
					}
				}

				wp_safe_redirect( $redirect_to );
				exit();
			}
		}

		public function admin_notice( $message = '' ): void {
			if ( 'invalid' == $message ) {
				echo sprintf(
					'<div class="notice notice-error"><p><strong>%1$s</strong>: %2$s</p></div>',
					esc_html( __( "Error", 'contact-form-7' ) ),
					esc_html( __( "Invalid keys", 'captcha-by-yandex-for-contact-form-7' ) ) );
			}

			if ( 'success' == $message ) {
				echo sprintf( '<div class="notice notice-success"><p>%s</p></div>',
					esc_html( __( 'Settings saved.', 'contact-form-7' ) ) );
			}
		}

		public function display( $action = '' ): void {
			echo sprintf(
				'<p>%s</p>',
				esc_html( __( "Yandex Captcha protects you against spam and other types of automated abuse. With Contact Form 7&#8217;s Yandex Captcha integration module, you can block abusive form submissions by spam bots.", 'captcha-by-yandex-for-contact-form-7' ) )
			);

			if ( $this->is_active() ) {
				echo sprintf(
					'<p class="dashicons-before dashicons-yes">%s</p>',
					esc_html( __( "Yandex Captcha is active on this site.", 'captcha-by-yandex-for-contact-form-7' ) )
				);
			}

			if ( 'setup' == $action ) {
				$this->display_setup();
			} else {
				echo sprintf(
					'<p><a href="%1$s" class="button">%2$s</a></p>',
					esc_url( $this->menu_page_url( 'action=setup' ) ),
					esc_html( __( 'Setup Integration', 'contact-form-7' ) )
				);
			}
		}

		private function display_setup(): void {
			$sitekey = $this->is_active() ? $this->get_sitekey() : '';
			$secret  = $this->is_active() ? $this->get_secret( $sitekey ) : '';

			?>
            <form method="post" action="<?php echo esc_url( $this->menu_page_url( 'action=setup' ) ); ?>">
				<?php wp_nonce_field( 'wpcf7-cfyc-setup' ); ?>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sitekey"><?php echo esc_html( __( 'Client key', 'captcha-by-yandex-for-contact-form-7' ) ); ?></label></th>
                        <td><?php
							if ( $this->is_active() ) {
								echo esc_html( $sitekey );
								echo sprintf(
									'<input type="hidden" value="%1$s" id="sitekey" name="sitekey" />',
									esc_attr( $sitekey )
								);
							} else {
								echo sprintf(
									'<input type="text" aria-required="true" value="%1$s" id="sitekey" name="sitekey" class="regular-text code" />',
									esc_attr( $sitekey )
								);
							}
							?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="secret"><?php echo esc_html( __( 'Server Key', 'captcha-by-yandex-for-contact-form-7' ) ); ?></label></th>
                        <td><?php
							if ( $this->is_active() ) {
								echo esc_html( wpcf7_mask_password( $secret, 4, 4 ) );
								echo sprintf(
									'<input type="hidden" value="%1$s" id="secret" name="secret" />',
									esc_attr( $secret )
								);
							} else {
								echo sprintf(
									'<input type="text" aria-required="true" value="%1$s" id="secret" name="secret" class="regular-text code" />',
									esc_attr( $secret )
								);
							}
							?></td>
                    </tr>
                    </tbody>
                </table>
				<?php
				if ( $this->is_active() ) {
					if ( $this->get_global_sitekey() and $this->get_global_secret() ) {
						// nothing
					} else {
						submit_button(
							_x( 'Remove Keys', 'API keys', 'contact-form-7' ),
							'small', 'reset'
						);
					}
				} else {
					submit_button( __( 'Save Changes', 'contact-form-7' ) );
				}
				?>
            </form>
			<?php
		}

		public static function get_user_ip_address(): ?string {
			foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
				if ( array_key_exists( $key, $_SERVER ) === true ) {
					$key = sanitize_text_field( $_SERVER[ $key ] );
					foreach ( explode( ',', $key ) as $ip ) {
						$ip = trim( $ip );

						if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
							return $ip;
						}
					}
				}
			}

			return null;
		}

		public function verify( string $token ): bool {
			$ip        = self::get_user_ip_address();
			$service   = CFYC_Service::get_instance();
			$siteKey   = $service->get_sitekey();
			$serverKey = $service->get_secret( $siteKey );
			$response  = wp_remote_get( 'https://smartcaptcha.yandexcloud.net/validate?secret=' . $serverKey . '&ip=' . $ip . '&token=' . $token );

			// проверка ошибки
			if ( is_wp_error( $response ) ) {
				return false;
			} else {
				if ( ! isset( $response['response']['code'] ) or $response['response']['code'] != 200 ) {
					return false;
				}
				try {
					$response = json_decode( $response['body'], true );

					return ! empty( $response['status'] ) && $response['status'] == 'ok';
				} catch ( \Exception $e ) {
					return false;
				}
			}
		}

		private function verifySiteKey( string $sitekey ): bool {
			$urlparts = wp_parse_url( site_url() );
			$domain   = $urlparts['host'];
			$response = wp_remote_post( 'https://smartcaptcha.yandexcloud.net/check?host=' . $domain . '&sitekey=' . $sitekey );

			// проверка ошибки
			if ( is_wp_error( $response ) ) {
				return false;
			} else {
				if ( $response['response']['code'] != 200 ) {
					return false;
				}
				try {
					$response = json_decode( $response['body'], true );

					return ! empty( $response['status'] );
				} catch ( \Exception $e ) {
					return false;
				}
			}
		}
	}
}
