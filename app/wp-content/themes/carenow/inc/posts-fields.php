<?php
use Carbon_Fields\Container;
use Carbon_Fields\Field;

class MedAdditionalFields {

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
	    add_action( 'carbon_fields_register_fields', [$this, 'carbon_fields_register_fields'] );

	    //Дополнительные мета поля для поиска
	    add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets'], 20);
        add_action('init', [$this, 'wp_meta'], 20);
    }


	public function carbon_fields_register_fields() {

		$current_user = get_userdata( get_current_user_id() );
    	
		$site_staff = get_users( [
			'role__in' => [ 'author', 'editor' ],
			'orderby'  => 'display_name',
			'order'    => 'ASC'
		] );

		$site_staff_options = [
			0 => 'Пусто'
		];

		foreach ( $site_staff as $staff ) {
			$site_staff_options[ $staff->ID ] = $staff->display_name;
		}

		$default_author_id = 0;
		if ( isset( $site_staff_options[ get_current_user_id() ] ) && isset($current_user->roles) && in_array( 'author', $current_user->roles) ) {
			$default_author_id = get_current_user_id();
		}
		$default_editor_id = 0;
		if ( isset( $site_staff_options[ get_current_user_id() ] ) && isset($current_user->roles) && in_array( 'editor', $current_user->roles) ) {
			$default_editor_id = get_current_user_id();
		}


		$staff_fields = [];

		$staff_fields[] = Field::make( 'multiselect', 'med_article_authors', __( 'Авторы' ) )
		                       ->add_options( $site_staff_options )
		                       ->set_default_value( $default_author_id );

		$staff_fields[] = Field::make( 'multiselect', 'med_article_editors', __( 'Редакторы' ) )
		                       ->add_options( $site_staff_options )
		                       ->set_default_value( $default_editor_id );

		$staff_fields[] = Field::make( 'multiselect', 'med_article_translators', __( 'Переводчики' ) )
		                       ->add_options( $site_staff_options )
		                       ->set_default_value( $default_editor_id );

		Container::make( 'post_meta', 'Авторы и Редакторы' )
		         ->where( 'post_type', 'IN', [ 'substance', 'disease' ] )
		         ->where( 'current_user_role', 'IN', array( 'administrator' ) )
		         ->add_fields( $staff_fields );

		$file_fields   = [];
		$file_fields[] = Field::make( 'complex', 'med_article_files', 'Файлы' )
		                      ->set_layout( 'tabbed-horizontal' )
		                      ->add_fields( [
			                      Field::make( 'text', 'title', 'Название' ),
			                      Field::make( 'file', 'file', 'Файл' ),
		                      ] );

		Container::make( 'post_meta', 'Файлы' )
		         ->where( 'post_type', 'IN', [ 'substance', 'disease' ] )
		         ->where( 'current_user_role', 'IN', array( 'administrator' ) )
		         ->add_fields( $file_fields );

		$alternative_names_fields = [];

		$alternative_names_fields[] = Field::make( 'complex', 'med_article_alternative_names', 'Альтернативные названия' )
		                                   ->set_layout( 'tabbed-horizontal' )
		                                   ->set_help_text(
			                                   'Если поисковый запрос совпадет с альтернативным названием, то статья будет показана в поиске. ' .
			                                   'При отмеченном чекбоксе - оригинальное название будет заменено на альтернативное'
		                                   )
		                                   ->add_fields( [
			                                   Field::make( 'text', 'title', 'Название' ),
			                                   Field::make( 'checkbox', 'show_in_search', 'Отображать в поиске' ),
		                                   ] );

		Container::make( 'post_meta', 'Альтернативные названия' )
		         ->where( 'post_type', 'IN', [ 'substance', 'disease' ] )
		         ->where( 'current_user_role', 'IN', array( 'administrator', 'editor' ) )
		         ->add_fields( $alternative_names_fields );

	}

    //Мета поле
    public function enqueue_block_editor_assets()
    {
    	// Только админу доступно
	    if ( ! current_user_can('administrator') ) {
	    	return;
	    }

        global $post;

        if (!$post instanceof \WP_Post) {
            return;
        }

        //Проверяем тип поста
        if ( ! in_array($post->post_type, \MedviseElasticPress::allowed_post_types()))
            return;

        //Поле приоритета для сортировки и отметки
        $asset_file = include( THEMESFLAT_DIR . 'build/index.asset.php');
        wp_enqueue_script(
            'editor-medvisement',
            THEMESFLAT_LINK . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );
    }

    public function wp_meta() {
        register_post_meta(
            '',
            'ep_free',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
            ]
        );
    }
}

$MedAdditionalFields = new MedAdditionalFields();