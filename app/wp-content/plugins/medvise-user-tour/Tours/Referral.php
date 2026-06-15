<?php

namespace MedvisementUserTour\Tours;

use MedvisementUserTour\Interfaces\Tour;

class Referral implements Tour {

	public const name = 'Партнерская программа';

	public const priority = 10;

	public function isVisible() {
		return is_front_page() && \YITH_WCAF_Affiliate_Factory::get_current_affiliate();
	}

	public function hasAccess() {
		return (bool) \YITH_WCAF_Affiliate_Factory::get_current_affiliate();
	}

	public function getJS() {
		return file_get_contents( MEDVISEUSERTOUR_PLUGIN_DIR . "Tours" . DIRECTORY_SEPARATOR . class_basename(__CLASS__) . ".js" );
	}

	public function getStartPage() {
		return get_home_url();
	}
}