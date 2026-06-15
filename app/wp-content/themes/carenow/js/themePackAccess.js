;(function($) {

    var addThemePackToCart = function() {
        $(document).on('click', '.js-buy-theme-pack', function( event ) {
            event.preventDefault();
            var button = $(this);

            if ( ! button.hasClass('_loading') && ! button.hasClass('_added') && ! button.hasClass('_disabled') ) {

                button.addClass('_loading');
                button.html('Добавляем...');

                $.ajax( {
                    type: "post",
                    dataType: "json",
                    url: woocommerce_params.ajax_url,
                    data: {
                        action: "theme_pack_add_to_cart_ajax",
                        product_id: button.attr('data-product-id'),
                    },
                    success: function(msg) {
                        if ( msg.success ) {
                            button.removeClass('_loading');
                            button.addClass('_added').addClass('_disabled');
                            button.html('В корзине');
                            button.after('<a href="/cart" class="added_to_cart wc-forward" title="Просмотр корзины">Просмотр корзины</a>');
                        } else {
                            button.removeClass('_loading');
                            button.addClass('_disabled');
                            button.html('Уже в корзине');
                        }
                    }
                });

            }
        });
    };

// Dom Ready
$(function() {
    addThemePackToCart();
});
})(jQuery);
