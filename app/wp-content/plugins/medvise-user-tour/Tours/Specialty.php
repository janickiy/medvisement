<?php


namespace MedvisementUserTour\Tours;

use MedvisementUserTour\Interfaces\Tour;

class Specialty implements Tour {

	public const name = 'Специальность';

	public const priority = 10;

	// Определяет видимость теста на странице
	public function isVisible() {

		if ( is_tax( 'specialty', 7 ) ) {
			return TRUE;
		}

		return FALSE;
	}

	public function hasAccess() {
		return true;
	}

	public function getJS() {
		return file_get_contents( MEDVISEUSERTOUR_PLUGIN_DIR . "Tours" . DIRECTORY_SEPARATOR . class_basename(__CLASS__) . ".js" );
	}

	public function getStartPage() {
		return home_url( '/specialty/nevrologiya/' );
	}

}