<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Themes;

class Astra {

	public function __construct() {

		add_action( 'wp_head', function () {
			?>
			<style>
				.discounts-for-subscriptions-table tbody td {
					padding-left: 15px !important;
				}
			</style>
			<?php
		} );
	}
}
