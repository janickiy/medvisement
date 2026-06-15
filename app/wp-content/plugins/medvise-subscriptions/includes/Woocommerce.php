<?php


namespace MedviseSubscriptions\Woocommerce;


use MedviseSubscriptions\Subscriber\Subscriber;
use MeowCrew\SubscriptionsDiscounts\Entity\DiscountedOrderItem;
use MedviseSubscriptions\ArticleAccess\ArticleAccess;
use MedviseSubscriptions\SpecialtyAccess\SpecialtyAccess;

use WC_Order_Item_Product;
use WC_Subscription;


class Woocommerce {

	public function init() {

	    // Активация подписки
		add_action( 'init', [ $this, 'activate_subscription' ] );

		//Платеж прошел
		add_action( 'woocommerce_payment_complete', [ $this, 'order_payment_complete' ], 10, 2 );

		//Временно выводим информацию тут
		add_action( 'woocommerce_account_dashboard', [ $this, 'account_dashboard' ] );

		//Количество просмотров для товара
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'product_fields' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'product_fields_update' ] );

		//Убираем не нужные поля плательщика
		add_filter( 'woocommerce_billing_fields', [ $this, 'woocommerce_billing_fields' ] );
		
		//Действия на странице заказов в аккаунте
        add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'woocommerce_my_account_my_orders_actions'], 10, 2 );

        // Разрешаем добавлять не больше 1 товара
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'woocommerce_add_to_cart_validation' ], 10, 3 );

		// Скрываем оплату криптой
		add_filter('woocommerce_available_payment_gateways', [ $this, 'available_payment_gateways' ], 10, 1);

		// Скрываем отмененные заказы
		add_filter( 'woocommerce_my_account_my_orders_query', [ $this, 'woocommerce_my_account_my_orders_query' ], 10, 1 );

		// Текст в корзине
        add_action( 'woocommerce_before_cart', [ $this, 'woocommerce_before_cart' ] );

		// Текст политика конфиденциальности
		add_filter('woocommerce_get_privacy_policy_text', [ $this, 'woocommerce_get_privacy_policy_text' ], 99, 2);
		// Галочка
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'woocommerce_review_order_before_submit' ], 9 );
		add_action( 'woocommerce_checkout_process', [ $this, 'woocommerce_checkout_process' ] );

		add_action( 'woocommerce_after_my_account', [ $this, 'wcs_confirm_cancel' ] );
		add_action( 'woocommerce_subscription_details_after_subscription_table', [ $this, 'wcs_confirm_cancel' ] );
        
		// Правила повторных списаний
		add_filter( 'wcs_default_retry_rules', [ $this,  'wcs_default_retry_rules' ] );
        // Всегда разрешаем повторное использование платежки
        add_filter( 'wcs_payment_gateways_change_payment_method', [ $this, 'wcs_payment_gateways_change_payment_method' ], 999, 1 );

        // Скрываем действия с подписками
		add_filter( 'wcs_view_subscription_actions', [ $this,  'wcs_view_subscription_actions' ], 10, 3 );
        add_action( 'woocommerce_subscription_after_actions', [ $this, 'woocommerce_subscription_after_actions' ], 10, 1 );

        // отключаем кнопку повторного заказа для заказов содержащих статью
		add_action( 'plugins_loaded', [ $this, 'woocommerce_order_again_button_show_rule' ] );

        // Запрет добавления в корзину определенных товаров
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart_items' ], 10, 2 );
    }

	public function order_payment_complete( $order_id, $transaction_id ) {

		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();

		//Для записи в баланс просмотров пользователя
		$views = [
			'disease_views'   => 0
		];

		foreach ( $order_items as $order_item ) {

			//Оригинальные данные товара
			$med_disease_views_original = get_post_meta( $order_item->get_product_id(), '_med_disease_views', true );
			$med_subscription_days = get_post_meta( $order_item->get_product_id(), '_med_subscription_days', true );

			//Сохраняем просмотры заболеваний на момент оплаты за заказом
			if ( ! empty( $med_disease_views_original ) && empty( wc_get_order_item_meta( $order_item->get_id(), '_med_disease_views', true ) ) ) {

				//Закрепляем просмотры за товаром заказа
                    wc_add_order_item_meta( $order_item->get_id(), '_med_disease_views', $med_disease_views_original, true );
			}

			//Сохраняем кол-во дней подписки на момент оплаты за заказом
			if ( ! empty( $med_subscription_days ) && empty( wc_get_order_item_meta( $order_item->get_id(), '_med_subscription_days', true ) ) ) {

				//Закрепляем просмотры за товаром заказа
				wc_add_order_item_meta( $order_item->get_id(), '_med_subscription_days', $med_subscription_days, true );
			}

			//Пустое значение будет 0
			$views['disease_views']   += ((int) $med_disease_views_original) * $order_item->get_quantity();
		}

		//Зачисляем просмотры на баланс
		Subscriber::addViews( $order->get_user_id(), $views );
    }

	public function account_dashboard() {
		$user          = wp_get_current_user();
		$subscriptions = Subscriber::getSubscriptionsRaw( $user->ID );
		$views_history = Subscriber::historyViews( $user->ID );
		$specialties_history = Subscriber::historySpecialties( $user->ID );

		?>
        <p>
		<?php if ( empty( $subscriptions ) ): ?>
		<?php else: ?>
            <strong>Мои подписки</strong><br>
            <table>
                <tr>
                    <th>№ заказа</th>
                    <th>Дата начала</th>
                    <th>Дата окончания</th>
                </tr>
				<?php foreach ( $subscriptions as $subscription ): ?>
                    <tr>
                        <td><?= $subscription->order_id; ?></td>
                        <td><?= strtok($subscription->start_date,' '); ?></td>
                        <td><?= strtok($subscription->end_date,' '); ?></td>
                    </tr>
				<?php endforeach; ?>
            </table>
		<?php endif; ?>

		<?php if ( ! empty( $specialties_history ) ): ?>
        <p>
            <strong>Открытые специальности</strong>
        </p>
        <table>
            <tr>
                <th>Специальность</th>
                <th>Дата открытия</th>
                <th>Дата окончания</th>
            </tr>
			<?php foreach ( $specialties_history as $specialties_history_item ): ?>
                <tr>
                    <td>
                        <a href="<?= get_term_link( intval($specialties_history_item->specialty_id), 'specialty' ); ?>" target="_blank">
			                <?= get_term( $specialties_history_item->specialty_id, 'specialty' )->name; ?>
                        </a>
                    </td>
                    <td><?= $specialties_history_item->date_open; ?></td>
                    <td><?= $specialties_history_item->date_expiry; ?></td>
                </tr>
			<?php endforeach; ?>
        </table>
        <?php endif; ?>

        <p>
            <strong>Открытые статьи</strong>
        </p>
        <table>
            <tr>
                <th>Статья</th>
                <th>Дата открытия</th>
                <th>Дата окончания</th>
            </tr>
            <?php foreach ( $views_history as $history_item ): ?>
                <tr>
                    <td>
                        <a href="<?= get_permalink( $history_item->post_id ); ?>" target="_blank">
                            <?= get_the_title( $history_item->post_id ); ?>
                        </a>
                    </td>
                    <td><?= $history_item->date_open; ?></td>
                    <td><?= $history_item->date_expiry; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

		<?php
	}

	public function product_fields() {

		echo '<div class="hidden hide_if_subscription">';

		woocommerce_wp_text_input(
			[
				'id'                => '_med_disease_views',
				'label'             => 'Просмотры заболеваний',
				'description'       => 'Количество просмотров заболеваний, включая полезные материалы.',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '0'
				)
			]
		);

		woocommerce_wp_text_input(
			[
				'id'                => '_med_subscription_days',
				'label'             => 'Дней подписки',
				'description'       => 'Количество дней подписки.',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '1'
				)
			]
		);

		echo "</div>";

	}

	public function product_fields_update( $product_id ) {

	    // Смотрим тип товара, задать эти параметры можно только простому
		$product_type = $_POST['product-type'];

        // todo в будущем прогнать скриптом и удалить просмотры
        delete_post_meta( $product_id, '_med_substance_views');

		$disease_views = $_POST['_med_disease_views'];
		if ( ! empty( $disease_views ) && $product_type === 'simple' ) {
			update_post_meta( $product_id, '_med_disease_views', esc_attr( $disease_views ) );
		}
		else {
			delete_post_meta( $product_id, '_med_disease_views');
		}

		$subscription_days = $_POST['_med_subscription_days'];
		if ( ! empty( $subscription_days ) && $product_type === 'simple' ) {
			update_post_meta( $product_id, '_med_subscription_days', esc_attr( $subscription_days ) );
		}
		else {
			delete_post_meta( $product_id, '_med_subscription_days');
		}
	}

	public function woocommerce_billing_fields( $fields ) {
		unset( $fields['billing_address_1'] );
		unset( $fields['billing_address_2'] );
		unset( $fields['billing_city'] );
		unset( $fields['billing_postcode'] );
		unset( $fields['billing_state'] );
		unset( $fields['billing_company'] );

		return $fields;
	}

	public function woocommerce_my_account_my_orders_actions( $actions, $order ) {

		if ( $this->orderHasValidDateSubscription($order->get_id()) ) {
		    $med_activate_nonce = wp_create_nonce('medvise_activate_order');
			$actions['med_activate'] = [
				'url'  => get_permalink( wc_get_page_id( 'myaccount' ) ) . 'orders/?medvise_activate_order=' . $order->get_id() . '&token=' . $med_activate_nonce,
				'name' => 'Активировать'
			];
		}

		// Если кнопка оплаты есть и истек срок оплаты, убираем ее
		// todo отменять заказ такой нужно
		if ( array_key_exists( 'pay', $actions ) ) {

			$current_date = new \DateTime( '@' . current_time( 'timestamp' ) );
			$order_date   = $order->get_date_created();

			$days_passed = (int) $order_date->diff( $current_date )->format( "%a" );
			if ( $days_passed > 7 ) {
				unset( $actions['pay'] );
			}
		}

		return $actions;
	}

	public function orderHasValidDateSubscription($order_id) {
		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();

		if ( 'completed' !== $order->status ) {
		    return false;
		}

		//Заказ уже был активирован
		if ( $order->get_meta( '_med_subscription_activated', true ) )
		    return false;

		foreach ( $order_items as $order_item ) {
			$med_subscription_days = get_post_meta( $order_item->get_product_id(), '_med_subscription_days', true );

			if ( ! empty($med_subscription_days) )
			    return true;
		}

		return false;
	}

	public function activate_subscription() {

	    // Не активация - ничего не делаем
	    if ( empty($_GET['medvise_activate_order']) ) {
	        return true;
	    }

		check_ajax_referer( 'medvise_activate_order', 'token' );

		$order       = wc_get_order( $_GET['medvise_activate_order'] );
		$user = wp_get_current_user();

		if ( ! $order ) {
			wc_add_notice(
				"Невозможно активировать подписку. Заказ №{$order->id} не существует.",
				'error'
			);
			wp_safe_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ) );
			exit();
		}

		if ( $order->get_user_id() !== $user->ID ) {
			wc_add_notice(
				"Нельзя активировать подписку другого пользователя. Заказ №{$order->id}.",
				'error'
			);
			wp_safe_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ) );
			exit();
		}

	    if ( ! wp_verify_nonce( $_GET['token'], 'medvise_activate_order' ) ) {
		    wc_add_notice(
			    "Ошибка проверки ключа безопасности при активации подписки. Заказ №{$order->id}.",
			    'error'
		    );
		    wp_safe_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ) );
		    exit();
	    }

	    if ( ! $this->orderHasValidDateSubscription($order->id) ) {
		    wc_add_notice(
			    "Заказ №{$order->id} не содержит подписку или уже был активирован.",
			    'error'
		    );
		    wp_safe_redirect( $order->get_view_order_url() );
	    }

		$order->update_meta_data( '_med_subscription_activated', 1 );
	    $order->save();

	    // Сразу ставим статус оплаты
		$order_items = $order->get_items();

		$subscription_days_total = 0;
		foreach ( $order_items as $order_item ) {
			$med_subscription_days = (int) get_post_meta( $order_item->get_product_id(), '_med_subscription_days', TRUE );
			$subscription_days_total += $med_subscription_days;
		}

		$active_subscription = self::getActiveSubscription($order->get_user_id());

		if ( $active_subscription ) {
			$start_datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $active_subscription);
		}
		else {
			$start_datetime = new \DateTime('now');
		}

		global $wpdb;
		$end_datetime = clone $start_datetime;
		$end_datetime->modify( '+' . $subscription_days_total . ' day' );

		$query = "INSERT INTO `{$wpdb->prefix}medvise_subscriptions` (user_id, order_id, start_date, end_date) " .
		         "VALUES (%d, %d, %s, %s);";

		$wpdb->query( $wpdb->prepare( $query, [
			$order->get_user_id(),
			$order->id,
			$start_datetime->format( 'Y-m-d H:i:s' ),
			$end_datetime->format( 'Y-m-d' ),
		] ) );

		wc_add_notice(
			"Подписка по заказу №{$order->id}. была успешно активирована.",
			'success'
		);
		wp_safe_redirect( $order->get_view_order_url() );
		exit();
	}

	public static function getActiveSubscription($user_id = NULL) {

	    if ($user_id === NULL) {
		    $user = get_current_user();
	    }
	    else {
		    $user = get_user_by( 'ID', $user_id);
	    }

	    if ( ! $user ) {
	        return false;
	    }

	    global $wpdb;
		$today_datetime = new \DateTime('now');

		$query = "SELECT end_date FROM `{$wpdb->prefix}medvise_subscriptions` WHERE user_id=%d AND end_date > %s ORDER BY `end_date` DESC LIMIT 1;";

		$subscription = $wpdb->get_var( $wpdb->prepare( $query, [
			$user->ID,
			$today_datetime->format('Y-m-d')
		] ) );

		return empty($subscription) ? false : $subscription;
	}

	public function woocommerce_add_to_cart_validation( $valid, $product_id, $quantity ) {

	    // Очищаем корзину
        foreach (WC()->cart->cart_contents as $key => $item) {
	        unset( WC()->cart->cart_contents[ $key ] );
        }

		return $valid;
	}

	public function available_payment_gateways( $available_gateways ) {

	    // Админка
		if( is_admin() )
			return $available_gateways;
		
		$user = wp_get_current_user();

		$med_subscriber_payment = carbon_get_user_meta( $user->ID, 'med_subscriber_payment' );

		// Еще не иницилизированы мета поля - пропускаем так
		if (NULL === $med_subscriber_payment) {
		    return $available_gateways;
		}

		// Убираем отключенные способы оплаты
		foreach ($available_gateways as $key_gateway => $available_gateway) {
		    if ( ! in_array( $key_gateway, $med_subscriber_payment) ) {
			    unset($available_gateways[$key_gateway]);
		    }
		}

		return $available_gateways;
	}

	public function woocommerce_my_account_my_orders_query($args) {
		$args['status'] = array(
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-completed',
            //'wc-cancelled',
			'wc-refunded',
			'wc-failed',
		);

		return $args;
	}

	public function woocommerce_before_cart() {

		// не выводим сообщение если в корзине только товар для оплаты статей
		$product_id = ArticleAccess::articles_payment_get_option_product_id();
		$cart       = WC()->cart->get_cart();

		if ( count( $cart ) == 1 && in_array( $product_id, array_column( $cart, 'product_id' ) ) ) {
			return;
		}

	    // Если доступ уже есть - предупреждаем
        // wc_print_notice( __("У вас уже имеется активный доступ. Пожалуйста, убедитесь в том, что вы хотите оформить еще один заказ."), 'notice' );

	}

	public function woocommerce_get_privacy_policy_text( $text, $type ) {

		if ( $type === 'registration' ) {
			return "Регистрируясь на сайте, я даю согласие на <a href='/privacy-policy/' target='_blank'>обработку персональных данных</a>.";
		}

		if ( $type === 'checkout' ) {
		    return '';
		}

		return $text;
	}

	public function woocommerce_review_order_before_submit() {

		// Смотрим, есть ли в корзине подписка
		global $woocommerce;
		$items = $woocommerce->cart->get_cart();

		$has_subscription = false;
		foreach ( $items as $item => $values ) {
			$_product = wc_get_product( $values['data']->get_id() );

			if ( 'subscription' === $_product->get_type() ) {
				$has_subscription = true;
				break;
			}
		}

			$text = "Я даю согласие на <a href='/privacy-policy/' target='_blank'>обработку персональных данных</a> " .
			       "и принимаю <a href='/public-offer/' target='_blank'>условия публичной оферты</a>.";

		woocommerce_form_field( 'privacy_policy', array(
			'type'          => 'checkbox',
			'class'         => array('form-row privacy'),
			'label_class'   => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
			'input_class'   => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
			'required'      => true,
			'label'         => $text,
		));

	}

	public function woocommerce_checkout_process() {
		if ( ! (int) isset( $_POST['privacy_policy'] ) ) {
			wc_add_notice( 'Для оформления заказа вы должны согласиться с условиями (поставить галочку) напротив них.', 'error' );
		}
	}

	public function wcs_confirm_cancel() {
		?>
        <script>
            jQuery(document).ready(function ($) {
                $("td.subscription-actions a.cancel, table.shop_table.subscription_details a.cancel").on("click", function (e) {
                    var confirmCancel = confirm(
                        "Вы действительно хотите отменить подписку? \n\n" +
                        "При отмене подписки, прогрессирующая скидка будет сброшена и приобрести доступ по текущей цене станет невозможным. \n" +
                        "При повторном подключении тарифа, придётся производить оплату с 1 этапа, согласно графику платежей."
                    )

                    if (!confirmCancel) {
                        e.preventDefault();

                        // Небольшой хак
                        setTimeout(function() {
                            $( '.subscription_details' ).unblock();
                        }, 200);
                    }
                })
            }) </script>
		<?php
	}

    public function wcs_default_retry_rules() {
        return array(
	        array(
		        'retry_after_interval'            => DAY_IN_SECONDS / 2,
		        'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
		        'status_to_apply_to_order'        => 'pending',
		        'status_to_apply_to_subscription' => 'on-hold',
	        ),
	        array(
		        'retry_after_interval'            => DAY_IN_SECONDS / 2,
		        'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
		        'status_to_apply_to_order'        => 'pending',
		        'status_to_apply_to_subscription' => 'on-hold',
	        ),
	        array(
		        'retry_after_interval'            => DAY_IN_SECONDS,
		        'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
		        'status_to_apply_to_order'        => 'pending',
		        'status_to_apply_to_subscription' => 'on-hold',
	        ),
	        array(
		        'retry_after_interval'            => DAY_IN_SECONDS * 2,
		        'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
		        'status_to_apply_to_order'        => 'pending',
		        'status_to_apply_to_subscription' => 'on-hold',
	        ),
	        array(
		        'retry_after_interval'            => DAY_IN_SECONDS * 3,
		        'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
		        'status_to_apply_to_order'        => 'pending',
		        'status_to_apply_to_subscription' => 'on-hold',
	        ),
        );
    }

    public function wcs_payment_gateways_change_payment_method($is_change) {
        return false;
    }

    public function wcs_view_subscription_actions( $actions, $subscription, $user_id ) {

	    foreach ( $subscription->get_items() as $item ) {
		    if ( ! $item instanceof \WC_Order_Item_Product ) {
			    continue;
		    }

		    try {
			    $discountedItem = new DiscountedOrderItem( $item, $subscription );

			    $discounts   = $discountedItem->getDiscounts( false );
			    $totalRenewals = $discountedItem->getTotalRenewals() + 1;

			    $nextDiscount = $discountedItem->calculateAppliedDiscount($totalRenewals);

                // Отменить можно только если следующий месяц - платный
                if ( array_key_exists( $nextDiscount, $discounts ) && $discounts[$nextDiscount] == 0 ) {
                    unset($actions['cancel']);
                }
		    } catch ( \Exception $exception ) {
			    continue;
		    }

		    unset($actions['resubscribe']);

	    }

        // todo пересмотреть, как выглядит процесс переподписки
	    unset($actions['resubscribe']);

        return $actions;
    }

    public function woocommerce_subscription_after_actions($subscription) {

        if ( $subscription->has_product(21936) ) {
	        echo "<tr><td colspan='2' style='font-style: italic;'>Отменить годовую подписку можно в последний бесплатный (12-й) месяц.</td></tr>";
        }

    }
    
	public function woocommerce_order_again_button_show_rule() {
	    remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
	    add_action( 'woocommerce_order_details_after_order_table', [ $this, 'woocommerce_order_again_button_override' ] );
	}

	public function woocommerce_order_again_button_override( $order ) {
		$product_id_article  = ArticleAccess::articles_payment_get_option_product_id();
		foreach( $order->get_items() as $item_id => $item ) {
			if ( $product_id_article && $item->get_product_id() == $product_id_article ) {
				return;
			}
		}

		$product_id_specialty = SpecialtyAccess::get_specialty_product_id();
		foreach( $order->get_items() as $item_id => $item ) {
			if ( $product_id_specialty && $item->get_product_id() == $product_id_specialty ) {
				return;
			}
		}

		if ( ! $order || ! $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) ) ) || ! is_user_logged_in() ) {
				return;
		}

		wc_get_template(
			'order/order-again.php',
			array(
				'order'           => $order,
				'wp_button_class' => wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '',
				'order_again_url' => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
			)
		);
	}

	public function validate_add_to_cart_items( $passed_validation, $product_id ) {
        $restricted_product_ids = [];

        // Статьи и специальности просто так нельзя добавлять
        $restricted_product_ids[] = ArticleAccess::articles_payment_get_option_product_id();
        $restricted_product_ids[] = SpecialtyAccess::get_specialty_product_id();

        return ! in_array( $product_id, $restricted_product_ids );
	}
}