<?php
/**
 * Email Footer
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-footer.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 7.4.0
 */

defined( 'ABSPATH' ) || exit;
?>

<tr em="block" class="em-structure">
    <td align="center" style="text-align: left; padding-top: 20px;"
        class="em-mob-padding_top-20 em-mob-padding_right-20 em-mob-padding_bottom-20 em-mob-padding_left-20">
        <table border="0" cellspacing="0" cellpadding="0" class="em-mob-width-100perc">
            <tr>
                <td width="280" valign="top" class="em-mob-wrap em-mob-width-100perc">
                    <table cellpadding="0" cellspacing="0" border="0" width="100%" em="atom">
                        <tr>
                            <td style="padding-bottom: 20px;" class="em-mob-padding_bottom-30">
                                <img src="<?= get_site_url(); ?>/wp-content/themes/carenow/images/email/logo.svg"
                                     height="40"
                                     border="0" alt=""
                                     style="display: inline-block; width: auto; height: 40px;">
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table border="0" cellspacing="0" cellpadding="0" class="em-mob-width-100perc">
            <tr>
                <td width="580" valign="middle" class="em-mob-wrap em-mob-width-100perc">

                    <table cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td>
                                <table cellpadding="0" cellspacing="0" border="0" width="100%"
                                       em="atom">
                                    <tr>
                                        <td style="padding-bottom: 5px;">
                                            <div style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 21px; color: #656565;"
                                                 class="em-font-Inter-Regular">©&nbsp;Medvisement 2024
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                <table cellpadding="0" cellspacing="0" border="0" width="100%"
                                       em="atom">
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <div style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 21px; color: #656565;"
                                                 class="em-font-Inter-Regular">
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                <table cellpadding="0" cellspacing="0" border="0" width="100%"
                                       em="atom">
                                    <tr>
                                        <td align="left" class="em-mob-text-align-left"
                                            style="padding-bottom: 20px;">
                                            <a href="https://medvisement.com/feedback/" target="_blank"
                                               style="font-family: -apple-system, 'Segoe UI', 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 21px; text-decoration: underline; color: #656565;">Поддержка</a>
                                            <span style="font-family: -apple-system, 'Segoe UI', 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 21px; color: #A3ADBB;">&nbsp; &nbsp; &nbsp;<a
                                                        href="mailto:info@medvisement.com" target="_blank"
                                                        style="text-decoration: underline solid; color: #656565;">info@medvisement.com</a></span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td>
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
</table>
</body>
</html>