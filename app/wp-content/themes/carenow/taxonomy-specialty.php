<?php
/**
 * The template for displaying archive pages.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package carenow
 */

get_header(); ?>
<?php
$blog_layout = themesflat_get_opt('blog_archive_layout');
$columns = themesflat_get_opt('blog_grid_columns');

$imgs = array(
    'blog-grid' => 'themesflat-blog',
    'blog-list' => 'themesflat-blog',
);
$class_names = array(
    1 => 'blog-one-column',
    2 => 'blog-two-columns',
    3 => 'blog-three-columns',
    4 => 'blog-four-columns',
);
global $themesflat_thumbnail;
$themesflat_thumbnail = $imgs[$blog_layout];
$themesflat_thumbnail = apply_filters('themesflat/template/themesflat_thumbnail', $themesflat_thumbnail);
$class = array('blog-archive');
$class[] = 'archive-' . get_post_type();
$class[] = $blog_layout;
$class[] = $class_names[$columns];

$class = apply_filters('themesflat/template/blog_class', $class);

$queried_object = get_queried_object();
?>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="wrap-content-area clearfix">
                    <div id="primary" class="content-area">
                        <main id="main"
                              class="post-wrap <?php echo esc_attr(themesflat_get_opt('blog_archive_layout')); ?>"
                              role="main">

                            <h1 class="entry-title"><?= get_the_archive_title(); ?></h1>

                            <?php
                            $show_age_filter = carbon_get_term_meta( $queried_object->term_id, 'show_age_filter' );

                            $age_terms = get_terms('age', [
                                'hide_empty' => false,
                            ]);
                            ?>

                            <p>
                                Если какого-либо заболевания нет в списке или вы обнаружили какую-то техническую или
                                медицинскую ошибку,
                                напишите нам на почту: <a href="mailto:info@medvisement.com">info@medvisement.com</a>
                            </p>

                            <br>

                            <?= get_the_archive_description(); ?>

                            <?php
                            // Древо
                            $tree_shortcode = carbon_get_term_meta($queried_object->term_id, 'tree_shortcode');
                            if ( ! empty($tree_shortcode) ) {
                                echo do_shortcode($tree_shortcode);
                            }
                            ?>

                            <?php
                            // Статьи
                            $article_orderby = (
	                            isset( $_GET['article_order_by'] )
	                            && in_array( $_GET['article_order_by'], [ 'title', 'modified' ] )
                            ) ?
	                            $_GET['article_order_by'] : 'title';

                            $article_order = $article_orderby === 'title' ? 'ASC' : 'DESC';

                            $specialty_article_args = [
                                'posts_per_page' => -1,
                                'tax_query' => [
                                    'relation' => 'AND',
                                    [
                                        'taxonomy' => 'specialty',
                                        'field' => 'term_id',
                                        'terms' => $queried_object->term_id,
                                        'operator' => 'IN'
                                    ],
	                                [
		                                'taxonomy' => 'article-type',
		                                'field' => 'slug',
		                                'terms' => 'article',
		                                'operator' => 'IN'
	                                ]
                                ],
                                'post_type' => 'disease',
                                'post_status' => 'publish',
                                'nopaging' => true,
                                'orderby' => $article_orderby,
                                'order' => $article_order
                            ];
                            
                            if ( ! empty($_GET['article_age']) && $show_age_filter ) {
	                            $specialty_article_args['tax_query'][] = [
                                    'taxonomy' => 'age',
                                    'field' => 'slug',
                                    'terms' => $_GET['article_age'],
                                    'operator' => 'IN'
                                ];
                            }

                            $specialty_articles = get_posts($specialty_article_args);
                            ?>

                            <details id="disease-list"
                                     <?= empty($_GET['article_order_by']) && empty($_GET['article_age']) ? '' : 'open'; ?>
                                     style='margin-bottom:20px;<?= empty($specialty_articles) ? 'display:none;' : ''; ?>'>
                                <summary>Статьи по специальности</summary>

                                <form action="<?= $_SERVER['REQUEST_URI']; ?>" method="get" class="list-filter">

		                            <?php if ( $show_age_filter ): ?>
                                        <label for="article_age_any">
                                            <input type="radio" name="article_age"
                                                   id="article_age_any" <?= empty( $_GET['article_age'] ) ? 'checked' : ''; ?> value="">
                                            Все
                                        </label>
			                            <?php foreach ( $age_terms as $age_term ): ?>
                                            <label for="article_age_<?= $age_term->slug; ?>">
                                                <input type="radio" name="article_age" id="article_age_<?= $age_term->slug; ?>"
                                                       value="<?= $age_term->slug; ?>"
						                            <?= ( isset( $_GET['article_age'] ) && $_GET['article_age'] == $age_term->slug ) ? 'checked' : ''; ?>>
					                            <?= $age_term->name; ?>
                                            </label>
			                            <?php endforeach; ?>
		                            <?php endif; ?>

                                    <?php $order_by = $_GET['article_order_by'] ?? 'title'; ?>
                                    <label for="article_order_by">
                                        Сортировать по:
                                        <select name="article_order_by">
                                            <option value="title" <?php selected($order_by, 'title'); ?>>Алфавит</option>
                                            <option value="modified" <?php selected($order_by, 'modified'); ?>>Дата обновления</option>
                                        </select>
                                    </label>
                                </form>

                                <hr>

                                <ul>
			                        <?php foreach ( $specialty_articles as $specialty_article ): ?>
                                        <li>
                                            <a href="<?= get_permalink( $specialty_article ); ?>"><?= $specialty_article->post_title; ?></a>
                                        </li>
			                        <?php endforeach; ?>
                                </ul>
                            </details>



	                        <?php
	                        // Клинические рекомендации
	                        $clinguide_orderby = (
		                        isset( $_GET['clinguide_order_by'] )
		                        && in_array( $_GET['clinguide_order_by'], [ 'title', 'modified' ] )
	                        ) ?
		                        $_GET['clinguide_order_by'] : 'title';

	                        $clinguide_order = $clinguide_orderby === 'title' ? 'ASC' : 'DESC';

	                        $specialty_clinguide_args = [
		                        'posts_per_page' => -1,
		                        'tax_query' => [
			                        'relation' => 'AND',
			                        [
				                        'taxonomy' => 'specialty',
				                        'field' => 'term_id',
				                        'terms' => $queried_object->term_id,
				                        'operator' => 'IN'
			                        ],
			                        [
				                        'taxonomy' => 'article-type',
				                        'field' => 'slug',
				                        'terms' => 'clinical-guidelines',
				                        'operator' => 'IN'
			                        ]
		                        ],
		                        'post_type' => 'disease',
		                        'post_status' => 'publish',
		                        'nopaging' => true,
		                        'orderby' => $clinguide_orderby,
		                        'order' => $clinguide_order
	                        ];

	                        if ( ! empty($_GET['clinguide_age']) && $show_age_filter ) {
		                        $specialty_clinguide_args['tax_query'][] = [
			                        'taxonomy' => 'age',
			                        'field' => 'slug',
			                        'terms' => $_GET['clinguide_age'],
			                        'operator' => 'IN'
		                        ];
	                        }

	                        $specialty_clinguides = get_posts($specialty_clinguide_args);
	                        ?>

                            <details id="disease-clinguide-list"
		                        <?= empty($_GET['clinguide_order_by']) && empty($_GET['clinguide_age']) ? '' : 'open'; ?>
                                     style='margin-bottom:20px;<?= empty($specialty_clinguides) || ! current_user_can('manage_options') ? 'display:none;' : ''; ?>'>
                                <summary>Клинические рекомендации по специальности</summary>

                                <form action="<?= $_SERVER['REQUEST_URI']; ?>" method="get" class="list-filter">

			                        <?php if ( $show_age_filter ): ?>
                                        <label for="clinguide_age_any">
                                            <input type="radio" name="clinguide_age"
                                                   id="clinguide_age_any" <?= empty( $_GET['clinguide_age'] ) ? 'checked' : ''; ?> value="">
                                            Все
                                        </label>
				                        <?php foreach ( $age_terms as $age_term ): ?>
                                            <label for="clinguide_age_<?= $age_term->slug; ?>">
                                                <input type="radio" name="clinguide_age" id="clinguide_age_<?= $age_term->slug; ?>"
                                                       value="<?= $age_term->slug; ?>"
							                        <?= ( isset( $_GET['clinguide_age'] ) && $_GET['clinguide_age'] == $age_term->slug ) ? 'checked' : ''; ?>>
						                        <?= $age_term->name; ?>
                                            </label>
				                        <?php endforeach; ?>
			                        <?php endif; ?>

			                        <?php $order_by = $_GET['clinguide_order_by'] ?? 'title'; ?>
                                    <label for="clinguide_order_by">
                                        Сортировать по:
                                        <select name="clinguide_order_by">
                                            <option value="title" <?php selected($order_by, 'title'); ?>>Алфавит</option>
                                            <option value="modified" <?php selected($order_by, 'modified'); ?>>Дата обновления</option>
                                        </select>
                                    </label>
                                </form>

                                <hr>

                                <ul>
			                        <?php foreach ( $specialty_clinguides as $specialty_clinguide ): ?>
                                        <li>
                                            <a href="<?= get_permalink( $specialty_clinguide ); ?>"><?= $specialty_clinguide->post_title; ?></a>
                                        </li>
			                        <?php endforeach; ?>
                                </ul>
                            </details>


                        </main><!-- #main -->
                    </div><!-- #primary -->
                    <?php
                    if (themesflat_get_opt('sidebar_layout') == 'sidebar-left' || themesflat_get_opt('sidebar_layout') == 'sidebar-right') :
                        get_sidebar();
                    endif;
                    ?>
                </div><!-- /.wrap-content-area -->
            </div><!-- /.col-md-12 -->
        </div><!-- /.row -->
    </div>

    <script type="text/javascript">
        (function($) {

            // Изменили фильтры - скроллим до них
            const open_details = $("details[open]");
            if (open_details.length) {
                $([document.documentElement, document.body]).animate({
                    scrollTop: open_details.offset().top
                }, 400);
            }

            $('form.list-filter input, form.list-filter select').on('change', function () {
                $(this).closest('form.list-filter').submit();
            });

        })(jQuery);
    </script>

<?php get_footer(); ?>