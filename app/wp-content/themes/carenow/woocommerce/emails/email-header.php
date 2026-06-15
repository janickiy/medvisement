<?php
/**
 * Email Header
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-header.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 7.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Подтверждение заказа</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400&display=swap"
          em-class="em-font-Inter-Regular">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,700&display=swap"
          em-class="em-font-Inter-Bold">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,600&display=swap"
          em-class="em-font-Inter-SemiBold">
</head>
<body style="margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1;">

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">

<table cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr em="group">
        <td align="center"
            style="padding-right: 0px; padding-bottom: 0px; padding-left: 0px; background-color: #ffffff; background-repeat: repeat;"
            bgcolor="#ffffff" class="em-mob-padding_right-0 em-mob-padding_left-0">
            <!--[if (gte mso 9)|(IE)]>
            <table cellpadding="0" cellspacing="0" border="0" width="660">
                <tr>
                    <td>
            <![endif]-->
            <table cellpadding="0" cellspacing="0" width="100%" border="0"
                   style="max-width: 660px; min-width: 660px; width: 660px;" class="em-narrow-table">
                <tr em="block" class="em-structure">
                    <td align="center" style="padding: 20px 40px;" bgcolor="#FFFFFF"
                        class="em-mob-padding_left-20 em-mob-padding_right-20">
                        <table border="0" cellspacing="0" cellpadding="0" class="em-mob-width-100perc">
                            <tr>
                                <td width="580" class="em-mob-wrap em-mob-wrap-cancel em-mob-width-auto">
                                    <table cellpadding="0" cellspacing="0" border="0" width="100%" em="atom">
                                        <tr>
                                            <td align="center">
                                                <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/logo.svg"
                                                     height="50"
                                                     border="0" alt=""
                                                     style="display: block; width: 100%; height: 50px;">
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <!--[if (gte mso 9)|(IE)]>
            </td></tr></table>
            <![endif]-->
        </td>
    </tr>
    <tr em="group">
        <td align="center" bgcolor="#3888C6" style="background-color: #3888C6; padding: 40px 0;"
            class="em-mob-padding_top-20 em-mob-padding_right-20 em-mob-padding_bottom-20 em-mob-padding_left-20">
            <!--[if (gte mso 9)|(IE)]>
            <table cellpadding="0" cellspacing="0" border="0" width="660">
                <tr>
                    <td>
            <![endif]-->
            <table cellpadding="0" cellspacing="0" width="100%" border="0"
                   style="max-width: 660px; min-width: 660px; width: 660px;" class="em-narrow-table">
                <tr em="block" class="em-structure">
                    <td align="center"
                        class="em-mob-padding_top-20 em-mob-padding_right-20 em-mob-padding_bottom-20 em-mob-padding_left-20">
                        <table align="center" border="0" cellspacing="0" cellpadding="0" class="em-mob-width-100perc">
                            <tr>
                                <td width="580" valign="top" class="em-mob-wrap em-mob-width-100perc">
                                    <table cellpadding="0" cellspacing="0" border="0" width="100%" em="atom">
                                        <tr>
                                            <td align="center">
                                                <?php if( 'customer_completed_order' === $email_id ): ?>
                                                <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/cart.svg"
                                                     width="80"
                                                     border="0" alt=""
                                                     style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
	                                            <?php elseif( 'customer-invoice' === $email_id ): ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/invoice-wait.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
	                                            <?php elseif( 'customer_refunded_order' === $email_id ): ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/refund.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
                                                <?php elseif( 'customer_invoice' === $email_id ): ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/invoice-wait.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
	                                            <?php elseif( 'customer_completed_renewal_order' === $email_id ): ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/invoice-success.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
                                                <?php elseif( 'customer_renewal_invoice' === $email_id ): ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/invoice-wait.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
                                                <?php elseif( 'customer_payment_retry' === $email_id ): ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/invoice-warning.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
                                                <?php else: ?>
                                                    <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/confetti.svg"
                                                         width="80"
                                                         border="0" alt=""
                                                         style="display: block; width: 100%; max-width: 80px; box-shadow: 0px 0px 0px 0px;">
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                    <table cellpadding="0" cellspacing="0" border="0" width="100%" em="atom">
                                        <tr>
                                            <td style="padding: 20px 0 10px;">
                                                <div style="font-family: Helvetica, Arial, sans-serif; font-size: 20px; line-height: 1.2; color: #ffffff;"
                                                     class="em-font-Inter-SemiBold em-mob-font_size-25px"
                                                     align="center"><?php echo esc_html( $email_heading ); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
	                                <?php if ( in_array( $email_id, [ 'customer_completed_order' ] ) ): ?>
                                        <table cellpadding="0" cellspacing="0" border="0" width="100%" em="atom">
                                            <tr>
                                                <td>
                                                    <div style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; line-height: 21px; color: #ffffff;"
                                                         align="center" class="em-font-Inter-Regular">
                                                        Спасибо за вклад в развитие русскоязычного медицинского портала!<br>
                                                        Мы ценим ваше доверие.
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
	                                <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <!--[if (gte mso 9)|(IE)]>
            </td></tr></table>
            <![endif]-->
        </td>
    </tr>

    <tr em="group">
        <td align="center" bgcolor="#ffffff" style="background-color: #ffffff; padding-right: 40px; padding-left: 40px;"
            class="em-mob-padding_right-20 em-mob-padding_left-20">
            <!--[if (gte mso 9)|(IE)]>
            <table cellpadding="0" cellspacing="0" border="0" width="660">
                <tr>
                    <td>
            <![endif]-->
            <table cellpadding="0" cellspacing="0" width="100%" border="0"
                   style="max-width: 660px; min-width: 660px; width: 660px;" class="em-narrow-table">