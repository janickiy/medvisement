<?php 
$style_header = themesflat_get_opt('style_header');
?>
<!-- Topbar -->
<?php if($style_header == 'header-03'): ?>
    <div class="themesflat-top">    
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="container-inside">
                        <div class="content-left">
                            <ul class="flat-information">
                                <?php if( themesflat_get_opt('topbar_address') != '' ): ?>
                                <li>
                                    <?php echo wp_kses(themesflat_get_opt('topbar_address_label'), themesflat_kses_allowed_html()); ?>
                                    <span><?php echo esc_attr(themesflat_get_opt('topbar_address')); ?></span>
                                </li>
                                <?php endif; ?>

                                <?php if( themesflat_get_opt('topbar_email') != '' ): ?>
                                <li>
                                    <?php echo wp_kses(themesflat_get_opt('topbar_email_label'), themesflat_kses_allowed_html()); ?>
                                    <span><?php echo esc_attr(themesflat_get_opt('topbar_email')); ?></span>
                                </li>
                                <?php endif; ?>

                                <?php if( themesflat_get_opt('topbar_time') != '' ): ?>
                                <li>
                                    <?php echo wp_kses(themesflat_get_opt('topbar_time_label'), themesflat_kses_allowed_html()); ?>
                                    <span><?php echo esc_attr(themesflat_get_opt('topbar_time')); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div><!-- content-left -->

                        <div class="content-right">
                            <div class="widget-topbar-menu">
                                <?php                            
                                wp_nav_menu( array( 'theme_location' => 'topbar', 'fallback_cb' => 'themesflat_menu_fallback', 'container' => false ) );
                                ?>                        
                            </div>
                        </div><!-- content-right -->
                    </div><!-- /.container-inside -->
                </div>
            </div>
        </div><!-- /.container -->        
    </div><!-- /.topbar -->
<?php else: ?>
    <div class="themesflat-top">    
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="container-inside">
                        <div class="content-left">
                            <ul class="flat-information">
                                <?php if( themesflat_get_opt('topbar_address') != '' ): ?>
                                <li>
                                    <?php echo wp_kses(themesflat_get_opt('topbar_address_label'), themesflat_kses_allowed_html()); ?>
                                    <span><?php echo esc_attr(themesflat_get_opt('topbar_address')); ?></span>
                                </li>
                                <?php endif; ?>

                                <?php if( themesflat_get_opt('topbar_email') != '' ): ?>
                                <li>
                                    <?php echo wp_kses(themesflat_get_opt('topbar_email_label'), themesflat_kses_allowed_html()); ?>
                                    <span><?php echo esc_attr(themesflat_get_opt('topbar_email')); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div><!-- content-left -->

                        <div class="content-right">

                        </div><!-- content-right -->
                    </div><!-- /.container-inside -->
                </div>
            </div>
        </div><!-- /.container -->        
    </div><!-- /.topbar -->
<?php endif; ?>