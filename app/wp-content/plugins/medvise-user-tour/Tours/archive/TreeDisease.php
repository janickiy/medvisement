<?php


namespace MedvisementUserTour\Tours\archive;

use MedvisementUserTour\Interfaces\Tour;

class TreeDisease implements Tour {

	public const name = 'Древо заболеваний';

	public const priority = 10;

	// Определяет видимость теста на странице
	public function isVisible() {

		if ( ! is_page() ) {
			return FALSE;
		}

		global $template;

		if ( basename($template) === 'specialty.php' ) {
			return TRUE;
		}

		return FALSE;
	}

	public function getJS() {
		return file_get_contents( MEDVISEUSERTOUR_PLUGIN_DIR . "Tours" . DIRECTORY_SEPARATOR . class_basename(__CLASS__) . ".js" );
	}

	public function getStartPage() {
		return home_url( '/specialty/' );
	}

}