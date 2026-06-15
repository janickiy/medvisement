<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Themes;

class Shopkeeper {

	public function __construct() {
		add_action( 'wp_head', function () {
			?>

			<style>
				.discounts-for-subscriptions-table {
					border-collapse: collapse !important;
					padding-left: 10px;
				}

				.discounts-for-subscriptions-table tbody td {
					padding: 10px !important;
				}

				.discounts-for-subscriptions-table th {
					padding-left: 10px !important;
				}
			</style>
			<?php
		} );
	}
}
