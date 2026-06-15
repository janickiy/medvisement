<?php
use Carbon_Fields\Container;
use Carbon_Fields\Field;

class MedProductFields {

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
	    add_action( 'carbon_fields_register_fields', [$this, 'carbon_fields_register_fields'] );
    }


	public function carbon_fields_register_fields() {

		$card_fields = [];

		$card_fields[] = Field::make( 'text', 'product_custom_price', __( 'Кастомная цена' ) )
		                      ->set_help_text( 'Если заполнено, будет отображаться вместо обычной цены. Например: "1990₽/мес"' );

		$card_fields[] = Field::make( 'text', 'product_price_after_expiry', __( 'Цена после окончания доступа' ) )
		                      ->set_help_text( 'Дополнительная информация о цене, которая отображается в описании карточки. Например: "Далее 5990₽/год"' );

		Container::make( 'post_meta', 'Карточка товара' )
		         ->where( 'post_type', 'IN', [ 'product' ] )
		         ->add_fields( $card_fields );

		// Поля для тематических тарифов (категория theme-packs)
		Container::make( 'post_meta', 'Тематический тариф' )
		         ->where( 'post_type', '=', 'product' )
		         ->set_context( 'normal' )
		         ->set_priority( 'high' )
		         ->add_fields( [
			         Field::make( 'association', 'theme_pack_articles', __( 'Статьи тарифа' ) )
			              ->set_types( [
				              [
					              'type'      => 'post',
					              'post_type' => 'disease',
				              ]
			              ] )
			              ->set_help_text( 'Выберите статьи, которые будут доступны при покупке этого тарифа' ),

			         Field::make( 'text', 'theme_pack_duration_days', __( 'Длительность доступа (дней)' ) )
			              ->set_attribute( 'type', 'number' )
			              ->set_attribute( 'min', 1 )
			              ->set_default_value( 365 )
			              ->set_help_text( 'Количество дней доступа к каждой статье с момента первого открытия' ),
		         ] );

		// Подписка
		Container::make( 'post_meta', 'Подписка' )
		         ->where( 'post_type', '=', 'product' )
		         ->set_context( 'normal' )
		         ->set_priority( 'high' )
		         ->add_fields( [
			         Field::make( 'association', 'subscription_specialties', __( 'Специальности подписки' ) )
			              ->set_types( [
				              [
					              'type'     => 'term',
					              'taxonomy' => 'specialty',
				              ]
			              ] )
			              ->set_help_text( 'Специальности, к которым дает доступ подписка, включая уже оформленные.' )
		         ] );
	}

}

$MedProductFields = new MedProductFields();