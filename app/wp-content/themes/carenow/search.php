<?php
/**
 * The template for displaying search results pages.
 *
 * @package carenow
 */



if ( ! empty($_GET['specialties']) ) {
	$specialty_terms = get_terms( 'specialty', [
		'include'    => $_GET['specialties'],
		'hide_empty' => false,
	] );
	$specialties_map = wp_list_pluck( $specialty_terms, 'name', 'term_id' );

    $search_title = "Раздел: " . implode(', ', $specialties_map);
}

get_header(); ?>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="wrap-content-area clearfix">

                    <div id="primary" class="content-area">
                        <main id="main"
                              class="post-wrap <?php echo esc_attr(themesflat_get_opt('blog_archive_layout')); ?>"
                              role="main">

	                        <?php if ( isset( $search_title ) ): ?>
                                <h1 style="text-align: center"><?= $search_title; ?></h1>
	                        <?php endif; ?>

                            <?php do_action('ep_suggestions'); ?>

                            <?php get_search_form(); ?>
                        </main><!-- #main -->
                    </div><!-- #primary -->

                    <div class="content-area" style="margin-top: 20px;">
                        <h2 class="entry-title" style="text-align: left;">Результаты поиска</h2>
	                    <?php if (have_posts()) : ?>
		                    <?php while (have_posts()) : the_post(); ?>

                            <div class="search-result_item">
                                <h3 class="search-result_title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>
                                <div class="search-result_text">
		                            <?php if ( is_plugin_active( 'elasticpress/elasticpress.php' ) ): ?>
			                            <?php the_content(); ?>
		                            <?php else: ?>
                                        <?php the_excerpt(); ?>
		                            <?php endif; ?>
                                </div>
                                <a href="<?php the_permalink(); ?>"  class="search-result_more">Подробнее</a>
                            </div>

		                    <?php endwhile; ?>
		                    <?php get_template_part( 'tpl/pagination' ); ?>
	                    <?php else : ?>
		                    <?php get_template_part('content', 'none'); ?>
	                    <?php endif; ?>
                    </div>

                </div><!-- /.wrap-content-area -->
            </div><!-- /.col-md-12 -->
        </div>
    </div>
<?php get_footer(); ?>