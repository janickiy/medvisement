<?php

function medrobo_check_payment_status( $order_id ) {

	$payment_result = medroboGetXMLOpStateExt( $order_id );
	$order          = wc_get_order( $order_id );
	$admin_email    = get_option( 'admin_email' );

	// Проверяем код запроса, 0 - все ок. Остальное - ошибки
	if ( ! isset( $payment_result->Result->Code ) || $payment_result->Result->Code != 0 ) {
		medroboFailRenewalOrder( $order );

		wp_mail(
			$admin_email,
			"Robokassa - Заказ №{$order_id} ошибка запроса",
			print_r( (array) $payment_result, true )
		);

		return false;
	}

	// Если код состояния не указан
	if ( ! isset( $payment_result->State->Code ) ) {
		medroboFailRenewalOrder( $order );

		wp_mail(
			$admin_email,
			"Robokassa - Заказ №{$order_id} ошибка состояния",
			print_r( (array) $payment_result, true )
		);

		return false;
	}

	// Код 100 - оплата успешная
	if ( '100' == $payment_result->State->Code ) {
		return true;
	}

	/*
	 * 10 - Операция отменена, деньги от покупателя не были получены.
	 * 60 - Отказ в зачислении средств на счёт магазина. Списанные средства вернулись покупателю на счёт
	 */
	if ( in_array( $payment_result->State->Code, [ '10', '60' ] ) ) {
		medroboFailRenewalOrder( $order );

		return false;
	}

	/*
	 * 5 - Операция только инициализирована, деньги от покупателя не получены.
	 * 20 - Операция находится в статусе HOLD
	 * 50 - Деньги от покупателя получены, производится зачисление денег на счет магазина
	 * 80 - Исполнение операции приостановлено. Операции, находящиеся в этом состоянии, разбираются нашей службой поддержки в ручном режиме.
	 */
	if ( in_array( $payment_result->State->Code, [ '5', '20', '50', '80' ] ) ) {
		wp_mail(
			$admin_email,
			"Robokassa - Заказ №{$order_id} ТРЕБУЕТ ВНИМАНИЯ",
			"Произошла ошибка с кодом состояния \n\n" . medroboGetStateCodeInfo( $payment_result->State->Code ) . "\n\n" .
			print_r( (array) $payment_result, true ) );

		return false;
	}

	return false;
}

add_action( 'medrobo_check_payment_status', 'medrobo_check_payment_status', 10, 1 );

function medroboFailRenewalOrder( $renewal_order ) {

	// Чтобы включались повторные попытки списаний
	add_filter( 'wcs_is_scheduled_payment_attempt', '__return_true', 99 );

	$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );

	if ( ! empty( $subscriptions ) ) {
		foreach ( $subscriptions as $subscription ) {
			$subscription->payment_failed();
		}
	}

}

function medroboGetXMLOpStateExt( $order_id ) {
	$mrhLogin = get_option( 'robokassa_payment_MerchantLogin' );

	if ( get_option( 'robokassa_payment_test_onoff' ) == 'true' ) {
		$pass2 = get_option( 'robokassa_payment_testshoppass2' );
	} else {
		$pass2 = get_option( 'robokassa_payment_shoppass2' );
	}

	$signature = md5( "{$mrhLogin}:{$order_id}:{$pass2}" );

	$response = wp_remote_post( 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/OpStateExt', [
		'body' => [
			'MerchantLogin' => $mrhLogin,
			'InvoiceID'     => $order_id,
			'Signature'     => $signature,
		]
	] );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$xml = @simplexml_load_string( wp_remote_retrieve_body( $response ) );

	if ( ! $xml ) {
		return false;
	}

	return json_decode( json_encode( $xml ) );
}

function medroboGetStateCodeInfo( $code ) {
	switch ( $code ) {
		case '5':
			return '5: Операция только инициализирована, деньги от покупателя не получены. ' .
			       'Или от пользователя ещё не поступила оплата по выставленному ему счёту или платёжная система, ' .
			       'через которую пользователь совершает оплату, ещё не подтвердила факт оплаты.';
		case '10':
			return '10: Операция отменена, деньги от покупателя не были получены. Оплата не была произведена. ' .
			       'Покупатель отказался от оплаты или не совершил платеж, и операция отменилась по истечении времени ожидания. ' .
			       'Либо платёж был совершён после истечения времени ожидания. ' .
			       'В случае возникновения спорных моментов по запросу от продавца или покупателя, операция будет ' .
			       'перепроверена службой поддержки, и в зависимости от результата может быть переведена в другое состояние.';
		case '20':
			return '20: Операция находится в статусе HOLD. Используется в случае отправки запроса на холдирование средств.';
		case '50':
			return '50: Деньги от покупателя получены, производится зачисление денег на счет магазина. ' .
			       'Операция перешла в состояние зачисления средств на баланс продавца. ' .
			       'В этом статусе платёж может задержаться на некоторое время. ' .
			       'Если платёж «висит» в этом состоянии уже долго (более 20 минут), это значит, ' .
			       'что возникла проблема с зачислением средств продавцу.';
		case '60':
			return '60: Отказ в зачислении средств на счёт магазина. Списанные средства вернулись покупателю на счёт (кошелёк), ' .
			       'с которого производилась оплата. Возможные причины ошибки: двойная оплата инвойса, просрочка счёта, ' .
			       'отмена холдирования или внутренние ограничения.';
		case '80':
			return '80: Исполнение операции приостановлено. Внештатная остановка. ' .
			       'Произошла внештатная ситуация в процессе совершения операции (недоступны платежные интерфейсы в системе, ' .
			       'из которой/в которую совершался платёж и т.д.) Или операция была приостановлена системой безопасности. ' .
			       'Операции, находящиеся в этом состоянии, разбираются нашей службой поддержки в ручном режиме.';
		case '100':
			return '100: Платёж проведён успешно, деньги зачислены на баланс продавца, уведомление об успешном платеже отправлено';
	}
}

function medroboLogPaymentRequestResponse( $order_id, $response ) {
	$datetime = new DateTime( '@' . current_time( 'timestamp' ) );;
	$current_time = $datetime->format( 'Y-m-d H:i:s' );

	$request_content = json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

	file_put_contents(
		ROBOKASSA_PLUGIN_DIR . 'request_responses.txt',
		"[{$current_time}] - №{$order_id}\n {$request_content}\n\n",
		FILE_APPEND
	);

	return true;
}