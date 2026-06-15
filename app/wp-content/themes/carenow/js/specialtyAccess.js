(function ($) {

    var removeSpecialtyFromCart = function () {
        $(document).on('click', '.js-remove-single-specialty', function (event) {
            event.preventDefault();
            $button = $(this);

            $.ajax({
                type: "post",
                dataType: "json",
                url: woocommerce_params.ajax_url,
                data: {
                    action: "specialty_remove_from_cart_ajax",
                    term_id: $button.attr('data-specialty-id'),
                },
                success: function (msg) {
                    if (msg.success) {
                        $("[name='update_cart']").prop('disabled', false).trigger("click");
                    }
                }
            });
        });
    };

    var addSpecialtyToCart = function () {
        $(document).on('click', '.js-buy-specialty-access', function (event) {
            event.preventDefault();
            var button = $(this);

            if (!button.hasClass('_loading') && !button.hasClass('_added') && !button.hasClass('_disabled')) {

                button.addClass('_loading');
                button.html('Добавляем...');

                $.ajax({
                    type: "post",
                    dataType: "json",
                    url: woocommerce_params.ajax_url,
                    data: {
                        action: "specialty_add_to_cart_ajax",
                        term_id: button.attr('data-specialty-id'),
                        product_id: button.attr('data-product-id'),
                    },
                    success: function (msg) {
                        if (msg.success) {
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

    var switchSpecialtyVariant = function () {
        $(document).on('change', '.tariff-card__options input[type="radio"]', function () {

            const container = $(this).closest('.tariff-card');
            const productId = $(this).data('product-id');
            const specialtyId = container.data('specialty-id');
            const subscriptionOption = $(this).val();

            // Обновляем modal target у кнопки "Подробнее"
            let newModalTarget = '#specialty-modal-' + specialtyId + '-' + subscriptionOption;
            container.find('button[data-bs-target]').attr('data-bs-target', newModalTarget);

            // Меняем кнопку "Купить"
            const purchaseButton = container.find('a.button');
            const buttonClasses = [
                'button add_to_cart_button ajax_add_to_cart',
                'button js-buy-theme-pack'
            ];

            if ('onetime' === subscriptionOption) {
                purchaseButton.attr('class', buttonClasses[1]);
            } else {
                purchaseButton.attr('class', buttonClasses[0]);
            }

            purchaseButton.text('Оформить');
            purchaseButton.attr('data-product_id', productId);
        });
        $('.tariff-card__options input[type="radio"]:checked').trigger('change');
    };

    // Dom Ready
    $(function () {
        removeSpecialtyFromCart();
        addSpecialtyToCart();
        switchSpecialtyVariant();
    });
})(jQuery);