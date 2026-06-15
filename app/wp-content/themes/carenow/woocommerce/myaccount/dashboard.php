<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);

$user = wp_get_current_user();
$user_first_name = get_user_meta( $user->ID, 'billing_first_name', true );
$welcome_text = '';

if ( ! empty($user_first_name) ) {
    $welcome_text = $user_first_name . ", приветствуем вас на портале для врачей «Medvisement»!";
}
elseif ( ! empty($user->user_email) ) {
	$welcome_text = $user->user_email . ", приветствуем вас на портале для врачей «Medvisement»!";
}
else {
	$welcome_text = "Приветствуем вас на портале для врачей «Medvisement»! Пожалуйста, в первую очередь " .
                    "<a href='" . wc_get_endpoint_url( 'edit-account' ) .
                    "' target='_blank' style='font-weight: bold;'>заполните анкету</a>.";
}
?>

    <p>
        <?= $welcome_text; ?>
    </p>
    <ul style="list-style: square;">
        <li>
            <a href="<?= wc_get_endpoint_url( 'orders' ); ?>">Заказы</a> - раздел, где отображаются все ваши заказы и
            оплаты по ним.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'subscriptions' ); ?>">Подписки</a> - активные и прошлые подписки на
            сайте. До
            тех пор, пока у вас нет активной подписки - открытые статьи отображаются ниже.<br>
            После регистрации и прикрепления ранее не использованного Telegram аккаунта вам будет доступна 1 (одна)
            бесплатная
            статья на выбор.<br>
            Также вы можете оплатить доступ к некоторым статьям.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'edit-account' ); ?>">Анкета</a> - ваши персональные данные, обязательно для заполнения.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'telegram-tab' ); ?>">Telegram аккаунт</a> - прикрепленный Telegram аккаунт
            для быстрой авторизации.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'password-change' ); ?>">Пароль аккаунта</a> - пароль для входа по Email. Если вы
            забудете пароль, вы можете воспользоваться процедурой его восстановления.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'delete-account' ); ?>">Удалить аккаунт</a> - удаление аккаунта и ваших
            персональных данных.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'share-article' ); ?>">Статьи для других</a> -
            имея подписку, вы можете открыть какую-либо статью на сайте пользователю, у которого нет доступа.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'templates-tab' ); ?>">Список шаблонов</a> - ваши личные заметки к
            статьям.
        </li>
        <li>
            <a href="<?= wc_get_endpoint_url( 'tours' ); ?>">Инструкции по использованию сайта</a> - инструкции
            запускаются автоматически при первом посещении раздела сайта. При необходимости, вы можете запустить их
            повторно.
        </li>
    </ul>

<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_before_my_account' );

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
