<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Themes;

class Porto {

	public function __construct() {

		add_action( 'wp_head', function () {
			?>
			<script>
				jQuery(document).ready(function ($) {
					if (document.subscriptionsDiscounts) {
						setTimeout(function () {
							document.subscriptionsDiscounts.init();
						}, 1000)
					}
				});
			</script>
			<?php
		} );
	}
}
