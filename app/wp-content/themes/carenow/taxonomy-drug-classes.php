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
$columns =  themesflat_get_opt('blog_grid_columns') ;

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
$themesflat_thumbnail = apply_filters('themesflat/template/themesflat_thumbnail',$themesflat_thumbnail);
$class = array('blog-archive');
$class[] = 'archive-'.get_post_type();
$class[] = $blog_layout;
$class[] =  $class_names[$columns];

$class = apply_filters('themesflat/template/blog_class',$class);

$queried_object = get_queried_object();
?>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="wrap-content-area clearfix">
                    <div id="primary" class="content-area">
                        <main id="main" class="post-wrap <?php echo esc_attr(themesflat_get_opt('blog_archive_layout')); ?>" role="main">

                            <h1 class="entry-title"><?= get_the_archive_title(); ?></h1>

                            <?php
                            $drug_class = get_terms([
                                'taxonomy' => 'drug-classes',
                                'hide_empty' => false,
                                'parent' => $queried_object->term_id
                            ]);
                            ?>

                            <div class="category-container">
                                <?php foreach ($drug_class as $drug_class_term): ?>
                                    <a href="<?= get_term_link($drug_class_term); ?>">
                                        <figure class="category-item">

                                            <?php $drug_class_image = wp_get_attachment_image_url(get_term_meta($drug_class_term->term_id, '_image', true), 'full'); ?>

                                            <?php if ($drug_class_image): ?>
                                                <img src="<?= $drug_class_image; ?>">
                                            <?php endif; ?>

                                            <h4><?= $drug_class_term->name; ?></h4>
                                        </figure>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <p>
                                Если какого-либо препарата нет в списке или вы обнаружили какую-то техническую или медицинскую ошибку,
                                напишите нам на почту: <a href="mailto:info@medvisement.com">info@medvisement.com</a>
                            </p>

                            <br>

                            <?= get_the_archive_description(); ?>

                            <br>

                            <?php
                            $specialty_posts = get_posts([
                                'posts_per_page' => -1,
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'specialty',
                                        'field' => 'term_id',
                                        'terms' => $queried_object->term_id,
                                        'operator' => 'IN'
                                    ]
                                ],
                                'post_type' => 'disease',
                                'post_status' => 'publish',
                                'nopaging' => true,
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ]);
                            ?>

                            <ul>
                                <?php foreach ($specialty_posts as $specialty_post): ?>
                                    <li><a href="<?= get_permalink($specialty_post); ?>"><?= $specialty_post->post_title; ?></a></li>
                                <?php endforeach; ?>
                            </ul>

                        </main><!-- #main -->
                    </div><!-- #primary -->
                    <?php
                    if ( themesflat_get_opt( 'sidebar_layout' ) == 'sidebar-left' || themesflat_get_opt( 'sidebar_layout' ) == 'sidebar-right' ) :
                        get_sidebar();
                    endif;
                    ?>
                </div><!-- /.wrap-content-area -->
            </div><!-- /.col-md-12 -->
        </div><!-- /.row -->
    </div>

<?php get_footer(); ?>