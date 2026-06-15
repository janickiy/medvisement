<?php namespace MeowCrew\SubscriptionsDiscounts;

class SanitizeManager {
	public static function sanitizeDiscountsRules( $rules ) {
		return array_filter( $rules, function ( $el ) {
			return ! empty( $el );
		}, ARRAY_FILTER_USE_KEY );
	}
}
