<?php

namespace MedviseMoneyPot;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class CarbonSettings {

	public function setup() {

		add_action( 'carbon_fields_register_fields', [ $this, 'user_fields' ] );
		add_action( 'carbon_fields_register_fields', [ $this, 'term_fields' ] );
		add_action( 'carbon_fields_register_fields', [ $this, 'theme_fields' ] );

		add_filter( 'carbon_fields_should_delete_field_value_on_save', [ $this, 'write_outside_comission_history' ], 10, 2 );
	}

	public function user_fields() {

		$user_fields = [
			Field::make( 'set', 'medvise_moneypot_specialties', __( 'Видимые специальности' ) )
			     ->set_options( function () {

					 //todo где-то намудрили с get_terms() и не получает все элементы
				     $specialty_terms = new \WP_Term_Query( [
					     'suppress_filter' => false,
					     'taxonomy'   => 'specialty',
					     'hide_empty' => false,
				     ] );

				     return wp_list_pluck( $specialty_terms->terms, 'name', 'term_id' );
			     } )
		];

		Container::make( 'user_meta', 'Котел' )
		         ->where( 'user_capability', 'CUSTOM', function ( $user_id ) {

			         //Новый пользователь
			         if ( $user_id == 0 ) {
				         return false;
			         }

			         $user_id = empty( $user_id ) ? $_POST['user_id'] : $user_id;

			         $user = get_user_by( 'ID', $user_id );

			         return ( in_array( 'author', $user->roles ) || in_array( 'editor', $user->roles ) );
		         } )
		         ->add_fields( $user_fields );

	}

	public function term_fields() {

		$term_fields = [
			Field::make( 'text', 'med_moneypot_percent', 'Отчисления в котел %' )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'max', '100' )
			     ->set_attribute( 'min', '0' )
			     ->set_attribute( 'step', '0.01' )
			     ->set_default_value( 0 )
		];

		Container::make( 'term_meta', 'Котел' )
		         ->show_on_taxonomy( 'specialty' )
		         ->where( 'current_user_role', 'IN', array( 'administrator' ) )
		         ->add_fields( $term_fields );
	}

	public function theme_fields() {

		$current_value             = get_option( '_med_moneypot_outside_comission', 0 );
		$history_commission        = get_option( 'outside_comission_history', array() );
		$history_commission['now'] = $current_value;

		$history_commission_html = "<h4 style='margin-top:0;'>История комиссий</h4>";
		$history_commission_html .= Helper::periods_pretty_print( $history_commission );

		$theme_fields = [
			Field::make( 'text', 'med_moneypot_platform_percent', 'Отчисления на платформу %' )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'max', '100' )
			     ->set_attribute( 'min', '0' )
			     ->set_attribute( 'step', '0.01' )
			     ->set_default_value( 0 ),
			Field::make( 'text', 'med_moneypot_outside_comission', 'Комиссия %' )
			     ->set_help_text(
				     'Комиссия, вычитаемая из всех отчислений (специальности, аффиляты, платформа сайта). ' .
				     'Налоги, платежные системы и т.д.<br> Каждое изменение сохраняется в истории ' .
				     '- для возможности отслеживания, какой размер сторонней комиссии была на момент какого-либо отчисления.'
			     )
			     ->set_attribute( 'type', 'number' )
			     ->set_attribute( 'max', '100' )
			     ->set_attribute( 'min', '0' )
			     ->set_attribute( 'step', '0.01' )
			     ->set_default_value( 0 ),
			Field::make( 'html', 'crb_html', __( 'Section Description' ) )
			     ->set_html($history_commission_html)
		];

		Container::make( 'theme_options', 'Общие комиссии' )
		         ->set_page_parent( 'moneypot' )
		         ->set_page_file( 'moneypot-platform' )
		         ->where( 'current_user_role', 'IN', array( 'administrator' ) )
		         ->add_fields( $theme_fields );
	}

	public function write_outside_comission_history( $delete, \Carbon_Fields\Field\Field $field ) {

		if ( $field->get_base_name() !== 'med_moneypot_outside_comission' ) {
			return $delete;
		}

		// Проверяем, будет ли сохраняться поле
		$save = apply_filters( 'carbon_fields_should_save_field_value', true, $field->get_value(), $field );

		if ( ! $save ) {
			return $delete;
		}

		$old_value = carbon_get_theme_option( 'med_moneypot_outside_comission' );
		$new_value = $field->get_value();

		// Одинаковые значения
		if ( $new_value === $old_value ) {
			return $delete;
		}

		$current_time       = current_time( 'Y.m.d H:i:s' );
		$history_commission = get_option( 'outside_comission_history', array() );

		$history_commission[ $current_time ] = $old_value;

		update_option( 'outside_comission_history', $history_commission, false );

		return $delete;
	}

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}