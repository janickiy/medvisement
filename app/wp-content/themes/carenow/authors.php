<?php
/**
 * Template Name: Авторы и Редакторы
 */
get_header();

$specialty_terms = get_terms(array(
    'taxonomy' => 'specialty',
    'hide_empty' => false,
));

$authors = get_users([
    'role__in' => ['author', 'editor'],
    'meta_key' => 'med_specialty',
    'meta_value' => [''],
    'meta_compare' => 'NOT IN',
    'orderby' => 'display_name',
    'order' => 'ASC'
]);

$authors_categories = [];

//Подготавливаем массив для удобного вывода
foreach ($specialty_terms as $specialty_term) {

    $authors_categories[$specialty_term->term_id]['title'] = $specialty_term->name;
    $authors_categories[$specialty_term->term_id]['authors'] = [];

    foreach ($authors as $author) {
        $author_med_specialty = get_user_meta($author->ID, 'med_specialty', true);

        if ( ! empty($author_med_specialty[$specialty_term->term_id]) ) {
            $authors_categories[$specialty_term->term_id]['authors'][] = "{$author_med_specialty[$specialty_term->term_id]} - " .
                "<a href='" . get_author_posts_url($author->ID) . "'>{$author->display_name}</a>";
        }

    }

    //В специальности никого нет - убираем
    if (empty($authors_categories[$specialty_term->term_id]['authors']))
        unset($authors_categories[$specialty_term->term_id]);
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="primary" class="content-area">
                <main id="main" class="post-wrap" role="main">

                    <h1 class="entry-title"><?php the_title(); ?></h1>

                    <?php foreach ($authors_categories as $authors_category): ?>
                        <details class="wp-block-details is-layout-flow wp-block-details-is-layout-flow">
                            <summary><?= $authors_category['title']; ?></summary>

                            <ul>
                                <?php foreach ($authors_category['authors'] as $author): ?>
                                    <li>
                                        <?= $author; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endforeach; ?>

                </main><!-- #main -->
            </div><!-- #primary -->
            <?php
            if (themesflat_get_opt('sidebar_layout') == 'sidebar-left' || themesflat_get_opt('sidebar_layout') == 'sidebar-right') :
                get_sidebar();
            endif;
            ?>
        </div><!-- /.col-md-12 -->
    </div><!-- /.row -->
</div><!-- /.container -->
<?php get_footer(); ?>
