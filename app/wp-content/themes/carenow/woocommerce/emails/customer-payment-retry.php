<?php
/**
 * Customer payment retry email
 *
 * @author  Prospress
 * @package WooCommerce_Subscriptions/Templates/Emails
 * @version 1.0.0 - Migrated from WooCommerce Subscriptions v2.6.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

    <div style="text-align: left">
        <p>
            Здравствуйте, <?= esc_html( $order->get_billing_first_name() ); ?>.<br>
            Автоматическая оплата за пользование сайтом Medvisement.com не удалась. <br><br>
            Если вы не отменяли подписку, то причина может быть в следующем:<br>
        </p>
        <ul>
            <li style="padding-bottom: 10px;">Нехватка средств на карте: в таком случае пополните счёт и попробуйте произвести оплату перейдя
                <a href="<?= esc_url( $order->get_checkout_payment_url() ); ?>" target="_blank">по ссылке</a> или
                дождитесь следующей попытки списания
            </li>

            <li>Первая оплата была выполнена через YandexPay или SberPay: в таком случае вы можете
                <a href="<?= esc_url( $order->get_checkout_payment_url() ); ?>" target="_blank">произвести оплату
                    вручную</a>.
                Или найдите заказ, ожидающий платежа в <a href="<?= wc_get_account_endpoint_url( 'orders' ); ?>"
                                                          target="_blank">личном кабинете</a>
                и нажмите кнопку "оплатить". Оплату необходимо произвести через
                банковскую карту или MirPay.
            </li>
        </ul>
    </div>

<?php
do_action( 'woocommerce_subscriptions_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_footer', $email );
