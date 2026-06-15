<?php
/**
 * Template Name: Группы Лекарственных Средств
 */
get_header();
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div id="primary" class="content-area">
                <main id="main" class="post-wrap" role="main">

                    <h1 class="entry-title"><?php the_title(); ?></h1>

	                <?= do_shortcode('[medvise_tree_taxonomy type="substance" node="0"]'); ?>

	                <?php the_content(); ?>

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
