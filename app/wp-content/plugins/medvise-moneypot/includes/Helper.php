<?php

namespace MedviseMoneyPot;

class Helper {

	public static function periods_pretty_print( $periods ) {
		$periods = array_reverse( $periods, true );

		$html = '<table class="widefat fixed striped">';

		foreach ( $periods as $period => $value ) {

			if ( $period === array_key_last( $periods ) ) {
				$html .= "<tr><td>до {$period}</td> <td><strong>" . $value . "%</strong></td></tr>";
				continue;
			}

			if ( $period === array_key_first( $periods ) ) {

				// Есть ли период далее
				if ( false !== next( $periods ) ) {
					$next_period = key( $periods );
					$html .= "<tr><td>{$next_period} - н.в.</td> <td><strong>" . $value . "%</strong></td></tr>";
				} else {
					$html .= "н.в. " . $value . "%";
				}

				continue;
			}

			next( $periods );
			$next_period = key( $periods );

			$html .= "<tr><td>{$next_period} - {$period}</td> <td><strong>" . $value . "%</strong></td></tr>";
		}

		$html .= '</table>';

		return $html;
	}

	public static function can_see_moneypot( $user_id ) {

		if ( user_can( $user_id, 'administrator' ) ) {
			return true;
		}

		// Проверяем наличие отметки в профиле
		$user_specialties = carbon_get_user_meta( $user_id, 'medvise_moneypot_specialties' );

		if ( ! empty($user_specialties) ) {
			return true;
		}

		return false;
	}

}