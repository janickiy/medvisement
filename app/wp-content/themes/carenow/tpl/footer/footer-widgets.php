<?php ?>
<footer id="footer" class="footer footer-style1">
    <div class="footer-widgets">
        <div class="container">
            <div class="row">
                <?php
                $footer_widget_areas = themesflat_get_opt('footer_widget_areas');
                $columns = [4, 4, 4];
                $key = 0;
                foreach ($columns as $key => $column) {
                    $key = $key + 1;
                    ?>
                    <div class="col-lg-<?php themesflat_esc_attr($column); ?> col-sm-12 widgets-areas widgets-areas-<?php themesflat_esc_attr($key); ?>">
                        <div class="wrap-widgets wrap-widgets-<?php themesflat_esc_attr($key); ?>">
                            <?php
                            $widget = themesflat_get_opt("footer" . $key);
                            themesflat_dynamic_sidebar($widget);
                            ?>
                        </div>
                    </div>
                <?php } ?>
            </div><!-- /.row -->
        </div><!-- /.container -->
    </div><!-- /.footer-widgets -->
</footer>