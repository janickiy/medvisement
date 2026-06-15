<?php
//Удаляем эмодзи
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('admin_print_styles', 'print_emoji_styles');

//REST API
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('template_redirect', 'rest_output_link_header', 11);

//Не нужен, т.к. используем REST API
add_filter('xmlrpc_enabled', '__return_false');

//Отключаем вывод ссылок на фиды и вспомогательные теги
remove_action('wp_head', 'feed_links_extra', 3); // Display the links to the extra feeds such as category feeds
remove_action('wp_head', 'feed_links', 2); // Display the links to the general feeds: Post and Comment Feed
remove_action('wp_head', 'rsd_link'); // Display the link to the Really Simple Discovery service endpoint, EditURI link
remove_action('wp_head', 'wlwmanifest_link'); // Display the link to the Windows Live Writer manifest file.
remove_action('wp_head', 'index_rel_link'); // index link
remove_action('wp_head', 'parent_post_rel_link', 10, 0); // prev link
remove_action('wp_head', 'start_post_rel_link', 10, 0); // start link
remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0); // Display relational links for the posts adjacent to the current post.
remove_action('wp_head', 'wp_generator'); // Display the XHTML generator that is generated on the wp_head hook, WP version

//Фиды
add_action('do_feed', 'medvisement_disable_feed', 1);
add_action('do_feed_rdf', 'medvisement_disable_feed', 1);
add_action('do_feed_rss', 'medvisement_disable_feed', 1);
add_action('do_feed_rss2', 'medvisement_disable_feed', 1);
add_action('do_feed_atom', 'medvisement_disable_feed', 1);
add_action('do_feed_rss2_comments', 'medvisement_disable_feed', 1);
add_action('do_feed_atom_comments', 'medvisement_disable_feed', 1);

function medvisement_disable_feed()
{
    wp_die(__('Фиды не доступны, пожалуйст, вернитесь на <a href="' . esc_url(home_url('/')) . '">главную</a>!'));
}

// Отключаем поиск при пустом запросе
add_filter( 'posts_search', function ( $search, \WP_Query $q ) {
	if ( ! is_admin() && empty( $search ) && $q->is_search() && $q->is_main_query() ) {
		$search .= " AND 0=1 ";
	}

	return $search;
}, 10, 2 );