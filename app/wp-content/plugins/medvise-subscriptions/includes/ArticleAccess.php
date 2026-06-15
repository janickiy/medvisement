<?php


namespace MedviseSubscriptions\ArticleAccess;


class ArticleAccess {

	public function init() {

		// ajax добавление статей в корзину
		add_action( 'wp_ajax_nopriv_article_add_to_cart_ajax', [ $this, 'article_add_to_cart_ajax' ] );
		add_action( 'wp_ajax_article_add_to_cart_ajax', [ $this, 'article_add_to_cart_ajax' ] );

		// добавляем css класс товарам для возможности раздельной стилизации
		add_filter( 'woocommerce_cart_item_class', [ $this, 'articles_payment_cart_item_class' ], 10, 3 );

		// отображение статей в корзине
		add_filter( 'woocommerce_get_item_data', [ $this, 'articles_payment_order_display_field' ], 10, 2 );

		// ajax удаление статей из корзины
		add_action( 'wp_ajax_nopriv_article_remove_from_cart_ajax', [ $this, 'article_remove_from_cart_ajax' ] );
		add_action( 'wp_ajax_article_remove_from_cart_ajax', [ $this, 'article_remove_from_cart_ajax' ] );

		// отображение названия товара в корзине
		add_filter( 'woocommerce_cart_item_name', [ $this, 'articles_payment_cart_product_name' ], 100, 3 );

		// подсчет цены статей в чекауте
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'articles_payment_price_calc' ], 1000, 1 );

		// передаем данные о статьях в мету заказа
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'articles_payment_item_meta' ], 10, 4 );

		// отображение названия товара в заказе
		add_filter( 'woocommerce_order_item_name', [ $this, 'articles_payment_order_product_name' ], 10, 2 );

		// убираем ссылку на товар в табице отображения заказа
		add_filter( 'woocommerce_order_item_permalink', [ $this, 'articles_payment_disable_order_product_url' ], 10, 3 );

		// вывод списка статей в заказе
		add_filter( 'woocommerce_display_item_meta', [ $this, 'articles_payment_display_item_meta_filter' ], 10, 3 );

		// вывод названия товара в админке заказа
		add_action( 'woocommerce_before_order_item_line_item_html', [ $this, 'articles_payment_before_order_item_itemtype_html_action' ], 10, 3 );

		// вывод списка статей в админке заказа
		add_action( 'woocommerce_after_order_itemmeta', [ $this, 'articles_payment_after_order_itemmeta_action' ], 10, 3 );

		// Прячем мету с днями в админке заказа
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'admin_panel_hide_order_itemmeta' ], 10, 1 );

		// после оплаты открываем пользователю нужные статьи
		add_action( 'woocommerce_payment_complete', [ $this, 'articles_payment_woocommerce_payment_complete' ], 10, 2 );

		// Валидация корзины
		add_action( 'woocommerce_check_cart_items', [ $this, 'validate_all_cart_contents' ] );
	}

	// забираем ID товара из глобальной настройки Woocommerce -> "Покупка статей"
	public static function articles_payment_get_option_product_id() {
		$get_option = carbon_get_theme_option( 'med_article_payment_product' );
		$product_id = '';

		if ( ! empty( $get_option ) ) {
			$product_id = intval( $get_option[0]['id'] );
		}

		return $product_id;
	}

	public function article_add_to_cart_ajax() {
		$out  = '0';
		$post = get_post( $_POST['postID'], OBJECT, 'raw' );

		if ( $post && $post->post_type == 'disease' ) {

			// ID товара Оплата статьи
			$product_id = self::articles_payment_get_option_product_id();
			$article    = $post;

			$article_payment_category = carbon_get_post_meta( $article->ID, 'med_article_payment_category_select' );
            // Категория не выбрана у статьи - добавить в корзину нельзя
			if ( empty( $article_payment_category ) ) {
				echo '0';
				wp_die();
			}

			$article_price = $article_payment_category;

			if ( ! in_array( $product_id, array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {
				WC()->cart->add_to_cart( $product_id );
				$out = '1';
			}

			// Кол-во дней из глобальной настройки
			$article_access_days = carbon_get_theme_option( 'med_article_access_days' );
			if ( empty( $article_access_days ) ) {
				echo '0';
				wp_die();
			}


			$cart = WC()->cart->cart_contents;
			foreach ( $cart as $cart_item_id => $cart_item ) {

				if ( $product_id == $cart_item['product_id'] ) {

					if ( empty( $cart_item['articles_order'] ) ) {
						$cart_item['articles_order'] = array(
							strval( $article->ID ) => array(
								'post_id' => strval( $article->ID ),
								'title'   => $article->post_title,
								'price'   => $article_price,
								'days'    => $article_access_days
							),
						);
						$out = '1';
					} else {
						if ( ! array_key_exists( strval( $article->ID ), $cart_item['articles_order'] ) ) {
							$cart_item['articles_order'][ strval( $article->ID ) ] = array(
								'post_id' => strval( $article->ID ),
								'title'   => $article->post_title,
								'price'   => $article_price,
								'days'    => $article_access_days
							);
							$out = '1';
						}
					}

					WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;

				}

			}
			WC()->cart->set_session();

		}

		echo $out; 
		wp_die();
	}

	public function articles_payment_cart_item_class( $class, $values, $values_key ) {
		$product_id = self::articles_payment_get_option_product_id();

		if ( isset( $values[ 'product_id' ] ) && $values[ 'product_id' ] == $product_id ) {
			$class .= ' product-article';
		}

		return $class;
	}

	public function articles_payment_order_display_field( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['articles_order'] ) ) {

			$item_data = array();

			foreach ( $cart_item['articles_order'] as $article_id => $data ) {

				// если в заказе всего 1 статья не выводим кнопку
				if ( count( $cart_item['articles_order'] ) == 1 ) {
					$html = '';
				} else {
					$html = '<a href="#" class="remove js-remove-single-article" data-article-id="' . $article_id . '">×</a>  ';
				}

				$days = self::plural_days( intval( $data['days'] ) );

				$html .= '<a href="' . get_permalink( intval( $article_id ) ) . '">';
				$html .= $data['title'] . '</a> ' . $days . ' : ' . $data['price'] . ',00₽';

				$item_data[] = array(
					'key'     => 'medvise_article_access',
					'value'   => $data,
					'display' => $html,
				);
			}

		}
		return $item_data;
	}

	public function article_remove_from_cart_ajax() {
		$out        = '0';
		$article_id = $_POST['articleID'];
		$product_id = self::articles_payment_get_option_product_id();

		// товар из настроек добавлен в корзину
		if ( in_array( $product_id, array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {

			$cart = WC()->cart->cart_contents;
			foreach ( $cart as $cart_item_id => $cart_item ) {

				if ( $product_id == $cart_item['product_id'] ) {

					if ( ! empty( $cart_item['articles_order'] ) && array_key_exists( intval( $article_id ), $cart_item['articles_order'] ) ) {
						unset( $cart_item['articles_order'][intval( $article_id )] );
						$out = '1';

						\WC_Form_Handler::update_cart_action();
					}

					WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;
				}
			}

			WC()->cart->set_session();

		}

		echo $out; 
		wp_die();
	}

	public function articles_payment_cart_product_name( $item_name,  $cart_item,  $cart_item_key ) {

		// достаем нужный товар из глобальной настройки Woocommerce -> "Покупка статей"
		$product_id = self::articles_payment_get_option_product_id();
		if ( $product_id ) {

			// проверяем ID товара
			if ( $cart_item['product_id'] == $product_id ) {

				// считаем мету товара (статьи)
				if ( ! empty( $cart_item['articles_order'] ) && count( $cart_item['articles_order'] ) > 1 ) {
					$item_name = 'Открытие статей:';
				} else if ( ! empty( $cart_item['articles_order'] ) && count( $cart_item['articles_order'] ) == 1 ) {
					$item_name = 'Открытие статьи:';
				}
			}
		}

		return $item_name;
	}

	public function articles_payment_price_calc( $cart ) {
		// This is necessary for WC 3.0+
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Avoiding hook repetition (when using price calculations for example | optional)
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		// Loop through cart items
		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = self::articles_payment_get_option_product_id();

			if ( $cart_item['product_id'] == $product_id && ! empty( $cart_item['articles_order'] ) ) {
				$total_articles_price = 0;

				foreach ( $cart_item['articles_order'] as $article_id => $data ) {
					$total_articles_price += intval( $data['price'] );
				}

				$cart_item['data']->set_price( $total_articles_price );
			}
		}
	}

	public function articles_payment_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values[ 'articles_order' ] ) ) {
			foreach ( $values[ 'articles_order' ] as $article_id => $data ) {
				$item->add_meta_data( 'medvise_article_access', $data );
			}
		}
	}

	public function articles_payment_order_product_name( $product_name, $item ) {
		$product_id = self::articles_payment_get_option_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$item_meta_data = $item->get_meta( 'medvise_article_access', false );

			if ( ! empty( $item_meta_data ) && count( $item_meta_data ) > 1 ) {
				$product_name = 'Открытие статей:';
			} elseif ( ! empty( $item_meta_data ) && count( $item_meta_data ) == 1 ) {
				$product_name = 'Открытие статьи:';
			}
		}

		return $product_name;
	}

	public function articles_payment_disable_order_product_url( $product_url, $item, $order ) {
		$product_id = self::articles_payment_get_option_product_id();

		if ( $product_id ) {
			if ( $item->get_product_id() == $product_id ) {
				return false;
			}
		}
		return $product_url;
	}

	public function articles_payment_display_item_meta_filter( $html, $item, $args ) {
		$product_id = self::articles_payment_get_option_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$html      = '';
			$item_meta = $item->get_meta( 'medvise_article_access', false );
			if ( $item_meta ) {
				foreach ( $item_meta as $meta ) {
					$article_data = $meta->get_data();

					$days = self::plural_days( intval( $article_data['value']['days'] ) );

					$html .= '<div><a href="' . get_permalink( intval( $article_data['value']['post_id'] ) ) . '">';
					$html .= $article_data['value']['title'] . '</a> ' . $days . ' : ';
					$html .= strval( $article_data['value']['price'] ) . ',00₽</div>';
				}
			}
		}
		return $html;
	}

	public function articles_payment_before_order_item_itemtype_html_action( $item_id, $item, $order ) {
		$product_id = self::articles_payment_get_option_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$item_meta_data = $item->get_meta( 'medvise_article_access', false );

			if ( ! empty( $item_meta_data ) && count( $item_meta_data ) > 1 ) {
				$item->set_name( 'Открытие статей:' );
			} elseif ( ! empty( $item_meta_data ) && count( $item_meta_data ) == 1 ) {
				$item->set_name( 'Открытие статьи:' );
			}
		}
	}

	public function articles_payment_after_order_itemmeta_action( $item_id, $item, $null ) {
		$product_id = self::articles_payment_get_option_product_id();

		if ( $product_id && $item->get_product_id() == $product_id ) {

			$item_meta = $item->get_meta( 'medvise_article_access', false );
			if ( $item_meta ) {
				foreach ( $item_meta as $meta ) {
					$article_data = $meta->get_data();

					$days = self::plural_days( intval( $article_data['value']['days'] ) );

					$html = '<div><a href="' . get_edit_post_link( intval( $article_data['value']['post_id'] ) ) . '">';
					$html .= $article_data['value']['title'] . '</a> ' . $days . ' : ';
					$html .= strval( $article_data['value']['price'] ) . ',00₽</div>';

					echo $html;
				}
			}
		}
	}

	public function admin_panel_hide_order_itemmeta( $array ) {
	    $array[] = '_med_article_days';
		return $array;
	}

	public function articles_payment_woocommerce_payment_complete( $order_id, $transaction_id ) {
		global $wpdb;

		$order       = wc_get_order( $order_id );
		$product_id  = self::articles_payment_get_option_product_id();
		$customer_id = $order->get_customer_id();

		$option_days = carbon_get_theme_option( 'med_article_access_days' );
		if ( empty( $option_days ) ) {
			return;
		} else {
			$option_days = strval( $option_days );
		}

		foreach( $order->get_items() as $item_id => $item ) {

			if ( $product_id && $item->get_product_id() == $product_id ) {

				//Сохраняем кол-во дней подписки на момент оплаты за заказом
				if ( empty( wc_get_order_item_meta( $item->get_id(), '_med_article_days', true ) ) ) {

					//Закрепляем дни за товаром заказа
					wc_add_order_item_meta( $item->get_id(), '_med_article_days', $option_days, true );
				}

				$item_meta = $item->get_meta( 'medvise_article_access', false );
				if ( $item_meta ) {
					foreach ( $item_meta as $meta ) {
						$article_data     = $meta->get_data();
						$article_id       = $article_data['value']['post_id'];
						$datetime_now     = date( 'Y-m-d H:i:s' );
						$datetime_inmonth = date( 'Y-m-d H:i:s', strtotime( '+' . $option_days . ' days' ) );

						//Проверяем, есть ли уже доступ к этой статье
						$page_view_access = $wpdb->get_row(
							"SELECT * FROM {$wpdb->prefix}medvise_page_views " .
							"WHERE user_id={$customer_id} AND post_id={$article_id} AND date_expiry >= NOW();"
						);

						//Если доступа нет - предоставляем
						if ( $page_view_access === null ) {
							$wpdb->insert(
								$wpdb->prefix . 'medvise_page_views',
								[
									'user_id'     => $customer_id,
									'post_id'     => $article_id,
									'source'      => "order_{$order_id}_item_{$item_id}",
									'date_open'   => $datetime_now,
									'date_expiry' => $datetime_inmonth
								],
								[ '%d', '%d', '%s', '%s', '%s' ]
							);
						} else { // если есть доступ - продлеваем
							$date_open_update   = $page_view_access->date_open;
							$date_expiry_update = date( 'Y-m-d H:i:s', strtotime( 
								$page_view_access->date_expiry . ' +' . $option_days . ' days' 
							) );

							$wpdb->insert(
								$wpdb->prefix . 'medvise_page_views',
								[
									'user_id'     => $customer_id,
									'post_id'     => $article_id,
									'source'      => "order_{$order_id}_item_{$item_id}",
									'date_open'   => $date_open_update,
									'date_expiry' => $date_expiry_update
								],
								[ '%d', '%d', '%s', '%s', '%s' ]
							);
						}
					}
				}
			}
		}
	}

	// не выводим кнопку если статья уже в корзине
	public static function showArticlesPaymentButton( $post_id ) {
        $post = get_post($post_id);

		$return = false;

		$article_payment_category = carbon_get_post_meta( $post_id, 'med_article_payment_category_select' );

        // Пустая категория - кнопку не выводим
        if ( empty( $article_payment_category ) ) {
            return false;
        }

		// достаем ID товара оплаты статей из глобальной настройки Woocommerce -> "Покупка статей"
		$product_id = self::articles_payment_get_option_product_id();
		if ( $product_id ) {

			// проверяем наличие товара в корзине
			if ( in_array( $product_id, array_column( WC()->cart->get_cart(), 'product_id' ) ) ) {
				$article = $post;
				$cart    = WC()->cart->cart_contents;

				foreach ( $cart as $cart_item_id => $cart_item ) {
					// ловим нужный товар
					if ( $product_id == $cart_item['product_id'] ) {
						// в корзине нет статей
						if ( empty( $cart_item['articles_order'] ) ) {
							$return = 1;
						} else {
							// в корзине есть статьи, проверяем наличие текущей
							if ( array_key_exists( strval( $article->ID ), $cart_item['articles_order'] ) ) {
								$return = 2;
							}
						}
					}
				}
			} else {
                // товара нет в корзине, выводим кнопку
				$return = 1;
			}
		}

		return $return;
	}

	public static function renderArticlesPaymentButton( $post_id ) {

		$show_articles_payment_button = self::showArticlesPaymentButton( $post_id );

		if ( $show_articles_payment_button === 1 ) {
			?>
            <div>Либо, вы можете приобрести доступ к данной статье на 3 месяца.</div>
            <a class="themesflat-button js-buy-article-access" href="#" target=""
               data-post-id="<?php echo $post_id; ?>">
                Открыть статью - <?php echo carbon_get_post_meta( $post_id, 'med_article_payment_category_select' ); ?>₽
            </a>
			<?php
		} elseif ( $show_articles_payment_button === 2 ) {
			?>
            <br>Статья уже в корзине <a href="<?php echo wc_get_cart_url(); ?>" class="added_to_cart wc-forward"
                                        title="Просмотр корзины">просмотр корзины</a>
			<?php
		}
	}

	public function validate_all_cart_contents() {
		$product_id = self::articles_payment_get_option_product_id();
		$cart = WC()->cart->cart_contents;

		foreach ( $cart as $cart_item_id => $cart_item ) {

			if ( $product_id != $cart_item['product_id'] ) {
				continue;
			}

			// В товаре не заданы статьи
			if ( empty( $cart_item['articles_order'] ) ) {
				wc_add_notice( "Статья для покупки не выбрана. Пожалуйста, добавьте статью в корзину заново.", 'error' );
				WC()->cart->empty_cart();
				continue;
			}

			// Проверяем каждую статью на корректность цены и возможность покупки
			foreach ( $cart_item['articles_order'] as $article_id => $data ) {

				$article_access_price = carbon_get_post_meta( $article_id, 'med_article_payment_category_select' );
				$article_access_days = carbon_get_theme_option( 'med_article_access_days' );

				// Статья вообще не продается или дней 0 стоит
				if ( empty( $article_access_price ) || empty( $article_access_days ) ) {
					wc_add_notice( "Статья «{$data['title']}» не доступна к покупке.", 'error' );
					unset( $cart_item['articles_order'][ $article_id ] );
					WC()->cart->cart_contents[ $cart_item_id ] = $cart_item;
					continue;
				}
				// Цена не соответствует
				if ( $data['price'] != $article_access_price ) {
					wc_add_notice( "Статья «{$data['title']}» - изменилась цена. ", 'error' );
					$cart_item['articles_order'][ $article_id ]['price'] = $article_access_price;
					WC()->cart->cart_contents[ $cart_item_id ]                = $cart_item;
				}
				// Длительность не соответствует
				if ( $data['days'] != $article_access_days ) {
					wc_add_notice( "Статья «{$data['title']}» - изменилась длительность. ", 'error' );
					$cart_item['articles_order'][ $article_id ]['days'] = $article_access_days;
					WC()->cart->cart_contents[ $cart_item_id ]               = $cart_item;
				}
			}

			//Если статей в заказе вообще не осталось - удаляем товар из корзины
			if ( empty( $cart_item['articles_order'] ) ) {
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