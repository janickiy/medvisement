<?php
/**
 * The template part for displaying a message that posts cannot be found.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package carenow
 */
?>

<section class="no-results not-found">

    <div class="page-content">
        <?php if (is_search()) : ?>

            <p class="subtext-nothing">
                <?php if ( empty( $_GET['s'] ) ): ?>
                    Пожалуйста, введите поисковый запрос.
                <?php else: ?>
                    Совпадений не найдено. Попробуйте изменить поисковый запрос.
                <?php endif; ?>
            </p>

        <?php else : ?>

            <p class="subtext-nothing">
                Запрашиваемая страница не найдена. Пожалуйста, попробуйте воспользоваться поиском.
            </p>
            <aside class="widget widget_search">
                <?php get_search_form(); ?>
            </aside>

        <?php endif; ?>
    </div><!-- .page-content -->
</section><!-- .no-results -->