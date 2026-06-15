<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

use MedviseSubscriptions\Specialty\Specialty;

defined('ABSPATH') || exit;

do_action('woocommerce_before_edit_account_form'); ?>

<form class="woocommerce-EditAccountForm edit-account" action=""
      method="post" <?php do_action('woocommerce_edit_account_form_tag'); ?> >

    <?php do_action('woocommerce_edit_account_form_start'); ?>

    <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
        <label for="account_first_name"><?php esc_html_e('First name', 'woocommerce'); ?>&nbsp;<span
                    class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name"
               id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr($user->first_name); ?>"/>
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
        <label for="account_last_name"><?php esc_html_e('Last name', 'woocommerce'); ?>&nbsp;<span
                    class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name"
               id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr($user->last_name); ?>"/>
    </p>
    <div class="clear"></div>

	<?php echo Medviselogin_EmailConfirmation::form_notice( $user->ID ); ?>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_email"><?php esc_html_e('Email address', 'woocommerce'); ?>&nbsp;<span
                    class="required">*</span></label>
        <input type="email" class="woocommerce-Input woocommerce-Input--email input-text ignore" name="account_email"
               id="account_email" autocomplete="email" value="<?php echo esc_attr($user->user_email); ?>"/>
    </p>
    <div class="clear"></div>

	<?php
	$allowedSpecialties = Specialty::get_allowed_specialties();
	$userSpecialty = MedUserProfile::get_user_specialty_id($user->ID);
	?>

	<?php if ( empty( $userSpecialty ) ): ?>
        <div style="margin-top:20px;line-height:1.2;" class="wc-block-components-notice-banner is-warning">
            Выберите вашу основную специальность.<br>
            Это не влияет на доступ к материалам сайта, но важно для нашего планирования.
        </div>
	<?php endif; ?>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_specialty">Специальность&nbsp;<span
                    class="required">*</span></label>
        <select id="account_specialty" name="account_specialty">
            <option value="">Выберите специальность</option>
            <?php
                foreach ($allowedSpecialties as $specialty) {
                    $specialtyTerm = get_term($specialty);
                    ?>
                    <option
                        value="<?php echo $specialtyTerm->term_id; ?>"
                        <?php selected($userSpecialty, $specialtyTerm->term_id); ?>>
                        <?php echo esc_html($specialtyTerm->name); ?>
                    </option>
                    <?php
                }
            ?>
            <option value="-1" <?php selected($userSpecialty, -1); ?>>Другая</option>
        </select>
    </p>

    <p id="account_other_specialty_wrapper" class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide" <?php echo $userSpecialty > -1 ? 'style="display: none;"' : '' ?>>
        <label for="account_other_specialty">Другая специальность<span
                    class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_other_specialty"
               id="account_other_specialty" value="<?php echo esc_attr(MedUserProfile::get_user_other_specialty($user->ID)); ?>"/>
    </p>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_specialty_age">Возрастная специализация
            <span class="required">*</span></label>
		<?php
		$age_terms = get_terms('age', [
			'hide_empty' => false,
		]);
		$userAgeSpecialty      = MedUserProfile::get_user_age_specialties( $user->ID );
		?>

	    <?php foreach ( $age_terms as $age_term ): ?>
            <label style="display:table;">
                <input type='checkbox' name='account_age_specialty[]' value='<?= $age_term->term_id; ?>'
				    <?= in_array( $age_term->term_id, $userAgeSpecialty ) ? 'checked' : ''; ?>
                       style="vertical-align: middle;margin-right: 5px;"/>
			    <?= $age_term->name; ?>
            </label>
	    <?php endforeach; ?>
    </p>

    <div class="clear"></div>

    <?php do_action('woocommerce_edit_account_form'); ?>

    <p>
        <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
        <button type="submit"
                class="woocommerce-Button button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>"
                name="save_account_details"
                value="<?php esc_attr_e('Save changes', 'woocommerce'); ?>"><?php esc_html_e('Save changes', 'woocommerce'); ?></button>
        <input type="hidden" name="action" value="save_account_details"/>
    </p>

    <?php do_action('woocommerce_edit_account_form_end'); ?>
</form>

<script>
    jQuery(($) => {
        const $specialtySelect = $('#account_specialty');
        const $otherSpecialtyWrapper = $('#account_other_specialty_wrapper');

        $specialtySelect.on('change', function(){
            const $this = $(this);

            if ($this.val() === '-1') {
                $otherSpecialtyWrapper.show();
            } else {
                $otherSpecialtyWrapper.hide();
            }
        });
    });

    jQuery(document).ready(function ($) {

        // Редирект
        const params = new URLSearchParams(window.location.search);
        const moveTo = params.get('move_to');
        const shouldRedirect = document.querySelector('.wc-block-components-notice-banner__content')?.textContent.includes('перенаправлены на другую страницу');
        if (moveTo && shouldRedirect) {
            window.setTimeout(function () {
                window.location.href = decodeURIComponent(moveTo);
            }, 4000);
        }

        // Хак с проверкой емейла - не проверяем при вводе, но проверяем при отправке формы, выставляя ignore динамически
        $.validator.addMethod('validateEmail', function (value, element, params) {

            var email_exist = false;

            $.ajax({
                async: false,
                type: 'POST',
                dataType: 'json',
                url: '<?= admin_url( 'admin-ajax.php' ); ?>',
                data: {
                    'action': 'email_check_exist',
                    'email': $('form.woocommerce-EditAccountForm input[name="account_email"]').val(),
                    'nonce': $('form.woocommerce-EditAccountForm input[name="save-account-details-nonce"]').val()
                },
                success: function (data) {
                    email_exist = data?.email_exists === true
                }
            });

            return ! email_exist;
        }, function (params, element) {
            return 'Данный Email уже зарегистрирован! Чтобы прикрепить его к текущему аккаунту:<br>' +
                '1. Выйдите из данного аккаунта<br>' +
                '2. Войдите в тот аккаунт, к которому этот Email уже прикреплен<br>' +
                '3. Удалите аккаунт, где прикреплен ваш Email' +
                '4. После этого снова войдите в текущий аккаунт и повторно прикрепите Email.';
        });

        $(".woocommerce-EditAccountForm").validate({
            ignore: ".ignore",
            rules: {
                "account_first_name": {
                    required: true,
                    minlength: 2
                },
                "account_last_name": {
                    required: true,
                    minlength: 2
                },
                "account_email": {
                    required: {
                        depends: function () {
                            $(this).val($.trim($(this).val()));
                            return true;
                        }
                    },
                    validateEmail: true
                },
                "account_specialty": "required",
                "account_age_specialty[]": "required",
            },
            messages: {
                "account_first_name": {
                    required: function() {
                        return "Введите свое имя.";
                    }
                },
                "account_last_name": {
                    required: function() {
                        return "Введите свою фамилию.";
                    }
                },
                "account_specialty": {
                    required: function() {
                        return "Укажите свою специальность.";
                    }
                },
                "account_age_specialty[]": {
                    required: function() {
                        return "Выберите возраст(а), на котором вы специализируетесь.";
                    }
                },
            },
            errorPlacement: function(error, element) {
                console.log(element.attr('name'));
                if ( element.attr('name') == 'account_age_specialty[]' ) {
                    error.insertAfter($('input[name="account_age_specialty\[\]"]').last().closest('label').get(0));
                } else {
                    error.insertAfter(element);
                }
            }
        });

        $(".woocommerce-EditAccountForm").submit(function (e) {
            $(".woocommerce-EditAccountForm").validate().settings.ignore = ":hidden";

            if ($(".woocommerce-EditAccountForm").valid()) {
                return true;
            } else {
                e.preventDefault();
                $(".woocommerce-EditAccountForm").validate().settings.ignore = ".ignore";
            }
        });
    });
</script>

<?php do_action('woocommerce_after_edit_account_form'); ?>
