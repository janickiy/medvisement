<?php


namespace MedvisementUserTour\Tours;

use MedvisementUserTour\Interfaces\Tour;

class FastSearch implements Tour {

	public const name = 'Быстрый поиск';

	public const priority = 10;

	// Определяет видимость теста на странице
	public function isVisible() {

		if ( ! is_page() ) {
			return FALSE;
		}

		global $post;

		if ( $post->post_name === 'quick-search' ) {
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
		return home_url( '/quick-search/' );
	}

}