jQuery(document).ready(function ($) {

    var SubscriptionDiscounts = function () {

        this.settings = subscriptionDiscountsData.settings;
        this.currencyOptions = subscriptionDiscountsData.currency_options;
        this.productType = subscriptionDiscountsData.product_type;
        this.subscriptionsDiscountsSelector = '[data-discounts-for-subscriptions]';

        this.init = function () {

            if (this.settings !== undefined) {

                if (this.productType === 'variable-subscription' || this.productType === 'variable') {
                    $(".single_variation_wrap").on("show_variation", this.loadVariationTable.bind(this));

                    $(document).on('reset_data', function () {
                        $('[data-variation-discounts-for-subscriptions-table]').html('');
                    });
                }
            }
        };

        this.loadVariationTable = function (event, variation) {

            $.post(document.location.origin + document.location.pathname + '?wc-ajax=get_discounts_table', {
                variation_id: variation['variation_id'],
                nonce: subscriptionDiscountsData.load_table_nonce
            }, (function (response) {
                $('.discounts-for-subscriptions-table').remove();
                $('[data-variation-discounts-for-subscriptions-table]').html(response);
            }).bind(this));
        };

        this.getProductName = function () {
            return $(this.subscriptionsDiscountsSelector).data('product-name');
        }
    };

    document.subscriptionsDiscounts = new SubscriptionDiscounts();

    document.subscriptionsDiscounts.init();
});