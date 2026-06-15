jQuery(document).ready(function ($) {

    jQuery(document).on('click', '[data-add-new-discount-rule]', function (e) {
        e.preventDefault();

        var newRuleInputs = jQuery(e.target).parent().find('[data-price-rules-input-wrapper]').first().clone();

        jQuery('<span data-discounts-rules-container></span>').insertBefore(jQuery(e.target))
            .append(newRuleInputs)
            .append('<span class="notice-dismiss remove-discount-rule" data-remove-discount-rule style="vertical-align: middle"></span>')
            .append('<br><br>');

        newRuleInputs.children('input').val('');

        recalculateIndexes(jQuery(e.target).closest('[data-discounts-rules-wrapper]'));
    });

    jQuery('body').on('click', '.remove-discount-rule', function (e) {
        e.preventDefault();

        var element = jQuery(e.target.parentElement);
        var wrapper = element.parent('[data-discounts-rules-wrapper]');
        var containers = wrapper.find('[data-discounts-rules-container]');

        if ((containers.length) < 2) {
            containers.find('input').val('');
            return;
        }

        jQuery('[data-discounts-rules-wrapper] .wc_input_price').trigger('change');

        element.remove();

        recalculateIndexes(wrapper);
    });

    function recalculateIndexes(container) {

        var fieldsName = [
            'subscriptions_discounts_percent_quantity',
            'subscriptions_discounts_percent_discount',
            'subscriptions_discounts_fixed_quantity',
            'subscriptions_discounts_fixed_price'
        ];

        for (var key in fieldsName) {
            if (fieldsName.hasOwnProperty(key)) {
                var name = fieldsName[key];

                jQuery.each(jQuery(container.find('input[name^="' + name + '"]')), function (index, el) {
                    var currentName = jQuery(el).attr('name');

                    var newName = currentName.replace(/\[\d*\]$/, '[' + index + ']');

                    jQuery(el).attr('name', newName);
                });
            }
        }

    }

    var SubscriptionDiscountsRoleBasedBlock = function () {

        $(document).on('change', '[data-role-subscription-discounts-type-select]', function (e) {

            var $container = $(e.target).closest('div');

            $container.find('[data-role-subscription-discounts-type]').css('display', 'none');
            $container.find('[data-role-subscription-discounts-type-' + this.value + ']').css('display', 'block');
        });

        this.$block = null;
        this.initializedBlocks = [];

        this.init = function (id) {

            this.variationCanBeChangedAlreadyTriggered = false;
            this.id = id;
            this.$block = jQuery('#' + id);

            if (this.initializedBlocks[id] !== undefined) {
                this.unbindEvents();
            }

            this.bindEvents();

            this.initializedBlocks[id] = this;
        };

        this.bindEvents = function () {
            $('body').on('click', '#' + this.id + ' .dfws-role-based-role-action--delete', this.removeRole.bind(this));
            $('body').on('click', '#' + this.id + ' .dfws-role-based-role__header', this.toggleRoleView.bind(this));
            $('body').on('click', '#' + this.id + ' .dfws-role-based-adding-form__add-button', this.addRole.bind(this));
        }

        this.unbindEvents = function () {
            $('body').off('click', '#' + this.id + ' .dfws-role-based-role-action--delete');
            $('body').off('click', '#' + this.id + ' .dfws-role-based-role__header');
            $('body').off('click', '#' + this.id + ' .dfws-role-based-adding-form__add-button');
        }

        this.toggleRoleView = function (event) {

            var $element = $(event.target);

            if ($element.hasClass('dfws-role-based-role-action--delete')) {
                return;
            }

            var role = $element.closest('.dfws-role-based-role');

            if (role.data('visible')) {
                this.hideRole(role);
            } else {
                this.showRole(role);
            }
        };

        this.showRole = function ($role) {
            $role.find('.dfws-role-based-role__content').stop().slideDown(400);
            $role.find('.dfws-role-based-role__action-toggle-view')
                .removeClass('dfws-role-based-role__action-toggle-view--open')
                .addClass('dfws-role-based-role__action-toggle-view--close');

            $role.data('visible', true);
        };

        this.hideRole = function ($role) {
            $role.find('.dfws-role-based-role__content').stop().slideUp(400);
            $role.find('.dfws-role-based-role__action-toggle-view')
                .removeClass('dfws-role-based-role__action-toggle-view--close')
                .addClass('dfws-role-based-role__action-toggle-view--open');
            $role.data('visible', false);
        };

        this.removeRole = function (e) {
            e.preventDefault();

            if (confirm("Are you sure?")) {

                var $roleToRemove = $(e.target).closest('.dfws-role-based-role');
                var roleSlug = $roleToRemove.data('role-slug');

                this.$block.find('.dfws-role-based-adding-form__role-selector').append('<option value="' + roleSlug + '">' + $roleToRemove.data('role-name') + '</option>');
                this.$block.find('.subscriptions_discounts_rules_roles_to_delete').find('[value="' + roleSlug + '"]').prop('selected', true);

                $roleToRemove.slideUp(400, function () {
                    $roleToRemove.remove();
                });

                this.triggerVariationCanBeUpdated();
            }
        };

        this.block = function () {
            this.$block.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        };

        this.unblock = function () {
            this.$block.unblock();
        };

        this.addRole = function (event) {

            event.preventDefault();

            var selectedRole = this.$block.find('.dfws-role-based-adding-form__role-selector').val();

            if (selectedRole) {

                var action = this.$block.data('add-action');
                var nonce = this.$block.data('add-action-nonce');
                var productId = this.$block.data('product-id');
                var loop = this.$block.data('loop');

                $.ajax({
                    method: 'GET',
                    url: ajaxurl,
                    data: {
                        action: action,
                        nonce: nonce,
                        role: selectedRole,
                        product_id: productId,
                        loop: loop,
                    },
                    beforeSend: (function () {
                        this.block();
                    }).bind(this)
                }).done((function (response) {
                    if (response.success && response.role_row_html) {
                        this.$block.find('.dfws-role-based-roles').append(response.role_row_html);
                        this.$block.find('.dfws-role-based-no-roles').css('display', 'none');

                        $.each(this.$block.find('.dfws-role-based-role'), (function (i, el) {
                            this.hideRole($(el));
                        }).bind(this));

                        this.showRole(this.$block.find('.dfws-role-based-role').last());

                        this.$block.find('.dfws-role-based-adding-form__role-selector').find('[value="' + selectedRole + '"]').remove();
                        this.$block.find('.subscriptions_discounts_rules_roles_to_delete').find('[value="' + selectedRole + '"]').prop('selected', false);

                        $('.woocommerce-help-tip').tipTip({
                            'attribute': 'data-tip',
                            'fadeIn': 50,
                            'fadeOut': 50,
                            'delay': 200
                        });

                        this.triggerVariationCanBeUpdated();

                        purgeInputValidationEvent();

                    } else {
                        response.error_message && alert(response.error_message);
                    }
                    this.unblock();
                }).bind(this));
            }
        }

        this.triggerVariationCanBeUpdated = function () {

            if (!this.variationCanBeChangedAlreadyTriggered) {

                this.$block
                    .closest('.woocommerce_variation')
                    .addClass('variation-needs-update');

                jQuery('button.cancel-variation-changes, button.save-variation-changes').removeAttr('disabled');
                jQuery('#variable_product_options').trigger('woocommerce_variations_defaults_changed');

                this.variationCanBeChangedAlreadyTriggered = true;
            }

        }
    };

    jQuery.each($('.dfws-role-based-block'), function (i, el) {
        (new SubscriptionDiscountsRoleBasedBlock()).init(jQuery(el).attr('id'));
    });

    jQuery(document).on('woocommerce_variations_loaded', function ($) {
        jQuery.each(jQuery('.dfws-role-based-block'), function (i, el) {

            var $el = jQuery(el);

            if ($el.data('product-type') === 'variation') {
                (new SubscriptionDiscountsRoleBasedBlock()).init($el.attr('id'));
            }
        });

        purgeInputValidationEvent();
    });

    $(document).on('change', '.variable_roles_subscriptions_discounts_', function () {
        if ($(this).is(':checked')) {
            $(this).closest('.data').find('.show_if_variable_roles_subscriptions_discounts_').show();
        } else {
            $(this).closest('.data').find('.show_if_variable_roles_subscriptions_discounts_').hide();
        }
    });


    /** FIXES FOR WOOCOMMERCE SUBSCRIPTION BUG
     * https://github.com/Automattic/woocommerce-subscriptions-core/issues/127
     * **/

    $('body').on('woocommerce-product-type-change', function () {
        if ($('select#product-type').val() === 'simple') {
            setTimeout(function () {
                $('.show_if_simple').show();
            }, 10);
        }

        if ($('select#product-type').val() === 'bundle') {
            setTimeout(function () {
                $('.show_if_bundle').show();
            }, 10);
        }
    });

    function purgeInputValidationEvent() {

        var quantityFields = jQuery('[data-discounts-rules-wrapper] .price-quantity-rule');

        quantityFields.off('invalid');

        quantityFields.on('invalid', function (e) {
            e.target.setCustomValidity("The discounted period should start from the 2nd renewal. In case you need to set up discounts starting from 1st payment - use the \"sale price\" field.\n Read more in the \"1st payment discount\" section of the plugin documentation.");
        }).on('change', function (e) {
            if (parseInt($(this).val()) !== 1) {
                e.target.setCustomValidity('');
            }
        });
    };

    purgeInputValidationEvent();
});