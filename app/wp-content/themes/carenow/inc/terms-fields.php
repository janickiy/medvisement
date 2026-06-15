<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class MedTermsFields
{

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        add_action('carbon_fields_register_fields', [$this, 'carbon_fields_register_fields']);
    }

    public function carbon_fields_register_fields()
    {

	    Container::make('term_meta', 'Настройки')
	             ->where('term_taxonomy', 'IN', ['specialty'])
	             ->add_fields(array(
		             Field::make( 'select', 'show_age_filter', 'Показывать фильтр возраста' )
		                  ->set_options( array(
			                  '0' => 'Нет',
			                  '1' => 'Да'
		                  ) )
	             ));

        Container::make('term_meta', 'Настройки')
            ->where('term_taxonomy', 'IN', ['specialty', 'drug-classes'])
            ->add_fields(array(
                Field::make('image', 'image', 'Изображение'),
                Field::make('text', 'tree_shortcode', 'Древо шорткод')
            ));
    }

}

$MedTermsFields = new MedTermsFields();