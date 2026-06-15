<?php
/**
 * Email Styles
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-styles.php.
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
	exit;
}

?>
        h4 {
            text-align: left;
        }

        .headerLineTitle {
            width: 1.5in;
            display: inline-block;
            margin: 0in;
            margin-bottom: .0001pt;
            font-size: 11.0pt;
            font-family: "Calibri", "sans-serif";
            font-weight: bold;
        }

        .headerLineText {
            display: inline;
            margin: 0in;
            margin-bottom: .0001pt;
            font-size: 11.0pt;
            font-family: "Calibri", "sans-serif";
            font-weight: normal;
        }

        .pageHeader {
            font-size: 14.0pt;
            font-family: "Calibri", "sans-serif";
            font-weight: bold;
            visibility: hidden;
            display: none;
        }

        .em-font-Inter-SemiBold {
            font-family: Inter, sans-serif !important;
            font-weight: 600 !important;
        }

        .em-font-Inter-Bold, .em-font-Inter-Medium {
            font-family: Inter, sans-serif !important;
            font-weight: 700 !important;
        }

        .em-font-Inter-Light, .em-font-Inter-Regular {
            font-family: Inter, sans-serif !important;
            font-weight: 300 !important;
        }

        .em-font-Inter-Regular {
            font-weight: 400 !important;
        }

        @media only screen and (max-device-width: 660px), only screen and (max-width: 660px) {
            .em-mob-wrap.em-mob-wrap-cancel, .noresp-em-mob-wrap.em-mob-wrap-cancel {
                display: table-cell !important;
            }

            .em-narrow-table {
                width: 100% !important;
                max-width: 660px !important;
                min-width: 280px !important;
            }

            .em-mob-width-91perc {
                width: 91% !important;
                max-width: 91% !important;
            }

            .em-mob-height-auto {
                height: auto !important;
            }

            .em-mob-height-20px {
                height: 20px !important;
            }

            .em-mob-width-100perc {
                width: 100% !important;
                max-width: 100% !important;
            }

            .em-mob-wrap {
                display: block !important;
            }

            .em-mob-text-align-left {
                text-align: left !important;
            }

            .em-mob-font_size-25px {
                font-size: 25px !important;
            }

            .em-mob-width-auto {
                width: auto !important;
            }

            .em-mob-font_size-14px {
                font-size: 14px !important;
            }

            .em-mob-font_size-12px {
                font-size: 12px !important;
            }

            .em-mob-text_align-left {
                text-align: left !important;
            }

            .em-mob-width-25px {
                width: 25px !important;
                max-width: 25px !important;
                min-width: 25px !important;
            }

            .em-mob-text_align-center {
                text-align: center !important;
            }

            .em-mob-padding_top-30 {
                padding-top: 30px !important;
            }

            .em-mob-padding_bottom-30 {
                padding-bottom: 30px !important;
            }

            .em-mob-padding_top-20 {
                padding-top: 20px !important;
            }

            .em-mob-padding_right-20 {
                padding-right: 20px !important;
            }

            .em-mob-padding_bottom-20 {
                padding-bottom: 20px !important;
            }

            .em-mob-padding_left-20 {
                padding-left: 20px !important;
            }

            .em-mob-line_height-23px {
                line-height: 23px !important;
            }

            .em-mob-padding_right-0 {
                padding-right: 0 !important;
            }

            .em-mob-padding_left-0 {
                padding-left: 0 !important;
            }
        }

        @media print {
            .headerLineTitle {
                width: 1.5in;
                display: inline-block;
                margin: 0in;
                margin-bottom: .0001pt;
                font-size: 11.0pt;
                font-family: "Calibri", "sans-serif";
                font-weight: bold;
            }

            .headerLineText {
                display: inline;
                margin: 0in;
                margin-bottom: .0001pt;
                font-size: 11.0pt;
                font-family: "Calibri", "sans-serif";
                font-weight: normal;
            }

            .pageHeader {
                font-size: 14.0pt;
                font-family: "Calibri", "sans-serif";
                font-weight: bold;
                visibility: visible;
                display: block;
            }

        }
<?php
