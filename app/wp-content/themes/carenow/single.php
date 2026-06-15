<?php
/**
 * The template for displaying all single posts.
 *
 * @package carenow
 */

get_header();

global $post;
the_post();

$med_article_files = carbon_get_the_post_meta('med_article_files');

use MedviseSubscriptions\Subscriber\Subscriber;
use MedviseSubscriptions\ThemePackAccess;
?>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div id="primary" class="content-area">

                    <main id="main" class="post-wrap" role="main">
                        <?php if ( 'disease' === $post->post_type ): ?>
                            <div class="text-end timer-wrapper">
                                <?php
                                // Счетчик для доступа к статье (тематические тарифы, покупка статей)
                                if (is_user_logged_in()) {
                                    $expiry_date = Subscriber::getArticleExpiryDate(
                                        get_current_user_id(),
                                        $post->ID
                                    );
                                    if ($expiry_date):
                                        ?>
                                        <div class="theme-pack-timer" data-expiry-date="<?= esc_attr($expiry_date); ?>">
                                            <span class="theme-pack-timer__label">Доступ истекает через:</span>
                                            <span class="theme-pack-timer__countdown"></span>
                                        </div>
                                    <?php endif;
                                }
                                ?>
	                            <?php medvise_render_share_article_button(); ?>
                            </div>
                        <?php else: ?>
                            <div class="text-end">
                                <?php medvise_render_share_article_button(); ?>
                            </div>
                        <?php endif; ?>

                        <h1 class="entry-title"><?php the_title(); ?></h1>

                        <?php if (Subscriber::hasAccess($post)): ?>

	                        <?= do_shortcode('[medvise_user_notes_templates_container]'); ?>

                            <?php get_template_part('content', 'single'); ?>

                            <?php if (in_array($post->post_type, ['substance', 'disease'])) : ?>

                                <?php if (!empty($med_article_files)): ?>
                                    <h6>Материалы статьи</h6>
                                    <ul class="entry-files">
                                        <?php foreach ($med_article_files as $med_article_file): ?>
                                            <li>
                                                <a href="<?= wp_get_attachment_url($med_article_file['file']); ?>"
                                                   target="_blank">
                                                    <?= empty($med_article_file['title']) ? basename(get_attached_file( $med_article_file['file'])) : $med_article_file['title']; ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

		                        <?php medvise_render_share_article_button(); ?>

		                        <?php get_template_part( 'tpl/authors' ); ?>

		                        <?= do_shortcode( "[medinfo_post_rating id='{$post->ID}']" ); ?>
		                        <?= do_shortcode( "[medinfo_post_report_form id='{$post->ID}']" ); ?>

                            <?php endif; ?>

                        <?php else: ?>

                            <article id="post-<?php the_ID(); ?>" <?php post_class( 'blog-post blog-single' ); ?>>
                                <!-- begin feature-post single  -->
		                        <?php get_template_part( 'tpl/feature-post-single' ); ?>
                                <!-- end feature-post single-->

		                        <?php get_template_part( 'tpl/entry-content/entry-content-meta' ); ?>

                                <div class="main-post">
                                    <div class="entry-content clearfix">
				                        <?= Subscriber::renderNoAccess( $post->ID ); ?>
                                    </div><!-- .entry-content -->
                                </div><!-- /.main-post -->
                            </article><!-- #post-## -->

                        <?php endif; ?>
                    </main><!-- #main -->
                </div><!-- #primary -->
                <?php
                if (themesflat_get_opt('sidebar_layout') == 'sidebar-left' || themesflat_get_opt('sidebar_layout') == 'sidebar-right') :
                    get_sidebar();
                endif;
                ?>
            </div><!-- /.col-md-12 -->
            <div class="col-md-12">
                <?php get_template_part('tpl/related-post') ?>
            </div><!-- /.col-md-12 -->
        </div><!-- /.row -->
    </div><!-- /.container -->

<?php medvise_render_share_article_modal(); ?>
<?php medvise_render_shared_article_modal(); ?>

<?php get_footer(); ?>