<div class="modal-menu-left">
    <div class="modal-menu__backdrop"></div>
    <div class="modal-menu__body">
        <button class="modal-menu__close" type="button">
            <i class="carenow-icon-cancel"></i>
        </button>
        <div class="modal-menu__panel">
            <div class="modal-menu__panel-header">
                <div class="modal-menu__panel-title">
                </div>
            </div>
            <div class="modal-menu__panel-body">
                <div class="nav-wrap-secondary">
                    <?php dynamic_sidebar('aside-toggler-sidebar'); ?>
                </div><!-- /.nav-wrap -->
            </div>
            <div class="modal-menu__panel-footer">
                <div class="logo-panel">
                    <a href="<?php echo esc_url(home_url('/')); ?>" title="<?php bloginfo('name'); ?>">
                        <img class="site-logo" src="<?= THEMESFLAT_LINK . "images/logo.svg?v=3" ?>"
                             alt="<?php bloginfo('name'); ?>"/>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>  