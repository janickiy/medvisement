<?php
/**
 * Template Name: Специальности
 */
get_header();
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="primary" class="content-area">
                <main id="main" class="post-wrap" role="main">

                    <h1 class="entry-title"><?php the_title(); ?></h1>

                    <?php //echo do_shortcode('[medvise_tree_taxonomy type="disease" node="0"]'); ?>

                    <h5>
                        Нажмите на иконку нужной специальности, чтоб посмотреть какие статьи и заболевания имеются в
                        наличии по выбранной теме
                    </h5>

	                <?php the_content(); ?>

                    <br>
                    <?php
                    $specialty_terms = get_terms([
                        'taxonomy' => 'specialty',
                        'hide_empty' => false,
                        'meta_key' => 'tax_position',
                        'orderby' => 'tax_position'
                    ]);
                    ?>

                    <div class="category-container">
                        <?php foreach ($specialty_terms as $specialty_term): ?>
                            <a href="<?= get_term_link($specialty_term); ?>">
                                <figure class="category-item">

                                    <?php $specialty_image = wp_get_attachment_image_url(get_term_meta($specialty_term->term_id, '_image', true), 'full'); ?>

                                    <?php if ($specialty_image): ?>

                                        <img src="<?= $specialty_image; ?>">
                                    <?php endif; ?>

                                    <h4><?= $specialty_term->name; ?></h4>
                                </figure>
                            </a>
                        <?php endforeach; ?>
                    </div>

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
