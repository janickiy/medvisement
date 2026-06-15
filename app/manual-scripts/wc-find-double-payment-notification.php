<?php /** @noinspection SqlNoDataSourceInspection */
// Запуск: wp eval-file manual-scripts/wc-find-double-payment-notification.php [real-run]
// Ищем заказы, в которых 2+ раза выставляло статус оплаты (прикол робокассы)

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

global $wpdb;

$real_run = (bool) $args[0] ?? false;

$orders = $wpdb->get_results(
	"
			SELECT 
			  * 
			FROM 
			  (
			    SELECT 
			      comment_post_ID AS order_id, 
			      COUNT(*) AS status_count 
			    FROM 
			      $wpdb->comments
			    WHERE 
			      comment_type = 'order_note' 
			      AND comment_content LIKE '%на «Выполнен»%' 
			    GROUP BY 
			      comment_post_ID 
			    ORDER BY 
			      status_count DESC
			  ) as orders 
			WHERE 
			  orders.status_count > 1;
	"
);

foreach ( $orders as $order ) {

	// Смотрим, были ли начисления по заказу
	$transactions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `source_id`=%d;",
			[
				'order',
				$order->order_id
			]
		)
	);

	$transactions_count = count( $transactions );

	// Начисление одно или нету - все ок
	if ( $transactions_count < 2 ) {
		continue;
	}

	// Ищем дубликаты транзакций
	$transaction_duplicates = array();
	foreach ( $transactions as $transaction ) {
		if ( isset( $transaction_duplicates[ $transaction->target ][ $transaction->target_id ][ $transaction->amount ] ) ) {
			$transaction_duplicates[ $transaction->target ][ $transaction->target_id ][ $transaction->amount ] ++;
		} else {
			$transaction_duplicates[ $transaction->target ][ $transaction->target_id ][ $transaction->amount ] = 1;
		}
	}

	// Тестовый запуск - пропускаем удаление дубликатов
	if ( ! $real_run ) {
		echo "Заказ $order->order_id : " . count( $transactions ) . " \n";
		var_dump( $transaction_duplicates );
		continue;
	}

	// $target - платформа или специальность
	foreach ( $transaction_duplicates as $target => $target_ids ) {
		// $target_ids - для платформы 1, для специальности - id специальности
		foreach ( $target_ids as $target_id => $amounts ) {
			// $amounts - сумма начисления, на всякий случай, но всегда одна
			foreach ( $amounts as $amount => $allocation_count ) {
				// Если начислило несколько раз - удаляем лишние записи
				if ( $allocation_count < 2 ) {
					continue;
				}

				echo "Удален №$order->order_id, $target - $target_id - $amount руб - $allocation_count\n ";

				$wpdb->query(
					$wpdb->prepare(
						"
								DELETE FROM 
								  `{$wpdb->prefix}medvise_transactions`
								WHERE 
								  `source` = 'order' 
								  AND `source_id` = %d 
								  AND `target` = %s 
								  AND `target_id` = %d 
								  AND `amount` = %d 
								LIMIT 
								  %d;
						",
						[
							$order->order_id,
							$target,
							$target_id,
							$amount,
							( $allocation_count - 1 )
						]
					)
				);
			}
		}
	}


}