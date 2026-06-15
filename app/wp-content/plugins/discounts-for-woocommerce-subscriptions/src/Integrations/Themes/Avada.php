<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Themes;

class Avada {

	public function __construct() {
		add_action( 'wp_head', function () {
			?>
			<style>
				.discounts-for-subscriptions-table tbody tr {
					height: inherit;
				}

				.discounts-for-subscriptions-table tbody td {
					padding: 15px 0 15px 10px;
				}

				.discounts-for-subscriptions-table th {
					padding-left: 10px;
				}
			</style>
			<?php
		} );
	}
}
