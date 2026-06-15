<?php
/**
 * Plugin Name: Medvise Parser REST
 * Description: Custom REST endpoint to upsert disease posts for the parser.
 * Version: 0.1.1
 * Author: Julia
 */

if (!defined('ABSPATH')) {
    exit;
}

const MEDVISE_PARSER_REST_SOURCE_QUERY_VAR = 'medvise_eng_article_source';
const MEDVISE_PARSER_REST_SOURCE_REWRITE_OPTION = 'medvise_eng_article_source_rewrite_version';
const MEDVISE_PARSER_REST_SOURCE_REWRITE_VERSION = '1.0.0';
const MEDVISE_PARSER_REST_ENGLISH_ARTICLE_TYPE_SLUG = 'eng-articles';

add_action('init', 'medvise_parser_rest_register_source_routes');
add_filter('query_vars', 'medvise_parser_rest_register_source_query_vars');
add_action('template_redirect', 'medvise_parser_rest_resolve_source_route', 1);
add_filter('the_content', 'medvise_parser_rest_rewrite_source_links_in_content', 9);

function medvise_parser_rest_register_source_routes() {
    add_rewrite_rule(
        '^eng-articles/source/([0-9]+)/?$',
        'index.php?' . MEDVISE_PARSER_REST_SOURCE_QUERY_VAR . '=$matches[1]',
        'top'
    );

    if (MEDVISE_PARSER_REST_SOURCE_REWRITE_VERSION !== get_option(MEDVISE_PARSER_REST_SOURCE_REWRITE_OPTION)) {
        flush_rewrite_rules(false);
        update_option(MEDVISE_PARSER_REST_SOURCE_REWRITE_OPTION, MEDVISE_PARSER_REST_SOURCE_REWRITE_VERSION, false);
    }
}

function medvise_parser_rest_register_source_query_vars($vars) {
    $vars[] = MEDVISE_PARSER_REST_SOURCE_QUERY_VAR;
    return $vars;
}

function medvise_parser_rest_resolve_source_route() {
    $topic_id = get_query_var(MEDVISE_PARSER_REST_SOURCE_QUERY_VAR);
    if ('' === (string) $topic_id) {
        return;
    }

    $topic_id = preg_replace('/\D+/', '', (string) $topic_id);
    if ('' === $topic_id) {
        medvise_parser_rest_render_source_404();
    }

    $post_id = medvise_parser_rest_find_published_english_post_by_topic_id($topic_id);
    if ($post_id <= 0) {
        medvise_parser_rest_render_source_404();
    }

    $permalink = get_permalink($post_id);
    if (!$permalink) {
        medvise_parser_rest_render_source_404();
    }

    wp_safe_redirect($permalink, 302);
    exit;
}

function medvise_parser_rest_find_published_english_post_by_topic_id($topic_id) {
    $args = [
        'post_type' => 'disease',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'ignore_sticky_posts' => true,
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query' => [
            [
                'key' => 'source_id',
                'value' => 'pi_en_' . $topic_id,
                'compare' => '=',
            ],
        ],
    ];

    if (taxonomy_exists('article-type')) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'article-type',
                'field' => 'slug',
                'terms' => MEDVISE_PARSER_REST_ENGLISH_ARTICLE_TYPE_SLUG,
            ],
        ];
    }

    $query = new WP_Query($args);
    return !empty($query->posts) ? (int) $query->posts[0] : 0;
}

function medvise_parser_rest_render_source_404() {
    global $wp_query;

    if ($wp_query instanceof WP_Query) {
        $wp_query->set_404();
    }

    status_header(404);
    nocache_headers();

    $template = get_query_template('404');
    if ($template) {
        include $template;
    }

    exit;
}

function medvise_parser_rest_rewrite_source_links_in_content($content) {
    $content = (string) $content;
    if (false === strpos($content, '/contents/topics/')) {
        return $content;
    }

    return preg_replace_callback(
        '/\bhref=(["\'])(?:https?:\/\/(?:www\.)?(?:uptodate\.com|utd\.libook\.xyz))?\/contents\/topics\/([0-9]+)\/?(#[^"\']*)?\1/i',
        static function ($matches) {
            $quote = $matches[1];
            $topic_id = $matches[2];
            $fragment = isset($matches[3]) ? sanitize_text_field($matches[3]) : '';
            $href = '/eng-articles/source/' . $topic_id . '/' . $fragment;

            return 'href=' . $quote . esc_url($href) . $quote;
        },
        $content
    );
}

function medvise_parser_rest_is_english_article_payload($external_id, $article_type_slug, $is_english) {
    return $is_english
        || MEDVISE_PARSER_REST_ENGLISH_ARTICLE_TYPE_SLUG === sanitize_title((string) $article_type_slug)
        || 1 === preg_match('/^en_\d+$/', (string) $external_id);
}

function medvise_parser_rest_build_article_post_name($title, $external_id) {
    $post_name = sanitize_title((string) $title);
    if ('' === $post_name) {
        $post_name = sanitize_title((string) $external_id);
    }

    return $post_name;
}

add_action('rest_api_init', function () {
    register_rest_route('medvise/v1', '/disease/upsert', [
        'methods'  => 'POST',
        'callback' => 'medvise_parser_rest_upsert_disease',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_rest_route('medvise/v1', '/disease/status', [
        'methods'  => 'GET',
        'callback' => 'medvise_parser_rest_disease_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'external_id' => [
                'required' => false,
                'type' => 'string',
            ],
            'source_id' => [
                'required' => false,
                'type' => 'string',
            ],
        ],
    ]);
});

/**
 * Находит (или создаёт) термин и возвращает его slug.
 *
 * @param string $taxonomy
 * @param string $value  Имя или slug термина.
 * @return string|null
 */
function medvise_parser_rest_get_or_create_term_slug($taxonomy, $value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    // Если это уже slug (латиница/цифры/дефисы) — пробуем найти по slug.
    if (preg_match('/^[a-z0-9\-]+$/', $value)) {
        $term = get_term_by('slug', $value, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return $term->slug;
        }
    }

    // Пробуем как имя.
    $term = get_term_by('name', $value, $taxonomy);
    if ($term && !is_wp_error($term)) {
        return $term->slug;
    }

    // Создаём новый термин.
    $created = wp_insert_term($value, $taxonomy);
    if (is_wp_error($created)) {
        return null;
    }

    if (isset($created['slug']) && $created['slug']) {
        return $created['slug'];
    }

    $term_id = isset($created['term_id']) ? (int) $created['term_id'] : 0;
    if ($term_id > 0) {
        $term = get_term($term_id, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return $term->slug;
        }
    }

    return null;
}

/**
 * Устанавливает термины по массиву значений (имя или slug).
 *
 * @param int    $post_id
 * @param string $taxonomy
 * @param array  $values
 */
function medvise_parser_rest_set_terms($post_id, $taxonomy, $values) {
    if (!is_array($values) || empty($values)) {
        return;
    }

    $slugs = [];
    foreach ($values as $v) {
        $slug = medvise_parser_rest_get_or_create_term_slug($taxonomy, $v);
        if ($slug) {
            $slugs[] = $slug;
        }
    }

    if (!empty($slugs)) {
        wp_set_object_terms($post_id, $slugs, $taxonomy, false);
    }
}

function medvise_parser_rest_get_or_create_article_type_slug($slug) {
    $slug = sanitize_title((string) $slug);
    if ($slug === '') {
        return '';
    }

    if (!taxonomy_exists('article-type')) {
        return $slug;
    }

    $term = get_term_by('slug', $slug, 'article-type');
    if ($term && !is_wp_error($term)) {
        return $term->slug;
    }

    $created = wp_insert_term($slug, 'article-type', ['slug' => $slug]);
    if (is_wp_error($created)) {
        $term_id = (int) $created->get_error_data('term_exists');
        if ($term_id > 0) {
            $term = get_term($term_id, 'article-type');
            if ($term && !is_wp_error($term)) {
                return $term->slug;
            }
        }
        return $slug;
    }

    $term_id = isset($created['term_id']) ? (int) $created['term_id'] : 0;
    if ($term_id > 0) {
        $term = get_term($term_id, 'article-type');
        if ($term && !is_wp_error($term)) {
            return $term->slug;
        }
    }

    return $slug;
}

function medvise_parser_rest_upsert_disease(WP_REST_Request $request) {
    $params = $request->get_json_params();

    $title = isset($params['title']) ? wp_strip_all_tags($params['title']) : '';
    $content = isset($params['content']) ? $params['content'] : '';
    $external_id = isset($params['external_id']) ? sanitize_text_field($params['external_id']) : '';
    $status = isset($params['status']) ? sanitize_text_field($params['status']) : 'draft';
    $article_type_slug = isset($params['article_type_slug']) ? sanitize_text_field($params['article_type_slug']) : '';
    $is_english = !empty($params['is_english']);

    // Дополнительные таксономии и мета-данные (как в CLI-варианте).
    $age_slugs = [];
    if (isset($params['age_slugs']) && is_array($params['age_slugs'])) {
        foreach ($params['age_slugs'] as $age) {
            $age_slugs[] = sanitize_title($age);
        }
    }

    $symptom_values = [];
    if (isset($params['symptom_names_or_slugs']) && is_array($params['symptom_names_or_slugs'])) {
        foreach ($params['symptom_names_or_slugs'] as $sym) {
            $symptom_values[] = sanitize_text_field($sym);
        }
    }

    $specialty_slugs = [];
    if (isset($params['specialty_slugs']) && is_array($params['specialty_slugs'])) {
        foreach ($params['specialty_slugs'] as $spec) {
            $specialty_slugs[] = sanitize_title($spec);
        }
    }

    $meta_extra = [];
    if (isset($params['meta_extra']) && is_array($params['meta_extra'])) {
        $meta_extra = $params['meta_extra'];
    }

    if (!$title || !$external_id) {
        return new WP_REST_Response(['ok' => false, 'error' => 'title/external_id required'], 400);
    }

    $source_id = 'pi_' . $external_id;
    $post_id = medvise_parser_find_post_by_source_id($source_id);

    $postarr = [
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => $status,
        'post_type'    => 'disease',
    ];

    if (medvise_parser_rest_is_english_article_payload($external_id, $article_type_slug, $is_english)) {
        $current_post_name = $post_id ? (string) get_post_field('post_name', $post_id) : '';
        if ('' === $current_post_name) {
            $post_name = medvise_parser_rest_build_article_post_name($title, $external_id);
            if ('' !== $post_name) {
                $postarr['post_name'] = $post_name;
            }
        }
    }

    if ($post_id) {
        $postarr['ID'] = $post_id;
        $new_id = wp_update_post($postarr, true);
    } else {
        $new_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($new_id)) {
        return new WP_REST_Response(['ok' => false, 'error' => $new_id->get_error_message()], 500);
    }

    $post_id = (int)$new_id;

    // meta
    update_post_meta($post_id, 'source_id', $source_id);

    // Флаг английской статьи (как в CLI-варианте).
    if ($is_english) {
        update_post_meta($post_id, 'medvise_is_english_article', '1');
    }

    // Дополнительные произвольные мета-поля.
    if (!empty($meta_extra) && is_array($meta_extra)) {
        foreach ($meta_extra as $key => $value) {
            $meta_key = sanitize_key($key);
            if ($meta_key === '') {
                continue;
            }
            update_post_meta($post_id, $meta_key, $value);
        }
    }

    // taxonomy (optional)
    if ($article_type_slug) {
        $article_type_slug = medvise_parser_rest_get_or_create_article_type_slug($article_type_slug);
        wp_set_object_terms($post_id, [$article_type_slug], 'article-type', false);
    }

    if (!empty($age_slugs)) {
        medvise_parser_rest_set_terms($post_id, 'age', $age_slugs);
    }

    if (!empty($symptom_values)) {
        medvise_parser_rest_set_terms($post_id, 'symptoms', $symptom_values);
    }

    if (!empty($specialty_slugs)) {
        medvise_parser_rest_set_terms($post_id, 'specialty', $specialty_slugs);
    }

    return new WP_REST_Response(['ok' => true, 'post_id' => $post_id], 200);
}

function medvise_parser_find_post_by_source_id($source_id) {
    $q = new WP_Query([
        'post_type'      => 'disease',
        'post_status'    => 'any',      // ищем среди всех статусов (draft/publish и т.д.)
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => 'source_id',
                'value' => $source_id,
                'compare' => '=',
            ]
        ],
    ]);
    if (!empty($q->posts)) {
        return (int)$q->posts[0];
    }
    return 0;
}

/**
 * Проверка статуса заболевания (исправленная версия)
 */
function medvise_parser_rest_disease_status(WP_REST_Request $request) {
    // Получаем параметры (работает и для query string, и для JSON body)
    $raw_external_id = $request->get_param('external_id');
    $raw_source_id = $request->get_param('source_id');
    
    // Безопасная санитизация с проверкой на null
    $external_id = ($raw_external_id !== null) ? sanitize_text_field((string) $raw_external_id) : '';
    $source_id = ($raw_source_id !== null) ? sanitize_text_field((string) $raw_source_id) : '';

    // Если source_id не передан, но есть external_id — строим source_id автоматически
    // Используем строгое сравнение с пустой строкой
    if ($source_id === '' && $external_id !== '') {
        $source_id = 'pi_' . $external_id;
    }

    // Валидация: должен быть указан хотя бы один идентификатор
    if ($source_id === '') {
        return new WP_REST_Response(
            ['ok' => false, 'error' => 'external_id or source_id required'], 
            400
        );
    }

    // Поиск поста
    $post_id = medvise_parser_find_post_by_source_id($source_id);
    
    if ($post_id) {
        $status = get_post_status($post_id);
        return new WP_REST_Response([
            'ok' => true,
            'exists' => true,
            'post_id' => (int) $post_id,
            'post_status' => $status ? (string) $status : '',
            'source_id' => $source_id, // Возвращаем тот, по которому искали
        ], 200);
    }

    // Пост не найден
    return new WP_REST_Response([
        'ok' => true,
        'exists' => false,
        'post_id' => 0,
        'post_status' => '',
        'source_id' => $source_id,
    ], 200);
}

// === НОВЫЙ ФИЛЬТР: Базовая аутентификация по основному паролю ===
add_filter('determine_current_user', function($user) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Применяем ТОЛЬКО к нашим эндпоинтам
    if (strpos($request_uri, '/wp-json/medvise/v1/') === false) {
        return $user;
    }

    $username = null;
    $password = null;

    if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        $username = sanitize_user($_SERVER['PHP_AUTH_USER']);
        $password = $_SERVER['PHP_AUTH_PW'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (strpos($auth_header, 'Basic ') === 0) {
            $decoded = base64_decode(trim(substr($auth_header, 6)));
            $parts = explode(':', $decoded, 2);
            if (count($parts) === 2) {
                $username = sanitize_user($parts[0]);
                $password = $parts[1];
            }
        }
    }

    if ($username && $password) {
        $user_obj = wp_authenticate($username, $password);
        if (!is_wp_error($user_obj) && $user_obj instanceof WP_User) {
            return $user_obj->ID;
        }
    }

    return $user;
}, 20);
