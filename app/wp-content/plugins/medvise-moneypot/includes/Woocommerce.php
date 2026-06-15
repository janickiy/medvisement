<?php

namespace MedviseMoneyPot;

use MedviseSubscriptions\SpecialtyAccess\SpecialtyAccess;
use MedviseSubscriptions\ArticleAccess\ArticleAccess;

class Woocommerce {

	public function setup() {

		// Создаем комиссию в котел после оплаты
		add_action( 'woocommerce_payment_complete', [ $this, 'create_transactions' ], 10, 2 );

		add_action( 'add_meta_boxes', [ $this, 'add_shop_order_meta_boxes' ] );

	}

	public function create_transactions( $order_id, $transaction_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );

		// Проверяем, было ли уже начисление по этому заказу. В случае, если вызвали статус повторно
		$has_transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `source_id`=%d;",
				[
					'order',
					$order->get_id()
				]
			)
		);

        // Начисление уже было...
        if ( $has_transaction ) {

	        $admin_email = get_option( 'admin_email' );
            $order_link = get_site_url( null, "/wp-admin/admin.php?page=wc-orders&action=edit&id=$order_id");

	        wp_mail( $admin_email, "Котёл - Заказ №$order_id двойное начисление",
		        "Была попытка двойного начисления в котёл, заказ " .
		        "№<a href='$order_link'>$order_id</a>. " .
		        "Пожалуйста убедитесь, что все ок."
	        );

            return true;
        }

		$customer_specialty = (int) get_user_meta( $order->get_customer_id(), 'specialty', true );

		$specialty_product_id = SpecialtyAccess::get_specialty_product_id();

        $outside_comission = (int) carbon_get_theme_option( 'med_moneypot_outside_comission' );

		// Проходим по купленным товарам
		$to_transact_specialty = [];
		foreach ( $order->get_items() as $item_id => $item ) {

			// Товар - "Покупка специальности"
			if ( $specialty_product_id && $item->get_product_id() == $specialty_product_id ) {

				// Специальности в товаре
				$item_specialties = $item->get_meta( 'medvise_specialty_access', false );

				// Переводим скидку на товар в % (на случай, если фиксированная)
				$discount = round( ( $item->get_total() / $item->get_subtotal() ), 4 );

				// Раскидываем скидку по каждой специальности И считаем отчисляемый %
				foreach ( $item_specialties as $specialty ) {

					$specialty = (object) $specialty->get_data()['value'];

					// Высчитываем скидку из цены специальности
					$specialty_price = ceil( $specialty->price * $discount );

					$specialty_percent = carbon_get_term_meta( $specialty->specialty_id, 'med_moneypot_percent' );
					if ( empty( $specialty_percent ) ) {
						continue;
					}

					// Высчитываем % в котел
					if ( ! array_key_exists( $specialty->specialty_id, $to_transact_specialty ) ) {
						$to_transact_specialty[ $specialty->specialty_id ] = 0;
					}
					$to_transact_specialty[ $specialty->specialty_id ] += floor( $specialty_price / 100 * $specialty_percent );
				}

			} // Покупка статьи или подписки - по специальности пользователя в анкете
			else {
				$specialty_percent = carbon_get_term_meta( $customer_specialty, 'med_moneypot_percent' );
				if ( empty( $specialty_percent ) ) {
					continue;
				}

				if ( ! array_key_exists( $customer_specialty, $to_transact_specialty ) ) {
					$to_transact_specialty[ $customer_specialty ] = 0;
				}
				$to_transact_specialty[ $customer_specialty ] += floor( $item->get_total() / 100 * $specialty_percent );
			}
		}

		// Делаем отчисление на платформу
		$to_transact_platform = 0;
		$platform_percent     = carbon_get_theme_option( 'med_moneypot_platform_percent' );
		if ( ! empty( $platform_percent ) ) {
			$to_transact_platform = floor( $order->get_total() / 100 * $platform_percent );
		}

		// Записываем в БД по специальностям
		foreach ( $to_transact_specialty as $specialty_id => $amount ) {

			// Пустые значения не пишем
			if ( empty( $amount ) ) {
				continue;
			}

			// Вычитаем % комиссии
			$amount = floor( $amount - ( $amount * $outside_comission / 100 ) );

			$query = "INSERT INTO `{$wpdb->prefix}medvise_transactions` (source, source_id, target, target_id, amount, created_at) " .
			         "VALUES (%s, %d, %s, %d, %d, %s);";

			$wpdb->query( $wpdb->prepare( $query, [
				'order',
				$order->get_id(),
				'specialty',
				$specialty_id,
				$amount,
				$order->get_date_paid()->date( 'Y-m-d' )
			] ) );
		}

		// Записываем в БД по платформе
		if ( ! empty( $to_transact_platform ) ) {
			$query = "INSERT INTO `{$wpdb->prefix}medvise_transactions` (source, source_id, target, target_id, amount, created_at) " .
			         "VALUES (%s, %d, %s, %d, %d, %s);";

			// Вычитаем % комиссии
			$to_transact_platform = floor( $to_transact_platform - ( $to_transact_platform * $outside_comission / 100 ) );

			$wpdb->query( $wpdb->prepare( $query, [
				'order',
				$order->get_id(),
				'platform',
				1,
				$to_transact_platform,
				$order->get_date_paid()->date( 'Y-m-d' )
			] ) );
		}

	}

	public function add_shop_order_meta_boxes() {
		add_meta_box( 'allocations_box', 'Котел', [ $this, 'allocation_meta_box' ],
			wc_get_page_screen_id( 'shop-order' ), 'side', 'high' );
	}

	public function allocation_meta_box( $order ) {
		global $wpdb;

		$query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `source_id`=%d;";

		$transactions = $wpdb->get_results( $wpdb->prepare( $query, [
			'order',
			$order->get_id()
		] ) );
		?>

		<?php if ( empty( $transactions ) ): ?>
            <em>Пусто</em>
		<?php else: ?>
            <ol>
				<?php foreach ( $transactions as $transaction ): ?>
                    <li>
						<?= $transaction->target === 'specialty' ? get_term( $transaction->target_id )->name : 'Платформа'; ?>
                        :
						<?= $transaction->amount; ?>₽
                    </li>
				<?php endforeach; ?>
            </ol>
		<?php endif; ?>

		<?php
	}

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

}