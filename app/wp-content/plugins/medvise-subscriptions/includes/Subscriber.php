<?php


namespace MedviseSubscriptions\Subscriber;


use MedviseSubscriptions\Woocommerce\Woocommerce;
use MedviseSubscriptions\ArticleAccess\ArticleAccess;
use MedviseSubscriptions\SpecialtyAccess\SpecialtyAccess;

class Subscriber {

	public function init() {
		add_action( 'init', [ $this, 'wp_init' ] );

		add_action( 'wp_head', [ $this, 'frontUserInfo' ] );

		// Поле емейл не обязательное
		add_action( 'user_profile_update_errors', [ $this, 'user_profile_update_errors' ], 10, 3 );

		add_filter( 'wcs_get_users_subscriptions', [ $this, 'wcs_get_users_subscriptions' ], 10, 2 );
	}

	public function wp_init() {
		if ( ! empty( $_POST['medsub_open_access'] ) ) {
			self::openAccess( $_POST['post_id'] );
		}
	}

    // Нужно для интеграции выпадающего поиска (ElasticPress)
	public function frontUserInfo() {
	    global $wpdb;

		$user = wp_get_current_user();

		$open_articles = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->prefix}medvise_page_views " .
			"WHERE user_id={$user->ID} AND date_expiry >= NOW();"
		);

        $open_specialties = $wpdb->get_col(
	        "SELECT specialty_id FROM {$wpdb->prefix}medvise_specialty_views " .
	        "WHERE user_id={$user->ID} AND date_expiry >= NOW();"
        );

		?>
        <script type="text/javascript">
            window.med_user = {
                id: <?= get_current_user_id() ?>,
                open_articles: <?= json_encode(array_map('intval', $open_articles)) ?>,
                open_specialties: <?= json_encode(array_map('intval', $open_specialties)) ?>
            };
        </script>
		<?php
	}

	public static function openAccess( $post_id ) {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			echo 'Вы не авторизованы';
			die();
		}

		$user_id         = get_current_user_id();
		$post            = get_post( $post_id );
		$views           = self::getViews( $user_id );
		$datetime_now    = date( 'Y-m-d H:i:s' );
		$datetime_inyear = date( 'Y-m-d H:i:s', strtotime( '+366 days' ) );

        // Открывать можно только заболевания
		if ( $post->post_type !== 'disease' ) {
			return false;
		}

		if ( $views['disease_views'] <= 0 ) {
			echo 'Недостаточно просмотров на балансе для открытия статьи';
			die();
		}

        // Проверяем, открывался ли доступ ранее
		$had_free_access = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}medvise_page_views WHERE user_id={$user_id} AND post_id={$post->ID} AND `source`='balance';"
		);

        // Доступ открывался ранее за "просмотры" - ничего не делаем
        if ( $had_free_access ) {
            return false;
        }

		//Минусуем кол-во просмотров с баланса
		update_user_meta( $user_id, 'disease_views', ( $views['disease_views'] - 1 ) );

		//Открываем доступ к статье
		$wpdb->insert(
			$wpdb->prefix . 'medvise_page_views',
			[
				'user_id'     => $user_id,
				'post_id'     => $post->ID,
				'source'      => 'balance',
				'date_open'   => $datetime_now,
				'date_expiry' => $datetime_inyear
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		return false;
	}

	public static function getSubscriptionsRaw( $user_id ) {

	    global $wpdb;

		$query = "SELECT * FROM `{$wpdb->prefix}medvise_subscriptions` WHERE user_id=%d ORDER BY `end_date` DESC;";

		$subscriptions = $wpdb->get_results( $wpdb->prepare( $query, [
			$user_id
		] ) );

		return $subscriptions;
	}

	public static function getViews( $user_id ) {

		$views = [];

		$views['disease_views']   = (int) get_user_meta( $user_id, 'disease_views', true );

		return $views;
	}

	public static function hasNotActivatedSubscriptionOrder( $user_id = NULL ) {
		$user = $user_id === NULL ? wp_get_current_user() : get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return false;
		}

		$completed_orders = wc_get_orders([
			'customer_id' => $user->ID,
			'status'      => 'completed',
			'limit'       => - 1,
		]);

		foreach ( $completed_orders as $order ) {
			// заказ не активирован
			if ( ! $order->get_meta( '_med_subscription_activated', true ) ) {
				// проверяем возможность активации заказа
				$order_items = $order->get_items();
				foreach ( $order_items as $order_item ) {
					$med_subscription_days = wc_get_order_item_meta( $order_item->get_id(), '_med_subscription_days', true );

					if ( ! empty($med_subscription_days) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	public static function hasAccess( $post ) {
		global $wpdb;

		//Разрешаем просмотр всех записей кроме заболеваний
		if ( ! in_array( $post->post_type, [ 'disease' ] ) ) {
			return true;
		}

		// Бесплатные статьи можно смотреть всем
		if (get_post_meta( $post->ID, 'ep_free', true) == 1) {
			return true;
		}

		// Клин. реки доступны всем
		if ( $post->post_type === 'disease' ) {
			$disease_article_type = wp_get_object_terms( $post->ID, 'article-type', [ 'fields' => 'id=>slug' ] );

			if ( in_array( 'clinical-guidelines', $disease_article_type ) ) {
				return true;
			}
		}

		//Не залогинен - нельзя смотреть
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		//Админы и авторы смотрят все
		if ( in_array( 'administrator', $user->roles ) ||
             in_array( 'author', $user->roles ) ||
		     in_array( 'editor', $user->roles ) ) {
			return true;
		}

		//Если есть доступ
		if ( self::hasSubscription( $post ) ) {
			return true;
		}

		// Проверяем, открыт ли доступ к статье
		$page_view_access = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}medvise_page_views " .
			"WHERE user_id={$user->ID} AND post_id={$post->ID} AND date_expiry >= NOW();"
		);

		// Доступ к статье есть
		if ( $page_view_access !== null ) {
			return true;
		}

		// Проверяем, открыт ли доступ к специальности
		$post_specialties        = wp_get_post_terms( $post->ID, 'specialty', [ 'fields' => 'ids' ] );
		$post_specialties_string = implode( ',', $post_specialties );

		$specialty_view_access = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}medvise_specialty_views " .
			"WHERE user_id={$user->ID} AND specialty_id IN ({$post_specialties_string}) AND date_expiry >= NOW();"
		);

		//Доступ есть
		if ( $specialty_view_access !== null ) {
			return true;
		}

		return false;
	}

	public static function hasSubscription( $post = null ) {

		if ( $post === null ) {
			global $post;
		}

		$user = wp_get_current_user();

		if ( ! $user ) {
			return FALSE;
		}

        if ( ! $post instanceof \WP_Post ) {
            return FALSE;
        }

		//Админы и авторы смотрят все
		if ( in_array( 'administrator', $user->roles ) ||
		     in_array( 'author', $user->roles ) ||
		     in_array( 'editor', $user->roles ) ) {
			return TRUE;
		}

        // Внутренняя подписка, вне Woocommerce Subscriptions. Используется для годового тарифа без рекуррентов вроде
		if ( Woocommerce::getActiveSubscription( $user->ID ) ) {
			return TRUE;
		}

        // Woocommerce subscriptions
		$woo_subscriptions = wcs_get_users_subscriptions( $user->ID );
		$post_specialties  = wp_get_post_terms( $post->ID, 'specialty', [ 'fields' => 'ids' ] );

		foreach ( $woo_subscriptions as $k => $woo_subscription ) {
			// Не активная подписка
			if ( ! $woo_subscription->has_status( [ 'active', 'pending-cancel' ] ) ) {
				continue;
			}
			// Позиции внутри подписки
			$woo_subscription_items = $woo_subscription->get_items();

			foreach ( $woo_subscription_items as $woo_subscription_item ) {
				$woo_subscription_item_specialties = carbon_get_post_meta( $woo_subscription_item->get_product_id(), 'subscription_specialties' );

                if ( empty( $woo_subscription_item_specialties ) ) {
                    continue;
                }

                // Получаем ID термов
                $woo_subscription_item_specialties = wp_list_pluck( $woo_subscription_item_specialties, 'id' );

				$intersection = array_intersect($woo_subscription_item_specialties, $post_specialties);

                // Есть доступ к специальности статьи
				if ( ! empty($intersection) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Получает дату истечения доступа к статье
	 */
	public static function getArticleExpiryDate( $user_id, $article_id ) {
		global $wpdb;

		if ( ! $user_id || ! $article_id ) {
			return null;
		}

        $post = get_post( $article_id );

		if ( self::hasSubscription( $post ) ) {
			return null;
		}

		// Ищем активированную статью с максимальной датой истечения
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT date_expiry
			FROM {$wpdb->prefix}medvise_page_views
			WHERE user_id = %d
			AND post_id = %d
			AND date_expiry > NOW()
			ORDER BY date_expiry DESC
			LIMIT 1",
			$user_id,
			$article_id
		) );

		return $result ? $result->date_expiry : null;
	}

	public static function getPostPreview($post) {
	    $previewLengthConfig = [
            // < 1000 symbols => 20% length
            1000 => 15,
            5000 => 10,
            25000 => 5,
            50000 => 1,
            1000000 => 1
        ];

	    $text = $post->post_content;
		$return = [
			'text'  => '',
			'style' => 0
		];

		// Смотрим, есть ли в тексте СЕО спойлер - если есть, обрезаем текст по нему
		$seodetails_tag_post_pos = strpos( $text, '<!-- /wp:medvise-seodetails/medvise -->' );
	    // Если текст содержит блок обрезки, то обрезаем по нему
		$seolimit_tag_post_pos = strpos( $text, '<!-- /wp:medvise-seolimit/medvise -->' );

        if ( $seodetails_tag_post_pos ) {
	        $seodetails_tag_post_pos += 39;

	        $return['text'] = substr( $text, 0, $seodetails_tag_post_pos );
	        $return['style'] = 1;

	        // Обрезанная часть текста
	        $truncated_text = substr( $text, $seodetails_tag_post_pos  );

	        // Парсим спойлеры верхнего уровня
	        $dom = new \DomDocument;
	        $dom->loadHTML( '<?xml encoding="UTF-8"> ' . $truncated_text);
	        $xpath = new \DomXPath($dom);
	        $elements = $xpath->query("//details[not(ancestor::details)]/summary");

	        // Добавляем спойлеры закрытые
	        foreach ( $elements as $element ) {
		        $return['text'] .= "\n" .
		                           '<details class="wp-block-details wp-block-details__locked" data-bs-toggle="tooltip" ' .
		                           'data-bs-placement="top" title="Оформите подписку, чтобы продолжить чтение" onclick="return false;">' .
		                           '<summary>' . $element->nodeValue . ' <i class="fa fa-lock" aria-hidden="true"></i></summary>' .
		                           '</details>';
	        }
        }
        elseif ( $seolimit_tag_post_pos ) {
	        $seolimit_tag_post_pos += 37;

	        $return['text'] = substr( $text, 0, $seolimit_tag_post_pos );
	        $return['style'] = 1;

	        // Обрезанная часть текста
	        $truncated_text = substr( $text, $seolimit_tag_post_pos  );

	        // Парсим спойлеры верхнего уровня
	        $dom = new \DomDocument;
	        $dom->loadHTML( '<?xml encoding="UTF-8"> ' . $truncated_text);
	        $xpath = new \DomXPath($dom);
	        $elements = $xpath->query("//details[not(ancestor::details)]/summary");

	        // Добавляем спойлеры закрытые
            foreach ( $elements as $element ) {
	            $return['text'] .= "\n" .
	                               '<details class="wp-block-details wp-block-details__locked" data-bs-toggle="tooltip" ' .
	                               'data-bs-placement="top" title="Оформите подписку, чтобы продолжить чтение" onclick="return false;">' .
	                               '<summary>' . $element->nodeValue . ' <i class="fa fa-lock" aria-hidden="true"></i></summary>' .
	                               '</details>';
            }
        }
        else {
	        //remove html comments
	        $text = preg_replace('/<!--(?:.*?)-->/', '', $text);

	        //remove details tags
	        $text = preg_replace('/<\/?details(?:.*?)>/', '', $text);

	        //replace summary tag to h3
	        $text = preg_replace('/<(\/?)summary/', '<$1h3', $text);

	        //replace figure & img
	        $imagePlaceholder = '<img src="' . MEDVISESUB_URL . 'assets/images/preview.jpg" class="image-placeholder">';
	        $text = preg_replace('/<figure(.*?)<\/figure>/', $imagePlaceholder, $text);
	        $text = preg_replace('/<img(.*?)>/', $imagePlaceholder, $text);

	        $textLength = mb_strlen($text);

	        $previewLength = 0;
	        foreach ($previewLengthConfig as $maxLength => $previewPercent) {
		        if ($textLength < $maxLength) {
			        $previewLength = round($textLength * $previewPercent / 100);
			        break;
		        }
	        }

	        $preview = mb_substr($text, 0, $previewLength);

	        //remove last broken tag
	        $preview = preg_replace('/<\/?[^>]*$/', '', $preview);

	        $return['text'] = force_balance_tags( $preview );
        }

		return $return;
    }

	public static function renderNoAccess( $post_id ) {
		global $current_user, $post;

        $telegram_account = \Medviselogin_Telegram::telegram_user_get_by_id( $current_user->ID );

		//Определяем тип статьи
		switch ($post->post_type) {
			case 'disease':
				$has_views = get_user_meta( $current_user->ID, 'disease_views', true );
				break;
			default:
				$has_views = false;
				break;
		}

		ob_start();
		?>
        <div class="post-preview">
			<?php
			$post_preview = self::getPostPreview( $post );
			echo $post_preview['text'];
			?>
        </div>

		<?php get_template_part( 'tpl/authors', null, ['shadow' => $post_preview['style'] !== 1 ] ); ?>

        <div class="subscribe-access subscribe-access_notop">

			<?php if ( ! is_user_logged_in() ):
				$move_to_url = '';
				if ( in_array( $post->post_type, [ 'disease', 'substance' ] ) ) {
					$move_to_url = '?move_to=' . urlencode( THEMESFLAT_PROTOCOL . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
				}
                ?>
				<h2>Ограниченный доступ</h2>

				<div>
                    Для просмотра статьи необходимо
                    <a href="/register/<?= $move_to_url; ?>">зарегистрироваться</a>
                    или
                    <a href="/login/<?= $move_to_url; ?>">авторизоваться</a>
                </div>

            <?php elseif ( $telegram_account === null && ! self::hasNotActivatedSubscriptionOrder() && self::available_for_award($current_user->ID) && ! $has_views  ): ?>

                <div>
                    Получите доступ к любой статье на выбор, <a href="/my-account/telegram-tab/">привязав к аккаунту</a> свой Telegram. <br>
                    Вы можете приобрести <a href="/subscribe/">полный</a> доступ к сайту или <a href="/subscribe/">отдельному разделу</a>
                </div>

                <div class="themesflat-buttons__wrap">
                    <a class="themesflat-button" href="/subscribe/" target="_blank">Приобрести подписку</a>
					<?php if ( carbon_get_post_meta( $post_id, 'med_article_payment_category_select' ) ): ?>
                        <a class="themesflat-button" href="/login/">
                            Открыть статью - <?= carbon_get_post_meta( $post_id, 'med_article_payment_category_select' ); ?>₽
                        </a>
					<?php endif; ?>
                </div>

			<?php elseif ( self::hasNotActivatedSubscriptionOrder() && ! self::hasSubscription( $post ) ): ?>

				<h2>Активируйте подписку</h2>
				<p>
					Вам необходимо активировать подписку в личном кабинете, в разделе <a href="/my-account/orders/">Заказы</a>.
				</p>

			<?php elseif ( $has_views && ! self::hasSubscription( $post ) ): ?>
				<h2>Важная информация</h2>
				<p>
                    Вы можете бесплатно открыть <strong><?= $has_views; ?></strong>
                    <?= self::russian_plural($has_views, 'статью', 'статьи', 'статей'); ?> на выбор.
                    Следующие статьи будут доступны только по <a href="/subscribe/">подписке</a>.
                    Открыть статью «<?= $post->post_title; ?>» в рамках бесплатного доступа?
				</p>

				<form method="post">
					<input type="hidden" name="medsub_open_access" value="1">
					<input type="hidden" name="post_id" value="<?= $post_id; ?>">
					<button class="themesflat-button" type="submit">Открыть статью</button>
				</form>
			<?php else: ?>
				<h2>Доступ к статье ограничен</h2>
				<div>
					Вы можете приобрести <a href="/subscribe/" target="_blank">полный</a> доступ к сайту или <a href="/subscribe/" target="_blank">отдельному разделу</a>.
				</div>

				<a class="themesflat-button" href="/subscribe/" target="_blank">Приобрести подписку</a><br>

				<?php ArticleAccess::renderArticlesPaymentButton( $post_id ); ?>

			<?php endif; ?>

		</div>

		<?php
		return ob_get_clean();
	}

	public static function addViews( $user_id, $views ) {

		$disease_views_cur = get_user_meta( $user_id, 'disease_views', true );
		if ( empty( $disease_views_cur ) ) {
			$disease_views_cur = 0;
		}

		$disease_views = $disease_views_cur + $views['disease_views'];
		update_user_meta( $user_id, 'disease_views', $disease_views );
	}

	public static function historyViews( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}medvise_page_views WHERE " .
			"user_id = {$user_id} ORDER BY `date_open`"
		);
	}

	public static function historySpecialties( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}medvise_specialty_views WHERE " .
			"user_id = {$user_id} ORDER BY `date_open`"
		);
	}

	public function user_profile_update_errors($errors, $update, $user) {
		$errors->remove('empty_email');
	}

	public function wcs_get_users_subscriptions( $subscriptions, $user_id ) {

		global $wp;

		// В ЛК скрываем подписки отмененные и удаленные
		if ( is_page( wc_get_page_id( 'myaccount' ) ) && isset( $wp->query_vars['subscriptions'] ) ) {
			foreach ( $subscriptions as $subscription_id => $subscription ) {
				if ( in_array( $subscription->get_status(), [ 'cancelled', 'trash' ] ) ) {
					unset( $subscriptions[ $subscription_id ] );
				}
			}
		}

		return $subscriptions;
	}

    public static function available_for_award( $user_id, $tg_user_id = null ) {
        global $wpdb;

        // Были ли открыты статьи
        $hasViews = self::historyViews( $user_id );
        if ( ! empty( $hasViews ) ) {
            return false;
        }

        // Были ли подписки на специальности
        $hasSpecialties = self::historySpecialties( $user_id );
        if ( ! empty( $hasSpecialties ) ) {
            return false;
        }

        // Есть ли подписка
        if ( Woocommerce::getActiveSubscription( $user_id ) ||
             wcs_user_has_subscription( $user_id, '', [ 'active', 'pending-cancel' ] ) ) {
            return false;
        }

        // Была ли телега привязана куда-то ранее
        if ( $tg_user_id ) {
            $tg_user_used = $wpdb->get_var(
	            $wpdb->prepare(
                    "SELECT COUNT(tg_user_id) FROM `telegram_historical` WHERE `tg_user_id` = %d;",
		            $tg_user_id
	            )
            );

            if ( ! empty( $tg_user_used) ) {
                return false;
            }
        }

        return true;
    }

	public static function award( $user_id ) {
		$views['disease_views'] = 1;

		Subscriber::addViews( $user_id, $views );
	}

    public static function russian_plural($numeric, $one, $two, $many)
    {
	    $numeric = (int) abs($numeric);
	    if ($numeric % 100 == 1 || ($numeric % 100 > 20) && ($numeric % 10 == 1)) {
		    return $one;
	    }
	    if ($numeric % 100 == 2 || ($numeric % 100 > 20) && ($numeric % 10 == 2)) {
		    return $two;
	    }
	    if ($numeric % 100 == 3 || ($numeric % 100 > 20) && ($numeric % 10 == 3)) {
		    return $two;
	    }
	    if ($numeric % 100 == 4 || ($numeric % 100 > 20) && ($numeric % 10 == 4)) {
		    return $two;
	    }
	    return $many;
    }
}