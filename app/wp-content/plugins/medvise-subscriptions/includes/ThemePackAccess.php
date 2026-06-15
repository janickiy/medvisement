<?php


namespace MedviseSubscriptions;

use MedviseSubscriptions\Subscriber\Subscriber;

class ThemePackAccess {

	public function init() {
		// Подключение JS скрипта
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Автоматическая активация статьи при просмотре
		add_action( 'template_redirect', [ $this, 'auto_activate_on_view' ], 5 );

		// После оплаты сохраняем покупку тарифа
		add_action( 'woocommerce_payment_complete', [ $this, 'theme_pack_save_access' ], 10, 2 );

		// Прячем служебные мета-поля в админке заказа
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'admin_panel_hide_order_itemmeta' ], 10, 1 );

		// Добавляем информацию о тарифе в заказ
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'order_item_meta' ], 10, 4 );

		// Вывод информации о статьях тарифа в заказе
		add_filter( 'woocommerce_display_item_meta', [ $this, 'order_product_items' ], 10, 3 );

		// Вывод информации в админке заказа
		add_action( 'woocommerce_after_order_itemmeta', [ $this, 'admin_panel_product_items' ], 10, 3 );

		// AJAX добавление тематического тарифа в корзину
		add_action( 'wp_ajax_theme_pack_add_to_cart_ajax', [ __CLASS__, 'ajax_add_to_cart' ] );
		add_action( 'wp_ajax_nopriv_theme_pack_add_to_cart_ajax', [ __CLASS__, 'ajax_add_to_cart' ] );
	}

	public function enqueue_scripts() {
		// AJAX добавление в корзину на странице подписок
		if ( is_shop() || is_page( 'subscribe' ) ) {
			wp_enqueue_script(
				'theme-pack-access',
				get_template_directory_uri() . '/js/themePackAccess.js',
				[ 'jquery', 'wc-add-to-cart' ],
				filemtime( get_template_directory() . '/js/themePackAccess.js' ),
				true
			);
		}

		// Живой счетчик на странице статьи
		if ( is_singular( [ 'disease' ] ) ) {
			wp_enqueue_script(
				'theme-pack-timer',
				get_template_directory_uri() . '/js/themePackTimer.js',
				[ 'jquery' ],
				filemtime( get_template_directory() . '/js/themePackTimer.js' ),
				true
			);
		}
	}

	/**
	 * Автоматическая активация статьи при просмотре
	 */
	public function auto_activate_on_view() {
		if ( ! is_singular( 'disease' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$post_id = get_the_ID();
		$user_id = get_current_user_id();

		self::activate_article( $user_id, $post_id );
	}

	/**
	 * Записываем доступ к статьям из тарифа при оплате
	 */
	public function theme_pack_save_access( $order_id, $transaction_id = null ) {
		global $wpdb;

		$order = wc_get_order( $order_id );
		$customer_id = $order->get_customer_id();

		if ( empty( $customer_id ) ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();

			// Получаем сохраненные данные из заказа
			$articles = $item->get_meta( '_theme_pack_articles', true );
			$duration_days = $item->get_meta( '_theme_pack_duration_days', true );

			// Если данные не сохранены, пытаемся получить из продукта
			if ( empty( $articles ) ) {
				$articles = carbon_get_post_meta( $product_id, 'theme_pack_articles' );
			}

			if ( empty( $duration_days ) ) {
				$duration_days = carbon_get_post_meta( $product_id, 'theme_pack_duration_days' );
			}

			// Если данных нет - пропускаем
			if ( empty( $articles ) || empty( $duration_days ) ) {
				continue;
			}

			// Записываем все статьи тарифа в page_views с date_expiry в прошлом (не активированы)
			// Используем специальную дату для неактивированных статей
			$not_activated_date = '1970-01-01 00:00:00';

			foreach ( $articles as $article ) {
				$article_id = $article['id'];

				$wpdb->insert(
					$wpdb->prefix . 'medvise_page_views',
					[
						'user_id'     => $customer_id,
						'post_id'     => $article_id,
						'source'      => "order_{$order_id}_item_{$item_id}",
						'date_open'   => $not_activated_date,
						'date_expiry' => $not_activated_date
					],
					[ '%d', '%d', '%s', '%s', '%s' ]
				);
			}
		}

	}

	/**
	 * Скрываем служебные мета-поля в админке
	 */
	public function admin_panel_hide_order_itemmeta( $array ) {
		$array[] = '_theme_pack_articles';
		$array[] = '_theme_pack_duration_days';
		return $array;
	}

	/**
	 * Сохраняем мета-данные тарифа при создании заказа
	 */
	public function order_item_meta( $item, $cart_item_key, $values, $order ) {
		$product_id = $item->get_product_id();

		// Получаем статьи из тарифа
		$articles = carbon_get_post_meta( $product_id, 'theme_pack_articles' );
		$duration_days = carbon_get_post_meta( $product_id, 'theme_pack_duration_days' );

		if ( empty( $articles ) || empty( $duration_days ) ) {
			return;
		}

		// Сохраняем список статей и длительность на момент покупки
		$item->add_meta_data( '_theme_pack_articles', $articles, true );
		$item->add_meta_data( '_theme_pack_duration_days', $duration_days, true );
	}

	/**
	 * Вывод списка статей в заказе (для пользователя)
	 */
	public function order_product_items( $html, $item, $args ) {

		$articles = $item->get_meta( '_theme_pack_articles', true );
		$duration_days = $item->get_meta( '_theme_pack_duration_days', true );

		if ( empty( $articles ) || empty( $duration_days ) ) {
			return $html;
		}

		$html = '<div class="theme-pack-info">';
		$html .= '<strong>Статей в тарифе:</strong> ' . count( $articles );

		$html .= ' <span style="color: #666;">(' . plural_russian( ['%d день', '%d дня', '%d дней'], intval( $duration_days ) ) . ')</span>';

		$html .= '<details style="margin-top: 10px;"><summary style="cursor: pointer;">Показать список статей</summary>';
		$html .= '<ul style="margin: 10px 0; padding-left: 20px;">';

		foreach ( $articles as $article ) {
			$article_id = $article['id'];
			$article_title = get_the_title( $article_id );
			$article_url = get_permalink( $article_id );

			$html .= '<li><a href="' . esc_url( $article_url ) . '" target="_blank">' . esc_html( $article_title ) . '</a></li>';
		}

		$html .= '</ul></details></div>';

		return $html;
	}

	/**
	 * Админка - вывод информации о тарифе
	 */
	public function admin_panel_product_items( $item_id, $item, $null ) {

		$articles = $item->get_meta( '_theme_pack_articles', true );
		$duration_days = $item->get_meta( '_theme_pack_duration_days', true );

		if ( empty( $articles ) || empty( $duration_days ) ) {
			echo '<div style="color: #999; font-style: italic;">Статьи не указаны</div>';
			return;
		}

		echo '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;">';
		echo '<strong>Тематический тариф:</strong> ' . count( $articles ) . ' ' . plural_russian( ['статья', 'статьи', 'статей'], count( $articles ) );

        echo ' (' . plural_russian( ['%d день', '%d дня', '%d дней'], intval( $duration_days ) ) . ')';

		echo '<details style="margin-top: 10px;"><summary style="cursor: pointer;">Список статей</summary>';
		echo '<ul style="margin: 10px 0; padding-left: 20px;">';

		foreach ( $articles as $article ) {
			$article_id = $article['id'];
			$article_title = get_the_title( $article_id );
			$edit_url = get_edit_post_link( $article_id );

			echo '<li><a href="' . esc_url( $edit_url ) . '" target="_blank">' . esc_html( $article_title ) . '</a></li>';
		}

		echo '</ul></details></div>';
	}

	/**
	 * AJAX обработчик добавления тематического тарифа в корзину
	 */
	public static function ajax_add_to_cart() {
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => 'Неверный ID продукта' ] );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( [ 'message' => 'Товар недоступен для покупки' ] );
		}

		// Проверяем, есть ли уже в корзине
		$already_in_cart = false;
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] == $product_id ) {
					$already_in_cart = true;
					break;
				}
			}
		}

		if ( $already_in_cart ) {
			wp_send_json_error( [ 'message' => 'Товар уже в корзине' ] );
		}

		// Добавляем в корзину
		$cart_item_key = WC()->cart->add_to_cart( $product_id );

		if ( $cart_item_key ) {
			wp_send_json_success( [
				'message'       => 'Товар добавлен в корзину',
				'cart_item_key' => $cart_item_key
			] );
		} else {
			wp_send_json_error( [ 'message' => 'Не удалось добавить товар в корзину' ] );
		}
	}

	/**
	 * Проверяет, есть ли у пользователя неактивированная статья в тематическом тарифе
	 * Возвращает данные для активации или false
	 */
	public static function is_not_activated_article( $user_id, $article_id ) {
		global $wpdb;

		// Ищем не активированную статью (date_expiry = 1970-01-01)
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"
				SELECT * FROM {$wpdb->prefix}medvise_page_views
				WHERE `user_id` = %d
				AND `post_id` = %d
				AND `date_expiry` = '1970-01-01 00:00:00'
				LIMIT 1",
				$user_id,
				$article_id
			)
		);

		if ( ! $result ) {
			return false;
		}

		preg_match_all( '/order_([0-9]+)_item_([0-9]+)/m', $result->source, $matches, PREG_SET_ORDER, 0 );

		if ( empty( $matches ) ) {
			return false;
		}

		$item_id  = $matches[0][2];

		$pack_duration_days = wc_get_order_item_meta( $item_id, '_theme_pack_duration_days', true );

		$result->duration_days = $pack_duration_days ? intval( $pack_duration_days ) : 365;

		return $result;
	}

	/**
	 * Активирует статью из тарифа при первом просмотре
	 */
	public static function activate_article( $user_id, $article_id ) {
		global $wpdb;

        $post = get_post( $article_id );

		// Если есть доступ - не активируем тариф
		if ( Subscriber::hasAccess( $post ) ) {
			return;
		}

		// Проверяем, есть ли неактивированная статья
		$not_activated = self::is_not_activated_article( $user_id, $article_id );

		if ( ! $not_activated ) {
			return;
		}

		// Активируем статью
		$datetime_now = wp_date( 'Y-m-d H:i:s' );
		$duration_days = intval( $not_activated->duration_days );

		$datetime_expiry = wp_date( 'Y-m-d H:i:s', strtotime( '+' . $duration_days . ' days' ) );

		$wpdb->update(
			$wpdb->prefix . 'medvise_page_views',
			[
				'date_open'   => $datetime_now,
				'date_expiry' => $datetime_expiry
			],
			[
				'id' => $not_activated->id,
			],
			[
				'%s',
				'%s'
			],
			[
				'%d'
			]
		);
	}

	/**
	 * Выводит кнопку добавления тематического тарифа в корзину
	 */
	public static function renderThemePackButton( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			echo 'Нет в наличии';
			return;
		}

		// Проверяем наличие товара в корзине
		$already_in_cart = false;
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] == $product_id ) {
					$already_in_cart = true;
					break;
				}
			}
		}

		if ( $already_in_cart ): ?>
            <a class="button" href="<?= wc_get_cart_url(); ?>" title="Просмотр корзины">
                В корзине
            </a>
		<?php else: ?>
            <a class="button js-buy-theme-pack" href="#" data-product-id="<?= $product_id; ?>">
                В корзину
            </a>
		<?php endif;
	}

}
