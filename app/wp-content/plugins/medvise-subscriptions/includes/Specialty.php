<?php

namespace MedviseSubscriptions\Specialty;

class Specialty {
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_specialty_page' ] );
		add_action( 'admin_init', [ $this, 'settings_init' ] );
	}

	public function register_specialty_page() {
		add_submenu_page( 'woocommerce', 'Учетные специальности', 'Учетные специальности', 'manage_options', 'med-specialties-page', [
			$this,
			'specialty_page_html'
		] );
	}

	public function settings_init() {
		add_settings_section(
			'med_specialties_section',
			'',
			'',
			'med-specialties-page'
		);

		add_settings_field(
			'med_specialties',
			'Учетные специальности',
			[ $this, 'specialties_field_html' ],
			'med-specialties-page',
			'med_specialties_section'
		);

		register_setting( 'med_specialties', 'med_specialties' );
	}

	function specialty_page_html() {
		include( MEDVISESUB_PATH . 'tpl/page-specialty.php' );
	}

	function specialties_field_html() {
		$allowedSpecialtiesIds = self::get_allowed_specialties();
		$specialtyTerms        = get_terms( [
			'taxonomy'   => 'specialty',
			'hide_empty' => false,
		] );

		include( MEDVISESUB_PATH . 'tpl/specialties-field.php' );
	}


	//Helpers
	public static function get_allowed_specialties() {
		$allowedSpecialties         = (array) get_option( 'med_specialties' );
		$existingAllowedSpecialties = get_terms( [
			'taxonomy'   => 'specialty',
			'include'    => $allowedSpecialties,
			'hide_empty' => false,
			'fields'     => 'ids'
		] );

		return $existingAllowedSpecialties;
	}
}