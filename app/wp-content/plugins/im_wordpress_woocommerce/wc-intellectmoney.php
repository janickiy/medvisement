<?php
/*
  Plugin Name: Платежный модуль IntellectMoney
  Plugin URI:
  Description: IntellectMoney — это универсальное решение по приему платежей в интернете. Мы работаем на рынке онлайн-платежей более 10 лет. За это время нас выбрали более 8 000 компаний. Среди них как российские, так и иностранные компании.
  Version: 6.4.5
  Author: IntellectMoney
  Author URI: https://intellectmoney.ru
 */
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

// Убеждаемся, что скрипт был запущен в контексте WP.
if (!defined('ABSPATH'))
    exit;

define('__IM_INCLUDE_PATH__', 'IntellectMoney/');
require_once __IM_INCLUDE_PATH__ . 'UserSettings.php';
require_once __IM_INCLUDE_PATH__ . 'Customer.php';
require_once __IM_INCLUDE_PATH__ . 'Order.php';
require_once __IM_INCLUDE_PATH__ . 'MerchantReceiptHelper.php';
require_once __IM_INCLUDE_PATH__ . 'Payment.php';
require_once __IM_INCLUDE_PATH__ . 'Result.php';
require_once __IM_INCLUDE_PATH__ . 'Currency.php';

add_action('plugins_loaded', 'woocommerce_intellectmoney', 0);
date_default_timezone_set('Europe/Moscow');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of Im_Custom_Gateway_Blocks
            $payment_method_registry->register( new Intellectmoney_Blocks );
        }
    );
}

// Переопределяем метод сохранения настроек платежного модуля
function process_admin_options() {
    // Получаем все POST-данные
    $post_data = $this->get_post_data();
    foreach ($this->form_fields as $key => $field) {
        if ('title' !== $this->get_field_type($field)) {
            try {
                if ($key == 'im_secretKey') {
                    // Обрабатываем im_secretKey отдельно — без экранирования
                    $secret_key_key = $this->plugin_id . $this->id . '_im_secretKey';
                    if (isset($_POST[$secret_key_key])) {
                        // Берём значение "как есть", убираем слеши, но не чистим строку
                        $raw_secret_key = stripslashes($_POST[$secret_key_key]);
                        $this->settings['im_secretKey'] = $raw_secret_key;
                    }
                } else {
                    $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                }
            } catch (Exception $e) {
                // Игнорируем ошибки
            }
        }
    }

    // Сохраняем настройки в базу данных
    return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));
}

function woocommerce_intellectmoney() {
    if (!class_exists('WC_Payment_Gateway'))
        return;
    if (class_exists('WC_IntellectMoney'))
        return;

    class WC_IntellectMoney extends WC_Payment_Gateway {
        public function __construct() {
            $woocommerce_currency = get_option('woocommerce_currency');
             if (in_array($woocommerce_currency, array('RUB', 'TST', 'USD', 'EUR'))) {
                $this->currency = $woocommerce_currency;
            }

            $plugin_dir = plugin_dir_url(__FILE__);

            $this->id = 'intellectmoney';
            $this->icon = apply_filters('woocommerce_intellectmoney_icon', '' . $plugin_dir . 'logo.png');
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();
            
            $moduleDescription = $this->get_option('im_description');
            if(empty($moduleDescription)) {
                $moduleDescription = 'Оплата с помощью пластиковых карт Visa/Mastercard, СбербанкОнлайн, Яндекс.Деньги, Webmoney, Деньги@Mail.ru, терминалы оплаты, банковский перевод, почта России и т.д.';
                $this->update_option('im_description', $moduleDescription);
            } 
            $this->method_description = __($this->get_option('im_description'), 'woocommerce');
            // Define user set variables  
            $this->title = $this->get_option('im_name');
            $this->description = $this->get_option('im_description');
			$this->im_method_title = __( 'IntellectMoney', 'woocommerce' );
            $this->im_settings = $this->init_im_settings();

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        function is_valid_for_use() {
            if (!in_array(get_option('woocommerce_currency'), array('RUB', 'EUR', 'USD', 'TST'))) {
                return false;
            }
            return true;
        }

        public function admin_options() {
            ?>
            <h3><?php _e('Платежный модуль IntellectMoney', 'woocommerce'); ?></h3>
            <p><?php _e('Настройка модуля оплаты IntellectMoney', 'woocommerce'); ?></p>

            <?php if ($this->is_valid_for_use()) : ?>
                <table class="form-table">

                    <?php
                    $this->generate_settings_html();
                    ?>
                </table>
                <script>
                    var el = document.getElementById('woocommerce_intellectmoney_im_resultUrl').setAttribute('disabled', 'disabled');
                </script>
            <?php else : ?>
                <div class="inline error"><p><strong><?php _e('Способ оплаты отключен', 'woocommerce'); ?></strong>: <?php _e('Модуль оплаты не поддерживает валюты Вашего магазина.', 'woocommerce'); ?></p></div>
            <?php
            endif;
        }

        function init_form_fields() {
            $statuses = wc_get_order_statuses();
            $nds = array(
                '1' => 'НДС 20%',
                '11' => 'НДС 22%',
                '2' => 'НДС 10%',
                '3' => 'НДС расч. 20/120',
                '12' => 'НДС расч. 22/122',
                '4' => 'НДС расч. 10/110',
                '5' => 'НДС 0%',
                '6' => 'НДС не облагается',
                '7' => 'НДС 5%',
                '8' => 'НДС 7%',
                '9' => 'НДС расч. 5/105',
                '10' => 'НДС расч. 7/107'
            );
            $integrationMethod = array(
                'Default' => 'По умолчанию',
                'P2P' => 'P2P',
            );

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Включить/Выключить', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'default' => 'yes'
                ),
                'im_name' => array(
                    'title' => __('Название', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Название способа оплаты, которое будет отображаться при выборе', 'woocommerce'),
                    'default' => 'Оплата через IntellectMoney',
                    'placeholder' => 'Оплата через IntellectMoney',
                    'css' => 'width: 500px;'
                ),
                'im_description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Описание способа оплаты, которое будет отображаться при выборе', 'woocommerce'),
                    'css' => 'width: 500px;'
                ),
                'im_integrationMethod' => array(
                    'title' => __('Метод интеграции', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Выберите метод интеграции', 'woocommerce'),
                    'options' => $integrationMethod,
                    'css' => 'width: 280px;'
                ),
                'im_formId' => array(
                    'title' => __('Номер формы в системе IntellectMoney', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Номер формы для P2P нужно скопировать из формы для приема платежей в личном кабинете IntellectMoney', 'woocommerce'),
                    'placeholder' => '7111',
                    'css' => 'width: 500px;'
                ),
                'im_accountId' => array(
                    'title' => __('Номер аккаунта в системе IntellectMoney', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Номер аккаунта для P2P нужно скопировать из формы для приема платежей в личном кабинете IntellectMoney', 'woocommerce'),
                    'placeholder' => '1000111111',
                    'css' => 'width: 500px;'
                ),
                'im_eshopId' => array(
                    'title' => __('Номер магазина в системе IntellectMoney', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Номер магазина нужно скопировать из личного кабинета IntellectMoney', 'woocommerce'),
                    'placeholder' => '459999',
                    'css' => 'width: 500px;'
                ),
                'im_secretKey' => array(
                    'title' => __('Секретный ключ в системе IntellectMoney', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Укажите секретный ключ, такой же, который вы указали в личном кабинете IntellectMoney', 'woocommerce'),
                    'placeholder' => 'mySecretKey',
                    'css' => 'width: 500px;'
                ),
                'im_resultUrl' => array(
                    'title' => __('Result URL', 'woocommerce'),
                    'type' => 'label',
                    'description' => __('Скопируйте данный адрес в личный кабинет IntellectMoney в настройки магазина', 'woocommerce'),
                    'default' => get_site_url() . '/?wc-api=wc_intellectmoney&return=result_url',
                    'placeholder' => get_site_url() . '/?wc-api=wc_intellectmoney&return=result_url',
                    'css' => 'width: 500px;',
                ),
                'im_test' => array(
                    'title' => __('Тестовый режим', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'description' => __('Включите данный режим при тестировании', 'woocommerce'),
                    'default' => 'no'
                ),
                'im_hold' => array(
                    'title' => __('Режим холдирования', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'description' => __('Включите данный режим, если хотите холдировать денежные средства'),
                    'default' => 'no'
                ),
                'im_holdTime' => array(
                    'title' => __('Время холдирования в часах', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Данный параметр относится к режиму холдирования. Укажите количество часов, по истечению которых денежные средства автоматически зачислятся на ваш счет, либо будут возвращены клиенту. Максимум 119 часов.', 'woocommerce'),
                    'default' => '119',
                    'css' => 'width: 50px;'
                ),
                'im_expireDate' => array(
                    'title' => __('Время существования счета', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Укажите сколько часов будет сущестовать счет(будет возможность его оплатить).', 'woocommerce'),
                    'default' => '24',
                    'css' => 'width: 50px;'
                ),
                'im_group' => array(
                    'title' => __('Группа устройств', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Укажите группу устройств для онлайн кассы', 'woocommerce'),
                    'placeholder' => 'Группа устройств',
                    'css' => 'width: 500px;'
                ),
                'im_inn' => array(
                    'title' => __('ИНН', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Укажите ИНН, такой же, который вы указали в личном кабинете IntellectMoney', 'woocommerce'),
                    'placeholder' => 'ИНН',
                    'css' => 'width: 500px;'
                ),
                'im_tax' => array(
                    'title' => __('Ставка НДС для товара', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Выберите ставку НДС для товара', 'woocommerce'),
                    'options' => $nds,
                    'css' => 'width: 280px;'
                ),
                'im_deliveryTax' => array(
                    'title' => __('Ставка НДС для доставки', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Выберите ставку НДС для доставки', 'woocommerce'),
                    'options' => $nds,
                    'css' => 'width: 280px;'
                ),
                'im_statusCreated' => array(
                    'title' => __('Статус заказа при создании СКО', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Выберите статус для созданного заказа', 'woocommerce'),
                    'options' => $statuses,
                    'css' => 'width: 180px;'
                ),
                'im_statusPaid' => array(
                    'title' => __('Статус заказа при полной оплате СКО', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Выберите статус для оплаченного заказа', 'woocommerce'),
                    'options' => $statuses,
                    'css' => 'width: 180px;'
                ),
                'im_preference' => array(
                    'title' => __('Доступные способы оплаты', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('По умолчанию доступны все способы оплаты. Оставьте данное поле пустым, если не знаете что указать', 'woocommerce'),
                    'default' => '',
                    'placeholder' => 'bankcard, sberbank, yandex, webmoney, terminals, alfaclick',
                    'css' => 'width: 500px;'
                ),
                'im_successUrl' => array(
                    'title' => __('Адрес при успешной оплате', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Адрес, на который будет перенаправлен клиент после успешной оплаты. Можно оставить по-умолчанию', 'woocommerce'),
                    'default' => get_site_url() . '/?wc-api=wc_intellectmoney&return=success_url',
                    'placeholder' => get_site_url() . '/?wc-api=wc_intellectmoney&return=success_url',
                    'css' => 'width: 500px;'
                ),
            );
        }

        function init_im_settings() {
            $settings = PaySystem\UserSettings::getInstance([
                'eshopId' => (string) $this->get_option('im_eshopId'), 
                'integrationMethod' => (string) $this->get_option('im_integrationMethod'), 
                'formId' => $this->get_option('im_formId'),
                'accountId' => $this->get_option('im_accountId'),
                'testMode' => $this->get_option('im_test') == 'yes' ? true : false, 
                'secretKey' => (string) $this->get_option('im_secretKey'), 
                'holdMode' => (string) $this->get_option('im_hold') == 'yes' ? true : false, 
                'holdTime' => (string) $this->get_option('im_holdTime'), 
                'group' => (string) $this->get_option('im_group'), 
                'tax' => (string) $this->get_option('im_tax'), 
                'deliveryTax' => (string) $this->get_option('im_deliveryTax'),
                'inn' => $this->get_option('im_inn'), 
                'preference' => (string) $this->get_option('im_preference'), 
                'successUrl' => (string) $this->get_option('im_successUrl'), 
                'expireDate' => (string) $this->get_option('im_expireDate'), 
                'statusCreated' => (string) $this->get_option('im_statusCreated'), 
                'statusCancelled' => 'cancelled', 
                'statusPaid' => (string) $this->get_option('im_statusPaid'), 
                'statusHolded' => (string) $this->get_option('im_statusPaid'), 
            ]);

            return $settings;
        }

        function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        public function generate_form($orderId) {
            global $woocommerce;

            $order = new WC_Order($orderId);

            $lang = get_locale();
            if (strpos($lang, "ru") !== false) {
                $lang = "ru";
            } elseif (strpos($lang, "en") !== false) {
                $lang = "en";
            } else {
                $lang = "ru";
            }
            
            $im_customer = PaySystem\Customer::getInstance(
                (string) $order->billing_email, 
                (string) $order->billing_first_name . ' ' . $order->billing_last_name, 
                (string) $order->billing_phone);
            $im_order = PaySystem\Order::getInstance(
                null, 
                (string) $orderId, 
                floatval($order->order_total), 
                floatval($order->order_total), 
                0, 
                0, 
                (string) $this->im_settings->getTestMode() ? PaySystem\Currency::TST : $this->currency, 
                0, 
                null);

            if (version_compare($woocommerce->version, "3.0", ">=")) {
                $shipping = $order->get_data()['shipping_lines'];
                $hasShipping = (bool) count($shipping);

                foreach ($order->get_items() as $item) {
                    $amount = $item->get_total() / $item->get_quantity();
                    $amount = round($amount, 2);
                    $quantity = $item->get_quantity();
                    $tax = $this->im_settings->getTax();
                    $im_order->addItem($amount, $quantity, (string) $item['name'], $tax);
                }

                if ($hasShipping) {
                    $shippingData = array_shift($shipping);
                    $amount = $shippingData['total'];
                    $amount = round($amount, 2);
                    if($amount > 0) {
                        $tax = $this->im_settings->getDeliveryTax();
                        $im_order->addItem($amount, 1.000, 'Доставка', $tax);
                    }
                }
            }
            else {
                $shipping = $order->get_items('shipping');
                $hasShipping = (bool) count($shipping);

                foreach ($order->get_items() as $itemId => $item) {
                    $quantity = $order->get_item_meta($itemId, '_qty', true);
                    $itemTotal = $order->get_item_meta($itemId, '_line_total', true);
                    $amount = $itemTotal / $quantity;
                    $amount = round($amount, 2);
                    $tax = $this->im_settings->getTax();
                    $im_order->addItem($amount, $quantity, (string) $item['name'], $tax);
                }

                if ($hasShipping) {
                    $itemId = key($shipping);
                    $amount = $order->get_total_shipping();
                    $amount = round($amount, 2);
                    if($amount > 0) {
                        $tax = $this->im_settings->getDeliveryTax();
                        $im_order->addItem($amount, 1.000, 'Доставка', $tax);
                    }
                }
            }
            $this->decodeSuccessUrlAndAddOrderIdIfMissing($orderId);
            $im_payment = PaySystem\Payment::getInstance($this->im_settings, $im_order, $im_customer, $lang);
                            
            date_default_timezone_set('Etc/GMT-3');
            echo
                "<html>\n" .
                "<head>\n" .
                "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />\n" .
                "</head>" . 
                "<body>\n<pre>\n";
            echo $im_payment->generateForm($this->im_settings->getIntegrationMethod() === 'Default', true);
            echo "</body>\n</html>\n";
            die;
        }

        function decodeSuccessUrlAndAddOrderIdIfMissing($orderId) {
            $successUrl = $this->get_option('im_successUrl');
            $parts = parse_url($successUrl);
            if (isset($parts['query'])) {
                parse_str($parts['query'], $queryParams);
                $queryParams = array_change_key_case($queryParams, CASE_LOWER);
                if (!isset($queryParams['orderid'])) {
                    $successUrl .= '&orderId=' . $orderId;
                }
            }
            $this->im_settings->setSuccessUrl(html_entity_decode($successUrl));
        }

        function process_payment($orderId) {
            $order = new WC_Order($orderId);
            if (!version_compare(WOOCOMMERCE_VERSION, '2.1.0', '<'))
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function receipt_page($order) {
            echo '<p>' . __('Сейчас Вы будете перемещены на страницу оплаты', 'woocommerce') . '</p>';
            echo $this->generate_form($order);
        }

        function checkRequest() {
            list($paymentId, $orderId, $recipientAmount, $recipientOriginalAmount, $recipientCurrency) 
                = array($_REQUEST['paymentId'], $_REQUEST['orderId'], $_REQUEST['recipientAmount'], $_REQUEST['recipientOriginalAmount'], $_REQUEST['recipientCurrency']);

            $order = new WC_Order($orderId);
            $im_order = PaySystem\Order::getInstance(
                $paymentId, 
                $orderId, 
                floatval($recipientAmount), 
                floatval($recipientAmount), 
                $recipientOriginalAmount, 
                0, 
                $recipientCurrency, 
                0, 
                null);
            $result = PaySystem\Result::getInstance($_REQUEST, $this->im_settings, $im_order, 'en', false);
            
            if ($result == null) {
                die("Payment with orderId ' . $orderId . ' does not exist.\n");
            }

            $response = $result->processingResponse();

            if ($response->changeStatusResult && !empty($response->statusCMS)) { 
                if ($response->statusCMS == $this->im_settings->getStatusCreated()) {
                    if (in_array($order->get_status(), array($this->im_settings->getStatusPaid(), $this->im_settings->getStatusCancelled()))) {
                        die("ERROR: Order status is not changed! Can't change to status 3\n");
                    } else {
                        $order->update_status($response->statusCMS);
                    }
                }
                if ($response->statusCMS == $this->im_settings->getStatusCancelled()) {
                    $order->update_status($response->statusCMS, __('Заказ отменен', 'woocommerce'));
                }
                if ($response->statusCMS == $this->im_settings->getStatusPaid()) {
                    if ($order->get_status() == $this->im_settings->getStatusCancelled()) {
                        die("ERROR: Order status is not changed!  Can't change to status 5\n");
                    } else {
                        $order->update_status($response->statusCMS);
                        // Очистка корзины.
                        WC()->cart->empty_cart();
                    }
                }
            } 

            echo $result->getMessage();
            die();
        }

        function check_ipn_response() {
            if (isset($_GET['return']) AND $_GET['return'] == 'result_url') {
                @ob_clean();	
                $this->checkRequest();
            } else if (isset($_GET['return']) AND $_GET['return'] == 'success_url') {
                $order = new WC_Order($_GET['orderId']);
                WC()->cart->empty_cart();
                wp_redirect($this->get_return_url($order));
            }
        }
    }

    function add_intellectmoney_gateway($methods) {
        $methods[] = 'WC_IntellectMoney';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_intellectmoney_gateway');
}
?>
