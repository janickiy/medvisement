<?php
// Запуск: wp eval-file manual-scripts/open-affiliates-program.php
// Открываем пользователям доступ к партнерской программе

global $wpdb;

$current_time   = current_time( 'mysql' );
$suitable_users = array();

// ИД товара "Покупка специальности"
$specialty_product_id = 25925;
// Специальности
$specialty_ids = [
	7 => 'Неврология'
];
// Товары на год +
$product_ids = [
	21936 => 'Годовой',
	5506  => '(архив) 2 года',
	5505  => '(архив) 1 год',
	23089 => 'Оплата из-за рубежа'
];

$orders = wc_get_orders( [
	'status'     => 'completed',
	'limit'      => - 1,
	'meta_query' => [
		'relation' => 'AND',
		// Скипаем заказы, созданные админом
		[
			'relation' => 'OR',
			[
				'key'     => '_wc_order_attribution_source_type',
				'value'   => 'admin',
				'compare' => '!=',
			],
			[
				'key'     => '_wc_order_attribution_source_type',
				'compare' => 'NOT EXISTS',
			]
		],
		// Скипаем продления подписки
		[
			'key'     => '_subscription_renewal',
			'compare' => 'NOT EXISTS',
		],
	],
] );

foreach ( $orders as $order ) {

	// Если сумма заказа 0 - пропускаем
	if ( $order->get_total() == '0.00' ) {
		continue;
	}

	// Если юзер не задан - пропускаем
	if ( $order->get_user_id() === 0 ) {
		continue;
	}

	$order_items = $order->get_items();

	foreach ( $order_items as $order_item ) {

		// Покупка специальности
		if ( $specialty_product_id === $order_item->get_product_id() ) {
			// Специальности в товаре
			$item_specialties = $order_item->get_meta( 'medvise_specialty_access', false );

			foreach ( $item_specialties as $specialty ) {
				$specialty = (object) $specialty->get_data()['value'];

				if ( array_key_exists( $specialty->specialty_id, $specialty_ids ) ) {
					$suitable_users[ $order->get_user_id() ] = $order->get_id();
					break;
				}
			}
		}

		// Покупка на год и более
		if ( array_key_exists( $order_item->get_product_id(), $product_ids ) ) {
			$suitable_users[ $order->get_user_id() ] = $order->get_id();
		}
	}

}

foreach ( $suitable_users as $user_id => $order_id ) {

	$affiliate = YITH_WCAF_Affiliate_Factory::get_affiliate_by_user_id( $user_id );

	// Если еще не партнер - открываем партнерку
	if ( ! $affiliate ) {
		$affiliate = new YITH_WCAF_Affiliate();
		$affiliate->set_user_id( $user_id );

		$affiliate->set_status( 'enabled' );

		$affiliate->update_meta_data( 'application_date', $current_time );

		$res = $affiliate->save();

		do_action( 'yith_wcaf_affiliate_saved', $affiliate );
	}
}

var_dump( $suitable_users );