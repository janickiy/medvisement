jQuery(document).ready(function ($) {
    var SubscriptionDiscountsSettings = function () {
        this.init = function () {
            this.prefix = 'subscription_discounts_';

            this.$showDiscountColumn = this.getRow('show_discount_column');

            this.$showDiscountColumn.on('change', (function () {
                if (this.$showDiscountColumn.is(':checked')) {
                    this.showRow(this.getRow('head_discount_text'));
                } else {
                    this.hideRow(this.getRow('head_discount_text'));
                }
            }).bind(this));

            this.$showDiscountColumn.trigger('change');
        };

        this.showRow = function (el) {
            el.closest('tr').show(100);
        };

        this.hideRow = function (el) {
            el.closest('tr').hide(100);
        };

        this.getRow = function (settingsName) {
            return $('[name=' + this.prefix + settingsName + ']');
        }
    };

    (new SubscriptionDiscountsSettings()).init();
});

