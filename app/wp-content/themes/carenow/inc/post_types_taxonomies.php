<?php
function medvise_post_types()
{
    //Заболевания
    $disease_args = [
        'label' => null,
        'labels' => [
            'name' => 'Заболевания', // основное название для типа записи
            'singular_name' => 'Заболевание', // название для одной записи этого типа
            'add_new' => 'Добавить Заболевание', // для добавления новой записи
            'add_new_item' => 'Добавление Заболевания', // заголовка у вновь создаваемой записи в админ-панели.
            'edit_item' => 'Редактирование Заболевания', // для редактирования типа записи
            'new_item' => 'Новое Заболевание', // текст новой записи
            'view_item' => 'Смотреть Заболевание', // для просмотра записи этого типа.
            'search_items' => 'Искать Заболевание', // для поиска по этим типам записи
            'not_found' => 'Не найдено Заболеваний', // если в результате поиска ничего не было найдено
            'not_found_in_trash' => 'Не найдено', // если не было найдено в корзине
            'menu_name' => 'Заболевания', // название меню
        ],
        'description' => '',
        'public' => true,
        'show_in_menu' => null, // показывать ли в меню админки
        'show_in_rest' => true, // добавить в REST API. C WP 4.7
        'menu_position' => 4,
        'menu_icon' => 'dashicons-universal-access-alt',
        'capability_type' => ['disease', 'diseases'],
        'map_meta_cap' => true,
        'hierarchical' => false,
        'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
        'taxonomies' => [],
        'has_archive' => false,
        'rewrite' => true,
        'query_var' => true
    ];
    register_post_type('disease', $disease_args);

	register_taxonomy('article-type', ['disease', 'substance'], [
		'labels' => [
			'name' => 'Тип статьи',
			'singular_name' => 'Тип статьи',
			'search_items' => 'Искать',
			'all_items' => 'Все Типы Статей',
			'view_item ' => 'Смотреть',
			'edit_item' => 'Изменить',
			'update_item' => 'Обновить',
			'add_new_item' => 'Добавить Тип Статьи',
			'new_item_name' => 'Название Типа Статьи',
			'menu_name' => 'Тип Статьи',
			'back_to_items' => '← Назад',
		],
		'public' => true,
		'show_in_rest' => true,
		'hierarchical' => false,
		'rewrite' => false,
		'publicly_queryable' => false,
		'capabilities' => [
			'manage_terms' => 'manage_specialty',
			'edit_terms' => 'edit_specialty',
			'delete_terms' => 'delete_specialty',
			'assign_terms' => 'assign_specialty',
		],
		'show_admin_column' => true,
	]);

    register_taxonomy('symptoms', 'disease', [
        'labels' => [
            'name' => 'Симптомы',
            'singular_name' => 'Симптом',
            'search_items' => 'Искать Симптомы',
            'all_items' => 'Все Симптомы',
            'view_item ' => 'Смотреть',
            'edit_item' => 'Изменить',
            'update_item' => 'Обновить',
            'add_new_item' => 'Добавить Новый Симптом',
            'new_item_name' => 'Название Нового Симптома',
            'menu_name' => 'Симптомы',
            'back_to_items' => '← Назад',
        ],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false, //Не создать красивый url
        'publicly_queryable' => false, //Отключить на фронте
        'capabilities' => [
	        'manage_terms' => 'manage_symptoms',
	        'edit_terms' => 'edit_symptoms',
	        'delete_terms' => 'delete_symptoms',
	        'assign_terms' => 'assign_symptoms',
        ],
        'show_admin_column' => true
    ]);

    register_taxonomy('age', 'disease', [
        'labels' => [
            'name' => 'Возраста',
            'singular_name' => 'Возраст',
            'search_items' => 'Искать',
            'all_items' => 'Все Возраста',
            'view_item ' => 'Смотреть',
            'edit_item' => 'Изменить',
            'update_item' => 'Обновить',
            'add_new_item' => 'Добавить Возраст',
            'new_item_name' => 'Название Возраст',
            'menu_name' => 'Возраст',
            'back_to_items' => '← Назад',
        ],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
        'publicly_queryable' => false,
        'capabilities' => [
	        'manage_terms' => 'manage_age',
	        'edit_terms' => 'edit_age',
	        'delete_terms' => 'delete_age',
	        'assign_terms' => 'assign_age',
        ],
        'show_admin_column' => true
    ]);

    register_taxonomy('specialty', 'disease', [
        'labels' => [
            'name' => 'Специальности',
            'singular_name' => 'Специальность',
            'search_items' => 'Искать',
            'all_items' => 'Все Специальности',
            'view_item ' => 'Смотреть',
            'edit_item' => 'Изменить',
            'update_item' => 'Обновить',
            'add_new_item' => 'Добавить Специальность',
            'new_item_name' => 'Название Специальности',
            'menu_name' => 'Специальность',
            'back_to_items' => '← Назад',
        ],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'capabilities' => [
	        'manage_terms' => 'manage_specialty',
	        'edit_terms' => 'edit_specialty',
	        'delete_terms' => 'delete_specialty',
	        'assign_terms' => 'assign_specialty',
        ],
        'show_admin_column' => true,
    ]);

    //Препараты
    $substance_args = [
        'label' => null,
        'labels' => [
            'name' => 'Препараты', // основное название для типа записи
            'singular_name' => 'Препарат', // название для одной записи этого типа
            'add_new' => 'Добавить Препарат', // для добавления новой записи
            'add_new_item' => 'Добавление Препарата', // заголовка у вновь создаваемой записи в админ-панели.
            'edit_item' => 'Редактирование Препарата', // для редактирования типа записи
            'new_item' => 'Новый Препарат', // текст новой записи
            'view_item' => 'Смотреть Препарат', // для просмотра записи этого типа.
            'search_items' => 'Искать Препарат', // для поиска по этим типам записи
            'not_found' => 'Не найдено', // если в результате поиска ничего не было найдено
            'not_found_in_trash' => 'Не найдено в корзине', // если не было найдено в корзине
            'menu_name' => 'Препараты', // название меню
        ],
        'description' => '',
        'public' => true,
        'show_in_menu' => null, // показывать ли в меню админки
        'show_in_rest' => true, // добавить в REST API. C WP 4.7
        'menu_position' => 4,
        'menu_icon' => 'dashicons-plus-alt',
        'capability_type' => ['substance', 'substances'],
        'map_meta_cap' => true,
        'hierarchical' => false,
        'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
        'taxonomies' => [],
        'has_archive' => false,
        'rewrite' => true,
        'query_var' => true,
    ];
    register_post_type('substance', $substance_args);

    register_taxonomy('drug-classes', 'substance', [
        'labels' => [
            'name' => 'Группы Лекарственных Средств',
            'singular_name' => 'Группа Лекарственных Средств',
            'search_items' => 'Искать',
            'all_items' => 'Все Группы Лекарственные Средства',
            'view_item ' => 'Смотреть',
            'edit_item' => 'Изменить',
            'update_item' => 'Обновить',
            'add_new_item' => 'Добавить Группу Лекарственных Средств',
            'new_item_name' => 'Название Группы Лекарственных Средств',
            'menu_name' => 'Группы Лекарственных Средств',
            'back_to_items' => '← Назад',
        ],
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => array('hierarchical' => true),
        'capabilities' => [
	        'manage_terms' => 'manage_drug-classes',
	        'edit_terms' => 'edit_drug-classes',
	        'delete_terms' => 'delete_drug-classes',
	        'assign_terms' => 'assign_drug-classes',
        ],
        'show_admin_column' => true
    ]);

}

add_action('init', 'medvise_post_types');

//Фильтр для плагина конвертации постов с классического редактора на Gutenberg
add_filter('post_type_supports_convert_to_blocks', function ($supports, $post_type) {
    $supported_cpts = array(
        'disease',
        'substance',
    );

    if (in_array($post_type, $supported_cpts)) {
        return true;
    }
    return $supports;
}, 10, 2);

//Разрешаем HTML в описании таксономий
remove_filter('pre_term_description', 'wp_filter_kses');
remove_filter('pre_link_description', 'wp_filter_kses');
remove_filter('pre_link_notes', 'wp_filter_kses');
remove_filter('term_description', 'wp_kses_data');

//Включаем HTML редактор в описании таксономий
add_action('specialty_edit_form', 'med_tax_html_editor', 10, 2);
function med_tax_html_editor($tag, $taxonomy)
{
    wp_enqueue_editor();
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function (event) {
            var id = 'description';

            wp.editor.initialize(id, {
                tinymce: {
                    wpautop: true
                },
                quicktags: true
            });
        });
    </script>
    <?php
}
