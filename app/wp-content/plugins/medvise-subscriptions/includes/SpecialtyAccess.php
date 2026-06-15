<?php


namespace MedviseSubscriptions\SpecialtyAccess;


class SpecialtyAccess {

	public function init() {

		// ajax добавление специальностей в корзину
		add_action( 'wp_ajax_nopriv_specialty_add_to_cart_ajax', [ $this, 'specialty_add_to_cart_ajax' ] );
		add_action( 'wp_ajax_specialty_add_to_cart_ajax', [ $this, 'specialty_add_to_cart_ajax' ] );

		// Добавляем css класс товарам для возможности раздельной стилизации
		add_filter( 'woocommerce_cart_item_class', [ $this, 'cart_item_class' ], 10, 3 );

		// Отображение специальностей в корзине
		add_filter( 'woocommerce_get_item_data', [ $this, 'cart_display_field' ], 10, 2 );

		// ajax удаление специальностей из корзины
		add_action( 'wp_ajax_nopriv_specialty_remove_from_cart_ajax', [ $this, 'specialty_remove_from_cart_ajax' ] );
		add_action( 'wp_ajax_specialty_remove_from_cart_ajax', [ $this, 'specialty_remove_from_cart_ajax' ] );

		// Отображение названия товара в корзине
		add_filter( 'woocommerce_cart_item_name', [ $this, 'cart_product_name' ], 100, 3 );

		// Подсчет цены специальностей в чекауте
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'total_price_calc' ], 1000, 1 );

		// Передаем данные о специальностях в мету заказа
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'order_item_meta' ], 10, 4 );

		// Отображение названия товара в заказе
		add_filter( 'woocommerce_order_item_name', [ $this, 'order_product_name' ], 10, 2 );

		// Убираем ссылку на товар в табице отображения заказа
		add_filter( 'woocommerce_order_item_permalink', [ $this, 'disable_order_product_url' ], 10, 3 );

		// Вывод списка специальностей в заказе
		add_filter( 'woocommerce_display_item_meta', [ $this, 'order_product_items' ], 10, 3 );

		// Вывод названия товара в админке заказа
		add_action( 'woocommerce_before_order_item_line_item_html', [ $this, 'admin_panel_product_title' ], 10, 3 );

		// Вывод списка специальностей в админке заказа
		add_action( 'woocommerce_after_order_itemmeta', [ $this, 'admin_panel_product_items' ], 10, 3 );

		// Прячем мету с днями в админке заказа
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'admin_panel_hide_order_itemmeta' ], 10, 1 );

		// После оплаты открываем пользователю нужные специальности
		add_action( 'woocommerce_payment_complete', [ $this, 'specialties_open_access' ], 10, 2 );

        // Валидация корзины
		add_action( 'woocommerce_check_cart_items', [ $this, 'validate_all_cart_contents' ] );
	}

	// Забираем ID товара из глобальной настройки Woocommerce -> "Покупка специальностей"
	public static function get_specialty_product_id() {
		$get_option = carbon_get_theme_option( 'med_specialty_access_product' );
		$product_id = '';

		if ( ! empty( $get_option ) ) {
			$product_id = intval( $get_option[0]['id'] );
		}

		return $product_id;
	}

	public function specialty_add_to_cart_ajax() {
		$specialty = get_term_by( 'term_id', $_POST['term_id'], 'specialty' );

		if ( empty( $specialty ) ) {
			wp_send_json_error();
			wp_die();
		}

		$specialty_access_price = carbon_get_term_meta( $specialty->term_id, 'med_specialty_access_price' );
		// Стоимомсть специальности не задана - добавить в корзину нельзя
		if ( empty( $specialty_access_price ) ) {
			wp_send_json_error();
			wp_die();
		}

		// ID товара Оплата специальности
		$product_id = self::get_specialty_product_id();
		if ( empty( $product_id ) ) {
			wp_send_json_error();
			wp_die();
		}

		// Кол-во дней из глобальной настройки
		$specialty_access_days = carbon_get_theme_option( 'med_specialty_access_days' );
		if ( empty( $specialty_access_days ) ) {
			wp_send_json_error();
			wp_die();
		}

		// Если товара нет в корзине - добавляем
		if ( ! in_array( $product_id, array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {
			WC()->cart->add_to_cart( $product_id );
		}

		$cart = WC()->cart->cart_contents;
		foreach ( $cart as $cart_item_id => $cart_item ) {

			// Находим наш товар в корзине
			if ( $product_id == $cart_item['product_id'] ) {

				// Специальностей нет в корзине
				if ( empty( $cart_item['specialties_order'] ) ) {
					$cart_item['specialties_order'] = array(
						strval( $specialty->term_id ) => array(
							'specialty_id' => strval( $specialty->term_id ),
							'title'        => $specialty->name,
							'price'        => $specialty_access_price,
							'days'         => $specialty_access_days
						),
					);

					WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;
					WC()->cart->set_session();
					wp_send_json_success();
					wp_die();

				} else {

					// Специальности есть, уточняем есть ли текущая
					if ( ! array_key_exists( strval( $specialty->term_id ), $cart_item['specialties_order'] ) ) {
						$cart_item['specialties_order'][ strval( $specialty->term_id ) ] = array(
							'specialty_id' => strval( $specialty->term_id ),
							'title'        => $specialty->name,
							'price'        => $specialty_access_price,
							'days'         => $specialty_access_days
						);
						
						WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;
						WC()->cart->set_session();
						wp_send_json_success();
						wp_die();
					}
				}
			}
		}

		wp_send_json_error();
		wp_die();
	}

	public function cart_item_class( $class, $values, $values_key ) {
		$product_id = self::get_specialty_product_id();

		if ( isset( $values[ 'product_id' ] ) && $values[ 'product_id' ] == $product_id ) {
			$class .= ' product-specialty';
		}

		return $class;
	}

	public function cart_display_field( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['specialties_order'] ) ) {

			$item_data = array();

			foreach ( $cart_item['specialties_order'] as $specialty_id => $data ) {

				// Если в заказе всего 1 статья не выводим кнопку
				if ( count( $cart_item['specialties_order'] ) == 1 ) {
					$html = '';
				} else {
					$html = '<a href="#" class="remove js-remove-single-specialty" data-specialty-id="' . $specialty_id . '">×</a>  ';
				}

				$days = self::plural_days( intval( $data['days'] ) );

				$html .= '<a href="' . get_term_link( intval( $specialty_id ), 'specialty' ) . '">';
				$html .= $data['title'] . '</a> ' . $days . ' : ' . $data['price'] . ',00₽';

				$item_data[] = array(
					'key'     => 'medvise_specialty_access',
					'value'   => $data,
					'display' => $html,
				);
			}

		}
		return $item_data;
	}

	public function specialty_remove_from_cart_ajax() {
		$specialty_id = $_POST['term_id'];
		$product_id   = self::get_specialty_product_id();

		// Товар из настроек добавлен в корзину
		if ( in_array( $product_id, array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {

			$cart = WC()->cart->cart_contents;
			foreach ( $cart as $cart_item_id => $cart_item ) {

				if ( $product_id == $cart_item['product_id'] ) {

					if ( ! empty( $cart_item['specialties_order'] ) && array_key_exists( intval( $specialty_id ), $cart_item['specialties_order'] ) ) {
						unset( $cart_item['specialties_order'][intval( $specialty_id )] );

						\WC_Form_Handler::update_cart_action();

						WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;
						WC()->cart->set_session();
						wp_send_json_success();
						wp_die();
					}
				}
			}
		}

		wp_send_json_error();
		wp_die();
	}

	public function cart_product_name( $item_name,  $cart_item,  $cart_item_key ) {

		$product_id = self::get_specialty_product_id();
		if ( $product_id ) {

			if ( $cart_item['product_id'] == $product_id ) {

				if ( ! empty( $cart_item['specialties_order'] ) && count( $cart_item['specialties_order'] ) > 1 ) {
					$item_name = 'Открытие специальностей:';
				} else if ( ! empty( $cart_item['specialties_order'] ) && count( $cart_item['specialties_order'] ) == 1 ) {
					$item_name = 'Открытие специальности:';
				}
			}
		}

		return $item_name;
	}

	public function total_price_calc( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = self::get_specialty_product_id();

			if ( $cart_item['product_id'] == $product_id && ! empty( $cart_item['specialties_order'] ) ) {
				$total_specialties_price = 0;

				foreach ( $cart_item['specialties_order'] as $specialty_id => $data ) {
					$total_specialties_price += intval( $data['price'] );
				}

				$cart_item['data']->set_price( $total_specialties_price );
			}
		}
	}

	public function order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values[ 'specialties_order' ] ) ) {
			foreach ( $values[ 'specialties_order' ] as $specialty_id => $data ) {
				$item->add_meta_data( 'medvise_specialty_access', $data );
			}
		}
	}

	public function order_product_name( $product_name, $item ) {
		$product_id = self::get_specialty_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$item_meta_data = $item->get_meta( 'medvise_specialty_access', false );

			if ( ! empty( $item_meta_data ) && count( $item_meta_data ) > 1 ) {
				$product_name = 'Открытие специальностей:';
			} elseif ( ! empty( $item_meta_data ) && count( $item_meta_data ) == 1 ) {
				$product_name = 'Открытие специальности:';
			}
		}

		return $product_name;
	}

	public function disable_order_product_url( $product_url, $item, $order ) {
		$product_id = self::get_specialty_product_id();

		if ( $product_id ) {
			if ( $item->get_product_id() == $product_id ) {
				return false;
			}
		}
		return $product_url;
	}

	public function order_product_items( $html, $item, $args ) {
		$product_id = self::get_specialty_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$html      = '';
			$item_meta = $item->get_meta( 'medvise_specialty_access', false );
			if ( $item_meta ) {
				foreach ( $item_meta as $meta ) {
					$specialty_data = $meta->get_data();

					$days = self::plural_days( intval( $specialty_data['value']['days'] ) );

					$html .= '<div><a href="' . get_term_link( intval( $specialty_data['value']['specialty_id'] ), 'specialty' ) . '">';
					$html .= $specialty_data['value']['title'] . '</a> ' . $days . ' : ';
					$html .= strval( $specialty_data['value']['price'] ) . ',00₽</div>';
				}
			}
		}
		return $html;
	}

	public function admin_panel_product_title( $item_id, $item, $order ) {
		$product_id = self::get_specialty_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$item_meta_data = $item->get_meta( 'medvise_specialty_access', false );

			if ( ! empty( $item_meta_data ) && count( $item_meta_data ) > 1 ) {
				$item->set_name( 'Открытие специальностей:' );
			} elseif ( ! empty( $item_meta_data ) && count( $item_meta_data ) == 1 ) {
				$item->set_name( 'Открытие специальности:' );
			}
		}
	}

	public function admin_panel_product_items( $item_id, $item, $null ) {
		$product_id = self::get_specialty_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$item_meta = $item->get_meta( 'medvise_specialty_access', false );
			if ( $item_meta ) {
				foreach ( $item_meta as $meta ) {
					$specialty_data = $meta->get_data();

					$days = self::plural_days( intval( $specialty_data['value']['days'] ) );

					$html = '<div><a href="' . get_edit_term_link( intval( $specialty_data['value']['specialty_id'] ), 'specialty' ) . '">';
					$html .= $specialty_data['value']['title'] . '</a> ' . $days . ' : ';
					$html .= strval( $specialty_data['value']['price'] ) . ',00₽</div>';

					echo $html;
				}
			}
		}
	}

	public function admin_panel_hide_order_itemmeta( $array ) {
		$array[] = '_med_specialty_days';
		return $array;
	}

	public function specialties_open_access( $order_id, $transaction_id ) {
		global $wpdb;

		$order       = wc_get_order( $order_id );
		$product_id  = self::get_specialty_product_id();
		$customer_id = $order->get_customer_id();

		$option_days = carbon_get_theme_option( 'med_specialty_access_days' );
		if ( empty( $option_days ) ) {
			return;
		} else {
			$option_days = strval( $option_days );
		}

		foreach( $order->get_items() as $item_id => $item ) {

			if ( $product_id && $item->get_product_id() == $product_id ) {

				//Сохраняем кол-во дней подписки на момент оплаты за заказом
				if ( empty( wc_get_order_item_meta( $item->get_id(), '_med_specialty_days', true ) ) ) {

					//Закрепляем дни за товаром заказа
					wc_add_order_item_meta( $item->get_id(), '_med_specialty_days', $option_days, true );
				}

				$item_meta = $item->get_meta( 'medvise_specialty_access', false );
				if ( $item_meta ) {
					foreach ( $item_meta as $meta ) {
						$specialty_data  = $meta->get_data();
						$specialty_id    = $specialty_data['value']['specialty_id'];
						$datetime_now    = date( 'Y-m-d H:i:s' );
						$datetime_inyear = date( 'Y-m-d H:i:s', strtotime( '+' . $option_days . ' days' ) );

						// Проверяем, был ли открыт доступ к специальности
						$page_view_access = $wpdb->get_row(
							"SELECT * FROM {$wpdb->prefix}medvise_specialty_views " .
							"WHERE user_id={$customer_id} AND specialty_id={$specialty_id} AND date_expiry >= NOW();"
						);

						// Если доступа нет - предоставляем
						if ( $page_view_access === null ) {
							$wpdb->replace(
								$wpdb->prefix . 'medvise_specialty_views',
								[
									'user_id'      => $customer_id,
									'specialty_id' => $specialty_id,
									'date_open'    => $datetime_now,
									'date_expiry'  => $datetime_inyear
								],
								[ '%d', '%d', '%s', '%s' ]
							);
						} else { 
							// Если есть, прибавляем нужный период
							$date_open_update   = $page_view_access->date_open;
							$date_expiry_update = date( 'Y-m-d H:i:s', strtotime( 
								$page_view_access->date_expiry . ' +' . $option_days . ' days' 
							) );

							$wpdb->replace(
								$wpdb->prefix . 'medvise_specialty_views',
								[
									'user_id'      => $customer_id,
									'specialty_id' => $specialty_id,
									'date_open'    => $date_open_update,
									'date_expiry'  => $date_expiry_update
								],
								[ '%d', '%d', '%s', '%s' ]
							);
						}
					}
				}
			}
		}
	}

	public static function renderSpecialtyAccessButton( $specialty_id ) {
        ?>
        <a class="button" href="#" data-product-id="" data-specialty-id="<?= $specialty_id; ?>">
            В корзину
        </a>
        <?php
        // todo переписать тут логику отображения. Т.к. может быть товар ручной подписки и отдельный на месяц/год
        // Сейчас оно всегда в корзине после перезагрузки страницы, даже если товар уже там
        // Ничего критичного, просто визуал
        return;

		$product_id = self::get_specialty_product_id();

		// Если товар для покупки специальностей не задан - выходим
		if ( empty( $product_id ) ) {
			return;
		}

		$specialty = get_term_by( 'term_id', $specialty_id, 'specialty' );
		$specialty_access_price = carbon_get_term_meta( $specialty_id, 'med_specialty_access_price' );

        // Специальность без стоимости - выходим
        if ( empty($specialty_access_price) || empty($specialty) ) {
            return;
        }

		// Проверяем наличие товара в корзине
        $already_in_cart = false;
		if ( in_array( $product_id, array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {
			$cart = WC()->cart->cart_contents;
			foreach ( $cart as $cart_item_id => $cart_item ) {
				// Ловим нужный товар
				if ( $product_id == $cart_item['product_id'] ) {
					// В корзине есть специальности, отмечаем те что в корзине
					if ( ! empty( $cart_item['specialties_order'] ) && array_key_exists( $specialty_id, $cart_item['specialties_order'] ) ) {
						$already_in_cart = true;
					}
				}
			}
		}

		if ( $already_in_cart ): ?>
            <a class="button" href="<?= wc_get_cart_url(); ?>" class="added_to_cart wc-forward"
               title="Просмотр корзины">
                В корзине
            </a>
		<?php else: ?>
            <a class="button js-buy-theme-pack" href="#" data-product-id="<?= $product_id; ?>">
                В корзину
            </a>
		<?php endif;

	}

	public function validate_all_cart_contents() {

		$product_id = self::get_specialty_product_id();
		$cart = WC()->cart->cart_contents;

		foreach ( $cart as $cart_item_id => $cart_item ) {

			if ( $product_id != $cart_item['product_id'] ) {
                continue;
			}

            // В товаре не заданы специальности
			if ( empty( $cart_item['specialties_order'] ) ) {
				wc_add_notice( "Специальность для покупки не выбрана. Пожалуйста, добавьте специальность в корзину заново.", 'error' );
				WC()->cart->empty_cart();
                continue;
			}

            // Проверяем каждую специальность на корректность цены и возможность покупки
            foreach ( $cart_item['specialties_order'] as $specialty_id => $data ) {

	            $specialty_access_price = carbon_get_term_meta( $specialty_id, 'med_specialty_access_price' );
	            $specialty_access_days = carbon_get_theme_option( 'med_specialty_access_days' );

	            // Специальность вообще не продается или дней 0 стоит
	            if ( empty( $specialty_access_price ) || empty( $specialty_access_days ) ) {
		            wc_add_notice( "Специальность «{$data['title']}» не доступна к покупке.", 'error' );
		            unset( $cart_item['specialties_order'][ $specialty_id ] );
		            WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;
		            continue;
	            }
	            // Цена не соответствует
	            if ( $data['price'] != $specialty_access_price ) {
		            wc_add_notice( "Специальность «{$data['title']}» - изменилась цена. ", 'error' );
		            $cart_item['specialties_order'][ $specialty_id ]['price'] = $specialty_access_price;
		            WC()->cart->cart_contents[ $cart_item_id ]                = $cart_item;
	            }
	            // Длительность не соответствует
	            if ( $data['days'] != $specialty_access_days ) {
		            wc_add_notice( "Специальность «{$data['title']}» - изменилась длительность. ", 'error' );
		            $cart_item['specialties_order'][ $specialty_id ]['days'] = $specialty_access_days;
		            WC()->cart->cart_contents[ $cart_item_id ]               = $cart_item;
	            }
            }

            //Если специальностей в заказе вообще не осталось - удаляем товар из корзины
            if ( empty( $cart_item['specialties_order'] ) ) {
	            WC()->cart->remove_cart_item( $cart_item_id );
            }
		}

		WC()->cart->set_session();
	}

	public static function plural_days( $number ) {
		$endings = ['(%d день)', '(%d дня)', '(%d дней)'];
		$cases = [2, 0, 1, 1, 1, 2];
		$n = $number;
		return sprintf($endings[ ($n%100>4 && $n%100<20) ? 2 : $cases[min($n%10, 5)] ], $n);
	}

}