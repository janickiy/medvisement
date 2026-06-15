<?php


namespace MedvisementUserTour\Tours;

use MedvisementUserTour\Interfaces\Tour;
use MedviseSubscriptions\Subscriber\Subscriber;

class ArticleTemplate implements Tour {

	public const name = 'Шаблоны статей';

	public const priority = 10;

	// Определяет видимость теста на странице
	public function isVisible() {

		if ( ! is_single() ) {
			return false;
		}

		global $post;

		if ( ! Subscriber::hasAccess($post) ) {
			return false;
		}

		if ( in_array( $post->post_type, [ 'disease', 'substance' ] ) ) {
			return true;
		}

		return false;
	}

	public function hasAccess() {
		return true;
	}

	public function getJS() {
		return file_get_contents( MEDVISEUSERTOUR_PLUGIN_DIR . "Tours" . DIRECTORY_SEPARATOR . class_basename(__CLASS__) . ".js" );
	}

	public function getStartPage() {
		return home_url( '/substance/epinefrin/' );
	}

}