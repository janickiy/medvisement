<?php


namespace MedviseSubscriptions;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Admin {

	public function init() {

		add_action( 'init', [ $this, 'medvise_seolimit_medvise_block_init' ] );

		// поля настроек для оплаты статей
		add_action( 'carbon_fields_register_fields', [$this, 'articles_payment_register_fields' ] );

		// выбор только опубликовынных товаров для оплаты статей
		add_filter( 'carbon_fields_association_field_options_med_article_payment_product_post_product', [$this, 'articles_payment_option_product_publish' ] );

		// поля настроек для оплаты специальностей
		add_action( 'carbon_fields_register_fields', [$this, 'specialty_access_register_fields' ] );

		// выбор только опубликовынных товаров для оплаты специальностей
		add_filter( 'carbon_fields_association_field_options_med_specialty_access_product_post_product', [$this, 'specialty_access_option_product_publish' ] );

	}

	public function medvise_seolimit_medvise_block_init() {
		register_block_type( MEDVISESUB_PATH . 'build/seodetails' );
		register_block_type( MEDVISESUB_PATH . 'build/seolimit' );
	}

	public function articles_payment_register_fields() {

		// поля настроек для Woocommerce -> "Покупка статей"
		$article_payment_fields   = [];

		// Длительность подписки
		$article_payment_fields[] = Field::make( 'text', 'med_article_access_days', 'Длительность подписки (дни)' )
										 ->set_attribute( 'type', 'number' )
										 ->set_default_value( 90 );

		$article_payment_fields[] = Field::make( 'complex', 'med_article_payment_category', 'Категории' )
										  ->set_help_text('Категории не должны иметь одинаковую цену!')
										  ->set_layout( 'tabbed-horizontal' )
										  ->add_fields( [
											   Field::make( 'text', 'article-payment-category-name', 'Категория' ),
											   Field::make( 'text', 'article-payment-category-price', 'Стоимость' ),
										  ] );

		$article_payment_fields[] = Field::make( 'association', 'med_article_payment_product', 'Товар используемый для покупки' )
										  ->set_max( 1 )
										  ->set_types( array(
											  array(
												  'type' => 'post',
												  'post_type' => 'product',
											  ),
										  ) );

		Container::make( 'theme_options', 'Покупка статей' )
				 ->set_page_parent( 'woocommerce' )
				 ->where( 'current_user_role', 'IN', array( 'administrator' ) )
				 ->add_fields( $article_payment_fields );

		// селект для выбора стоимости в самой статье
		$payment_categories = carbon_get_theme_option( 'med_article_payment_category' );
		$categories         = [
			0 => 'Без категории'
		];
		if ( ! empty( $payment_categories ) ) {
			foreach ( $payment_categories as $category ) {
				$categories[ $category['article-payment-category-price'] ] = $category['article-payment-category-name'];
			}
		}

		$article_payment_select_price   = [];

		$article_payment_select_price[] = Field::make( 'select', 'med_article_payment_category_select', 'Выберите категорию' )
			   ->add_options( $categories )
			   ->set_default_value( 0 );

		Container::make( 'post_meta', 'Стоимость статьи' )
				 ->where( 'post_type', 'IN', [ 'disease' ] )
				 ->where( 'current_user_role', 'IN', array( 'administrator' ) )
				 ->set_priority( 'high' )
				 ->add_fields( $article_payment_select_price );
	}

	public function articles_payment_option_product_publish( $query_arguments ) {
		$query_arguments['post_status'] = 'publish';
		return $query_arguments;
	}

	public function specialty_access_register_fields() {

		// поля настроек для Woocommerce -> "Покупка специальностей"
		$specialty_access_fields   = [];

		// Длительность подписки
		$specialty_access_fields[] = Field::make( 'text', 'med_specialty_access_days', 'Длительность подписки (дни)' )
		                                  ->set_attribute( 'type', 'number' )
		                                  ->set_default_value( 365 );

		$specialty_access_fields[] = Field::make( 'association', 'med_specialty_access_product', 'Товар используемый для покупки' )
										  ->set_max( 1 )
										  ->set_types( array(
											  array(
												  'type' => 'post',
												  'post_type' => 'product',
											  ),
										  ) );

		Container::make( 'theme_options', 'Покупка специальностей без продления' )
				 ->set_page_parent( 'woocommerce' )
				 ->where( 'current_user_role', 'IN', array( 'administrator' ) )
				 ->add_fields( $specialty_access_fields );

		// поле стоимости специальности
		$specialty_access   = [];
		$specialty_access[] = Field::make( 'text', 'med_specialty_access_price', 'Стоимость без продления' )
		                           ->set_attribute( 'type', 'number' )
		                           ->set_default_value( 0 );

		$specialty_access[] = Field::make( 'association', 'med_specialty_access_product_month', 'Товар для ежемесячной подписки' )
		                           ->set_max( 1 )
		                           ->set_types( array(
			                           array(
				                           'type'      => 'post',
				                           'post_type' => 'product',
			                           ),
		                           ) );

		$specialty_access[] = Field::make( 'association', 'med_specialty_access_product_year', 'Товар для годовой подписки' )
		                           ->set_max( 1 )
		                           ->set_types( array(
			                           array(
				                           'type'      => 'post',
				                           'post_type' => 'product',
			                           ),
		                           ) );

		Container::make('term_meta', 'Стоимость')
			     ->show_on_taxonomy( 'specialty' )
			     ->where( 'current_user_role', 'IN', array( 'administrator' ) )
			     ->add_fields( $specialty_access );
	}

	public function specialty_access_option_product_publish( $query_arguments ) {
		$query_arguments['post_status'] = 'publish';
		return $query_arguments;
	}

}