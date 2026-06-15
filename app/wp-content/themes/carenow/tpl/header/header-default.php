<?php 
$header_search_box = themesflat_get_opt('header_search_box');
$header_sidebar_toggler = themesflat_get_opt('header_sidebar_toggler');
?>
<header id="header" class="header header-default <?php if ( ! is_user_logged_in() ) { echo 'header--buttons-column'; } ?>">
    <div class="inner-header">  
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="header-wrap clearfix">
                        <div class="header-ct-left"><?php get_template_part( 'tpl/header/brand'); ?></div>
                        <div class="header-ct-center">
                            <?php get_template_part( 'tpl/header/navigator'); ?>

                            <?php if ( $header_search_box == 1 ) :?>
                            <div class="show-search">
                                <a href="#"><i class="carenow-icon-search-01"></i></a> 
                                <div class="submenu top-search widget_search">
                                    <?php get_search_form(); ?>
                                </div>        
                            </div> 
                            <?php endif;?>

                            <?php if ( $header_sidebar_toggler == 1 ) :?>
                            <div class="header-modal-menu-left-btn">
                                <div class="modal-menu-left-btn">
                                    <div class="line line--1"></div>
                                    <div class="line line--2"></div>
                                    <div class="line line--3"></div>
                                </div>
                            </div><!-- /.header-modal-menu-left-btn -->
                            <?php endif;?>

                            <div class="btn-menu">
                                <span class="line-1"></span>
                            </div><!-- //mobile menu button -->
                        </div>
                        <div class="tg-wp header-ct-right">
                            <?= do_shortcode('[medvise_loginbutton]'); ?>
                        </div>

                    </div>                
                </div><!-- /.col-md-12 -->
            </div><!-- /.row -->
        </div><!-- /.container -->
    </div>

    <div class="canvas-nav-wrap">
        <div class="overlay-canvas-nav"><div class="canvas-menu-close"><span></span></div></div>
        <div class="inner-canvas-nav">
            <?php get_template_part( 'tpl/header/brand-mobile'); ?>
            <nav id="mainnav_canvas" class="mainnav_canvas" role="navigation">
                <?php
                    wp_nav_menu( array( 'theme_location' => 'primary', 'fallback_cb' => 'themesflat_menu_fallback', 'container' => false ) );
                ?>
                <div class="tg-wp">
                    <?= do_shortcode('[medvise_loginbutton]'); ?>
                </div>
            </nav><!-- #mainnav_canvas -->  
        </div>
    </div><!-- /.canvas-nav-wrap --> 
</header><!-- /.header --> 