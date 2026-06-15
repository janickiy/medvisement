<?php
namespace MedvisementPostRating;

class Helpers {

	public static function getTpl( $name, $args = [], $echo = TRUE ) {

		if ( ! $echo ) {
			ob_start();
		}

		$themeTemplateFound = get_template_part( $name, NULL, $args );

		if ( $themeTemplateFound === FALSE ) {
			extract( $args );
			include Base::getInstance()->pluginPath . '/templates/' . $name . '.php';
		}

		if ( ! $echo ) {
			return ob_get_clean();
		}

	}

}

