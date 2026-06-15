<?php namespace MeowCrew\SubscriptionsDiscounts\Integrations\Themes;

class OceanWp {

	public function __construct() {
		add_action('wp_head', function () {
			?>
			<style>
				.price-rule-active .amount{
					color: #fff;
				}
				.discounts-for-subscriptions-table  tr {
					background: #fff;
				}
				.price-table-tooltip-icon {
					vertical-align: text-top;
				}
			</style>
			<?php
		});
	}
}
