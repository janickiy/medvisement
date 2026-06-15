<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CFYC_Frontend' ) ) {
	class CFYC_Frontend {

		private static CFYC_Frontend $instance;

		public static function get_instance(): CFYC_Frontend {
			if ( empty( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Add shortcode handler
		 *
		 * @return void
		 */
		public function cfyc_add_shortcode(): void {
			wpcf7_add_form_tag(
				array( CFYC_Common::TAG_NAME, CFYC_Common::TAG_NAME . '*' ),
				array( $this, 'cfyc_form_tag_handler' ),
				array(
					'name-attr'               => true,
					'do-not-store'            => true,
					'display-block'           => true,
					'zero-controls-container' => true,
					'not-for-mail'            => true,
					'singular'                => true,
				)
			);

			wp_enqueue_script(
				'cfyc-captcha',
				'https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=cfycOnloadFunction',
				array(),
				'1.0.0',
				array(
					'strategy' => 'defer',
				)
			);
			wp_add_inline_script( 'cfyc-captcha', "
				var cfycCaptchaReadyEvent = new CustomEvent('cfycCaptchaReadyEvent')
				var cfycCaptchaLoaded = false 
				function cfycOnloadFunction() {
					cfycCaptchaLoaded = true
					document.dispatchEvent(cfycCaptchaReadyEvent)
				}

				document.addEventListener('DOMContentLoaded', function(e) {
					document.addEventListener('cfycInitEvent', function(e) {
						const containers = document.querySelectorAll('.cfyc-captcha-container');
						containers.forEach(container => {
							const randName = container.id.replace(/-/g, '');
							if (typeof window['cfycLoad' + randName] === 'function') {
								window['cfycLoad' + randName]();
							}
						});
					});
				});
            ", 'before' );

			// Enqueue styles in header instead of inline <style> inside form markup
			wp_register_style( 'cfyc-captcha-style', false, array(), '1.0.0' );
			wp_add_inline_style( 'cfyc-captcha-style', '.smart-captcha.wpcf7-not-valid{height:102px;border:1px solid;padding-right:2px;border-radius:11px;}' );
			wp_enqueue_style( 'cfyc-captcha-style' );
		}

		/**
		 * Handle shortcode
		 *
		 * @param $tag
		 *
		 * @return string
		 */
		function cfyc_form_tag_handler( $tag ): string {
			$tag = new WPCF7_FormTag( $tag );

			$test           = $tag->has_option( 'test' ) ? 'true' : 'false';
			$invisible      = $tag->has_option( 'invisible' ) ? 'true' : 'false';
			$hideShield     = $tag->has_option( 'hideShield' ) ? 'true' : 'false';
			$shieldPosition = $tag->get_option( 'shieldPosition', '(top-left|center-left|bottom-left|top-right|center-right|bottom-right)', true );
			if ( ! $shieldPosition ) {
				$shieldPosition = 'center-right';
			}

			$service = CFYC_Service::get_instance();
			$key     = $service->get_sitekey();

			$rand = wp_rand( 1000, 9999 );

			$randName = str_replace( '-', '', $tag->name . $rand );

			// for visible
			$callback = '';
			$style    = ' style="min-height: 102px;"';
			$execute  = "
				form{$randName}.addEventListener('submit', function (event) {
					const tokenField = document.querySelector('#{$tag->name}-{$rand} input[name=smart-token]');
					if (tokenField?.value?.length === 0) { container{$randName}.classList.add(\"wpcf7-not-valid\"); }
				})
			";

			// for invisible
			if ( $invisible === 'true' ) {
				$execute = "
					submitBtn{$randName}.type='button'
					submitBtn{$randName}.addEventListener('click', function (event) {
						if (!flag{$randName}) {
		                    window.smartCaptcha.execute(widget{$randName});
		                    return false
		                } 
	
	                    wpcf7.submit(form{$randName}, submitBtn{$randName});
					})
				";

				$callback = "wpcf7.submit(form{$randName}, submitBtn{$randName});";

				$style = '';
			}

			$class = 'smart-captcha';
			if ( ! empty( $tag->get_class_option() ) ) {
				$class .= ' ' . esc_attr( $tag->get_class_option() );
			}

			$content = "
<div class=\"{$class} cfyc-captcha-container\" id=\"{$tag->name}-{$rand}\"{$style}></div>
<script>
	document.addEventListener('DOMContentLoaded', function(e) {
		if (typeof cfycCaptchaLoaded !== 'undefined' && cfycCaptchaLoaded) {
			cfycLoad{$randName}()
		} else {
			document.addEventListener('cfycCaptchaReadyEvent', cfycLoad{$randName})
		}
	})

	window.cfycLoad{$randName} = function() {
		if (window.smartCaptcha) {
			const container{$randName} = document.getElementById('{$tag->name}-{$rand}')
			if (window['cfycInitialized' + {$rand}]) {
				return
			}
			window['cfycInitialized' + {$rand}] = true
			
			const form{$randName} = container{$randName}.closest('form')
			let submitBtn{$randName} = form{$randName}.querySelector('input[type=\"submit\"]')
			if (!submitBtn{$randName}) {
				submitBtn{$randName} = form{$randName}.querySelector('button[type=\"submit\"]')
			}
			let flag{$randName} = false
			const widget{$randName} = window.smartCaptcha.render(container{$randName}, {
				sitekey: '{$key}',
				invisible: {$invisible},
				test: {$test},
				hideShield: {$hideShield},
				shieldPosition: '{$shieldPosition}',
				callback: (token) => {
					flag{$randName} = true
					container{$randName}.classList.remove(\"wpcf7-not-valid\")
					{$callback}
				}
			})

			{$execute}
		}
	}
</script>";

			return $content;
		}

		/**
		 * Check if captcha solved before submitting
		 *
		 * @param $result
		 * @param $tag
		 *
		 * @return mixed
		 */
		public function cfyc_validate_fills( $result, $tag ) {
			$submission = WPCF7_Submission::get_instance();
			$data       = $submission->get_posted_data();
			$token      = stripslashes( sanitize_text_field( $data['smart-token'] ?? '' ) );
			if ( empty( $token ) ) {
				$error = __( 'Please check captcha', 'captcha-by-yandex-for-contact-form-7' );
				$result->invalidate( $tag, $error );
			}

			return $result;
		}

		/**
		 * Verify captcha
		 *
		 * @param $result
		 * @param $tag
		 *
		 * @return mixed
		 */
		public function cfyc_validate_captcha( $result, $tag ): bool {
			$service = CFYC_Service::get_instance();
			if ( ! $service->is_active() ) {
				return false;
			}
			$submission = WPCF7_Submission::get_instance();
			$data       = $submission->get_posted_data();
			$token      = stripslashes( sanitize_text_field( $data['smart-token'] ?? '' ) );

			if ( $service->verify( $token ) ) { // Human
				$spam = false;
			} else { // Bot
				$spam = true;
			}

			return $spam;
		}
	}
}
