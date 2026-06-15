;(function($) {

    var removeArticleFromCart = function() {
        $(document).on('click', '.js-remove-single-article', function() {
            event.preventDefault();
            $button = $(this);
            $.ajax( {
                type: "post",
                dataType: "json",
                url: woocommerce_params.ajax_url,
                data: {
                    action: "article_remove_from_cart_ajax",
                    articleID: $button.attr('data-article-id'),
                },
                success: function(msg) {
                    if ( msg == '1' ) {
                        $("[name='update_cart']").prop('disabled', false).trigger("click");
                    }
                }
            });
        });
    };

    var addArticleToCart = function() {
        $('.js-buy-article-access').click(function(event) {
            event.preventDefault();
            var button = jQuery(this);

            if ( ! button.hasClass('_loading') && ! button.hasClass('_added') && ! button.hasClass('_disabled') ) {

                button.addClass('_loading');
                button.html('Добавляем...');

                jQuery.ajax( {
                    type: "post",
                    dataType: "json",
                    url: woocommerce_params.ajax_url,
                    data: {
                        action: "article_add_to_cart_ajax",
                        postID: button.attr('data-post-id'),
                    },
                    success: function(msg) {
                        if ( msg == '1' ) {
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
    removeArticleFromCart();
    addArticleToCart();
});
})(jQuery);