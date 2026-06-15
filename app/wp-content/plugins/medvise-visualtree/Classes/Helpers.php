<?php


namespace Medvisement\Classes;


class Helpers {

	public static function getTreeModelNamespace( $model ) {
		$model_name = "Medvisement\\Models\\";

		switch ( $model ) {
			case 'disease':
				$model_name .= 'Disease';
				break;
			case 'substance':
				$model_name .= 'Substance';
				break;
			case 'custom_quiz':
				$model_name .= 'CustomQuiz';
				break;
			default:
				$model_name = NULL;
				break;
		}

		return $model_name;
	}

	public static function walkTroughNodeArray( &$node_array, $callback ) {

		$node_array = call_user_func($callback, $node_array);

		if ( isset( $node_array['children'] ) ) {
			foreach ( $node_array['children'] as $k => $v ) {
				self::walkTroughNodeArray( $v, $callback );
				$node_array['children'][$k] = $v;
			}
		}

	}

}