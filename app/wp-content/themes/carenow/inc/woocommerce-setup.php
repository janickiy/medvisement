<?php /** @noinspection PhpUnreachableStatementInspection */

use MedviseSubscriptions\SpecialtyAccess\SpecialtyAccess;
use MedviseSubscriptions\ThemePackAccess;

function med_return_to_shop_text() {
	return 'Вернуться к тарифам';
}

add_filter( 'woocommerce_return_to_shop_text', 'med_return_to_shop_text' );


function med_empty_cart_message() {
	$shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );

	return "Ваша корзина пока пуста. Для выбора тарифов перейдите на страницу <a href='{$shop_page_url}'>Тарифы</a>.";
}

add_filter( 'wc_empty_cart_message', 'med_empty_cart_message' );


function med_related_products_title() {
	return 'Похожие тарифы';
}

add_filter( 'woocommerce_product_related_products_heading', 'med_related_products_title' );

function med_show_only_instock_products( $meta_query ) {
	if ( is_shop() || is_product() ) {
		$meta_query[] = array(
			'key'     => '_stock_status',
			'value'   => 'instock',
			'compare' => '='
		);
	}

	return $meta_query;
}

add_filter( 'woocommerce_product_query_meta_query', 'med_show_only_instock_products', 20 );

// Убираем сортировку
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

// Убираем количество
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );

// Переписываем шаблон неподдерживаемый для страницы магазина
function medvise_woo_shop_page_the_content( $content ) {

	if ( ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	if ( ! is_shop() ) {
		return $content;
	}

	ob_start();
	?>

    <p style="text-align: center">
        Мы оказываем услуги в виде предоставления медицинской информации для врачей.<br>
        Вы можете выбрать 2 варианта пользования сайтом.
    </p>

    <section class="sub-cards-simple">
        <div class="sub-card-simple">
            <div class="sub-card-simple__title">Бесплатный</div>
            <ul>
                <li>доступ к инструкциям по препаратам</li>
                <li>доступ к древу заболеваний и древу препаратов</li>
                <!--<li>доступ к тестам</li>
                <li>доступ к опросникам</li>-->
            </ul>
        </div>

        <div class="sub-card-simple">
            <div class="sub-card-simple__title">Платный</div>
            <ul>
                <li>все из бесплатной версии</li>
                <li>+ доступ к статьям по заболеваниям и препаратам</li>
            </ul>
        </div>
    </section>


	<?php
	$args = [
		'post_type'      => 'product',
		'limit'  => - 1,
		'status' => 'publish',
		'tax_query' => [
			[
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => [ 'subscription', 'variable-subscription', 'simple' ],
			],
			[
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => 'main-subscriptions'
			]
		],
		'order' => 'DESC',
		'orderby' => 'date'
	];
	$main_subscriptions_loop = new WP_Query( $args );

	$theme_pack_args = [
		'post_type'  => 'product',
		'limit'      => -1,
		'status'     => 'publish',
		'tax_query'  => [
			[
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => 'theme-packs'
			]
		],
		'order'      => 'DESC',
		'orderby'    => 'date'
	];
	$theme_pack_products = wc_get_products( $theme_pack_args );

	?>

    <section class="sub-tariffs">

        <h2 id="general-tariffs">Выберите желаемый тип доступа</h2>

        <div id="subscription-filter" class="vertical-buttons__wrapper">
            <div class="vertical-buttons__line">
                <div class="vertical-buttons__input">
                    <input type="radio" name="subtype-filter" id="subtype-filter_full" value="full">
                    <label for="subtype-filter_full">Полный</label>
                </div>
                <div class="vertical-buttons__input">
                    <input type="radio" name="subtype-filter" id="subtype-filter_specialty" value="specialty">
                    <label for="subtype-filter_specialty">По специальностям</label>
                </div>
				<?php if ( ! empty($theme_pack_products) ): ?>
                    <div class="vertical-buttons__input">
                        <input type="radio" name="subtype-filter" id="subtype-filter_theme-packs" value="theme-packs">
                        <label for="subtype-filter_theme-packs">По темам</label>
                    </div>
				<?php endif; ?>
            </div>
        </div>

        <div class="subscribe-type" data-subtype="full" style="display: none">

            <p style="padding-bottom:30px;font-size: 1.1rem;">
                Доступ ко всему сайту без ограничений
            </p>

            <div class="sub-tariffs__cards">
				<?php
				$i = 0;
				while ( $main_subscriptions_loop->have_posts() ) :
					$main_subscriptions_loop->the_post();
					global $product;

					// Получаем цену первого платежа
					$first_payment_price = $product->get_price();

					// Получаем правила цен для модального окна
					$product_price_rules = get_post_meta( $product->get_id(),
						'_fixed_subscription_discounts', true );
					$product_price_rules[1] = get_post_meta( $product->get_id(),
						'_subscription_price', true );
					ksort($product_price_rules);

					// Заполняем массив цен для таблицы в модальном окне
					$product_price_rules_filled = [];
					if ( $product->get_type() !== 'simple' ) {
						if ( \MeowCrew\SubscriptionsDiscounts\DiscountsManager::getDiscountsType( $product->get_id() ) === 'fixed' ) {
							// Получаем скидочные правила, добавляем цену первого периода
							$product_price_rules_filled    = array_replace( array_fill_keys( range( 1,
								array_key_last( $product_price_rules ) ), null ), $product_price_rules );

							// Заполняем пропуски в массиве
							foreach ( $product_price_rules_filled as $k => &$val ) {
								$next_key = ( $k + 1 );
								if ( array_key_exists( $next_key,
										$product_price_rules_filled ) && $product_price_rules_filled[ $next_key ] === null ) {
									$product_price_rules_filled[ $next_key ] = $val;
								}
							}
						}
					}
					?>
                    <div class="tariff-card">
						<?php if ( in_array( $product->get_type(), ['subscription', 'variable-subscription'] ) ): ?>
                            <div class="tariff-card__badge">Подписка</div>
						<?php endif; ?>
                        <h3 class="tariff-card__title"><?= $product->get_title(); ?></h3>
                        <div class="tariff-card__duration">
                            Полный доступ на
							<?php if ( $product->get_type() === 'simple' ): ?>
								<?php if ( get_post_meta( $product->get_id(), '_med_subscription_days', true ) < 300 ): ?>
                                    1 месяц
								<?php else: ?>
                                    1 год
								<?php endif; ?>
							<?php else: ?>
                                1 год
							<?php endif; ?>
                        </div>
                        <div class="tariff-card__price">
							<?php
							$custom_price = carbon_get_the_post_meta('product_custom_price');
							if ( ! empty($custom_price) ):
								echo $custom_price;
							else:
								echo $first_payment_price . '₽';
							endif;
							?>
                        </div>
						<?php $price_after_expiry = carbon_get_the_post_meta('product_price_after_expiry'); ?>
						<?php if ( ! empty($price_after_expiry) ): ?>
                            <div class="tariff-card__description">
                                <h4>После окончания доступа:</h4>
                                <p><?= $price_after_expiry; ?></p>
                            </div>
						<?php endif; ?>
                        <div class="tariff-card__actions">
                            <button type="button" class="tariff-card__details-btn" data-bs-toggle="modal"
                                    data-bs-target="#subscription-modal-<?= $i; ?>">
                                Подробнее
                            </button>

							<?php if ( is_user_logged_in() ): ?>
								<?php if ( $product->is_purchasable() && $product->is_in_stock() ): ?>
									<?php woocommerce_template_loop_add_to_cart(); ?>
								<?php else: ?>
                                    <span class="tariff-card__out-of-stock">Нет в наличии</span>
								<?php endif; ?>
							<?php else: ?>
                                <a href="/login" class="button">
                                    Войти <i class="fa-solid fa-right-to-bracket"></i>
                                </a>
							<?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="subscription-modal-<?= $i; ?>" tabindex="-1"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                                    <h5 class="modal-sub-title">Тариф</h5>
                                    <h5 class="modal-title"><?= $product->get_title(); ?></h5>
                                </div>
                                <div class="modal-body">
									<?= wpautop( $product->post->post_content ); ?>

									<?php if ( in_array( $product->get_type(),
										[ 'subscription', 'variable-subscription' ] ) ): ?>

										<?php if ( ! empty( $product_price_rules ) ): ?>

											<?php
											$subscription_has_long_periods = false;
											$previous_period               = 0;
											foreach ( $product_price_rules as $period => $price ) {
												if ( $period !== array_key_first( $product_price_rules ) && ( $period - 3 ) > $previous_period ) {
													$subscription_has_long_periods = true;
													break;
												}
												$previous_period = $period;
											}

											$price_table_items = $subscription_has_long_periods ? $product_price_rules : $product_price_rules_filled;
											?>

                                            <h2>График платежей</h2>
                                            <table class="shop_table">

                                                <thead>
                                                <tr>
                                                    <th>
                                                        <span class="nobr">Месяц</span>
                                                    </th>
                                                    <th>
                                                        <span>Цена</span>
                                                    </th>
                                                </tr>
                                                </thead>

                                                <tbody>
												<?php
												$iterator             = new ArrayIterator( $price_table_items );

												while ( $iterator->valid() ) :

													$current_price = $iterator->current();
													$current_quantity = $iterator->key();

													$iterator->next();

													if ( $iterator->valid() ) {
														$quantity = $current_quantity;

														if ( intval( $iterator->key() - 1 != $current_quantity ) ) {
															$quantity = number_format_i18n( $quantity ) . ' - ' . number_format_i18n( intval( $iterator->key() - 1 ) );
														}
													} else {
														$quantity = number_format_i18n( $current_quantity ) . ' и далее';
													}
													?>
                                                    <tr>
                                                        <td>
                                                            <span>
                                                                <?= $quantity; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                        <span data-price-rules-formated-price="">
                                                            <span class="woocommerce-Price-amount amount"><?= $current_price; ?><span class="woocommerce-Price-currencySymbol">₽</span>
                                                            </span>
                                                        </span>
                                                        </td>
                                                    </tr>
												<?php endwhile; ?>

                                                </tbody>
                                            </table>
										<?php endif; ?>

                                        <h2>Условия по оплате и продлению тарифа</h2>
                                        <p>
                                            1. При приобретении доступа на любой тариф из раздела «Подписки»,
                                            подключается автоматическое списание средств согласно графику платежей
                                            соответствующего тарифа.
                                        </p>
                                        <p>
                                            2. Автоматическое списание средств можно отменить в любой момент в личном
                                            кабинете
                                        </p>
										<?php if ( false ): ?>
                                            <p>
                                                3. Нажатием соответствующей кнопки в личном кабинете,
                                                действие тарифа можно заморозить на 28 календарных дней в течение года
                                                суммарно, но не более, чем за 4 раза.
                                            </p>
                                            <p>
                                                4. Заморозка тарифа – период, в течение которого доступ к сайту
                                                приостанавливается,
                                                следующее списание средств сдвигается на период заморозки.
                                            </p>
										<?php endif; ?>
                                        <p>
                                            3. При отмене ежемесячного автоматического списания (отмена подписки):<br>
                                            - после окончания последнего оплаченного периода, доступ к сайту закрывается<br>
                                            - продолжить подписку с того этапа, согласно графику, с которого она была
                                            отменена нельзя, но можно подписаться на тариф заново, то есть начать с первого платежного периода,
                                            согласно графику.
                                        </p>
                                        <p>
                                            4. Если очередное списание средств, согласно графику, не происходит вовремя из-за технических проблем,
                                            нехватки средств на балансе или любых других причин, не зависящих от нас, то:<br>
                                            - включается заморозка тарифа (см п.3), которая автоматически отключается при оплате тарифа,
                                            если оплата совершена в срок до 7 дней включительно от даты последнего запланированного платежа<br>
                                            - попытка очередного списания повторится на 3-й и на 7-й дни
                                        </p>
                                        <p>
                                            5. Если очередное списание средств, согласно графику, не происходит в течение более, чем 14 календарных дней подряд,
                                            то продолжить подписку с того этапа,
                                            с которого прервалась ежемесячная оплата нельзя, но можно подписаться на тариф заново,
                                            то есть начать с первого платежного периода, согласно графику.
                                        </p>
									<?php endif; ?>
                                </div>
                                <div class="modal-footer">
									<?php if ( is_user_logged_in() ): ?>
										<?php if ( $product->is_purchasable() && $product->is_in_stock() ): ?>
											<?php woocommerce_template_loop_add_to_cart(); ?>
										<?php else: ?>
                                            Нет в наличии
										<?php endif; ?>
									<?php else: ?>
                                        <a href="/login" class="button">Войти <i
                                                    class="fa-solid fa-right-to-bracket"></i></a>
									<?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
					$i ++;
				endwhile; ?>
            </div>
        </div>

		<?php wp_reset_query(); ?>

        <!-- ------------------------------------------------------------------------------------------------------ -->

        <div class="subscribe-type" data-subtype="specialty" style="display: none">

            <p style="padding-bottom:30px;font-size: 1.1rem;">
                Вы можете приобрести полный доступ к статьям по определенной специальности.<br>
                Если статья входит в несколько специальностей - достаточно приобрести доступ к одной специальности.
            </p>

            <div class="sub-tariffs__cards">
		        <?php
		        $specialty_terms = get_terms( [
			        'taxonomy'   => 'specialty',
			        'hide_empty' => true,
			        'meta_key'   => 'tax_position',
			        'orderby'    => 'tax_position'
		        ] );

		        foreach ( $specialty_terms as $specialty_term ):
			        $specialty_onetime = null;
			        $specialty_monthly = null;
			        $specialty_yearly = null;

			        $specialty_price = carbon_get_term_meta( $specialty_term->term_id, 'med_specialty_access_price' );

			        // Цена не задана - не продаем
			        if ( empty( $specialty_price ) ) {
				        continue;
			        }

			        // Разовая покупка - Возобновление подписки только вручную по «актуальной» цене
			        $specialty_onetime = [
				        'product_id'   => SpecialtyAccess::get_specialty_product_id(),
				        'specialty_id' => $specialty_term->term_id,
			        ];

			        // Ежемесячная - Ежемесячно продлевать подписку по «старой» цене
			        $specialty_monthly = carbon_get_term_meta( $specialty_term->term_id, 'med_specialty_access_product_month' );
			        if ( ! empty( $specialty_monthly ) ) {
				        $specialty_monthly = [
					        'product_id' => $specialty_monthly[0]['id'],
					        'product'      => wc_get_product( $specialty_monthly[0]['id'] ),
				        ];
			        }

			        // Ежегодно - Ежегодно продлевать подписку по «старой» цене
			        $specialty_yearly = carbon_get_term_meta( $specialty_term->term_id, 'med_specialty_access_product_year' );
			        if ( ! empty( $specialty_yearly ) ) {
				        $specialty_yearly = [
					        'product_id' => $specialty_yearly[0]['id'],
					        'product'      => wc_get_product( $specialty_yearly[0]['id'] ),
				        ];
			        }

			        ?>

                    <div class="tariff-card" data-specialty-id="<?= $specialty_term->term_id; ?>">
                        <h3 class="tariff-card__title"><?= $specialty_term->name; ?></h3>
                        <div class="tariff-card__duration">
                            Доступ на 1 год
                        </div>
                        <div class="tariff-card__price">
                            <p><?= $specialty_price; ?>₽</p>
                        </div>
                        <div class="tariff-card__options">
                            <h4>После окончания доступа:</h4>
	                        <?php
                            $was_specialty_option_checked = false;
                            if ( $specialty_yearly ): ?>
                                <label class="tariff-card__option">
                                    <input type="radio"
                                           name="<?= "specialty-{$specialty_term->term_id}-option"; ?>"
                                           value="yearly"
                                           data-product-id="<?= $specialty_yearly['product_id']; ?>" checked>
                                    <span>Ежегодно продлевать подписку по «старой» цене</span>
                                </label>
	                        <?php
	                            $was_specialty_option_checked = "yearly";
                            endif; ?>
	                        <?php if ( $specialty_monthly ): ?>
                                <label class="tariff-card__option">
                                    <input type="radio"
                                           name="<?= "specialty-{$specialty_term->term_id}-option"; ?>"
                                           value="monthly"
                                           data-product-id="<?= $specialty_monthly['product_id']; ?>"
	                                    <?= $was_specialty_option_checked ? '' : 'checked'; ?>>
                                    <span>Ежемесячно продлевать подписку по «старой» цене</span>
                                </label>
	                        <?php
		                        $was_specialty_option_checked = $was_specialty_option_checked === false ? "monthly" : $was_specialty_option_checked;
                            endif; ?>
	                        <?php if ( $specialty_onetime ): ?>
                                <label class="tariff-card__option">
                                    <input type="radio"
                                           name="<?= "specialty-{$specialty_term->term_id}-option"; ?>"
                                           value="onetime"
                                           data-product-id="<?= $specialty_onetime['product_id']; ?>"
                                           data-specialty-id="<?= $specialty_onetime['specialty_id']; ?>"
	                                    <?= $was_specialty_option_checked ? '' : 'checked'; ?>>
                                    <span>Возобновление подписки только вручную по «актуальной» цене</span>
                                </label>
	                        <?php
                                $was_specialty_option_checked = $was_specialty_option_checked === false ? "onetime" : $was_specialty_option_checked;
                            endif; ?>
                        </div>

                        <div class="tariff-card__actions">
                            <button type="button" class="tariff-card__details-btn" data-bs-toggle="modal"
                                    data-bs-target="#<?= "specialty-modal-{$specialty_term->term_id}-{$was_specialty_option_checked}"; ?>">
                                Подробнее
                            </button>

	                        <?php if ( is_user_logged_in() ): ?>
		                        <?php SpecialtyAccess::renderSpecialtyAccessButton( $specialty_term->term_id ); ?>
	                        <?php else: ?>
                                <a href="/login" class="button">
                                    Войти <i class="fa-solid fa-right-to-bracket"></i>
                                </a>
	                        <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $specialty_yearly ): ?>
			        <?php
			        // Получаем правила цен для модального окна
			        $product_price_rules    = get_post_meta( $specialty_yearly['product']->get_id(),
				        '_fixed_subscription_discounts', true );
			        $product_price_rules[1] = get_post_meta( $specialty_yearly['product']->get_id(),
				        '_subscription_price', true );
			        ksort( $product_price_rules );

			        // Заполняем массив цен для таблицы в модальном окне
			        $product_price_rules_filled = [];
			        if ( $specialty_yearly['product']->get_type() !== 'simple' ) {
				        if ( \MeowCrew\SubscriptionsDiscounts\DiscountsManager::getDiscountsType( $specialty_yearly['product']->get_id() ) === 'fixed' ) {
					        // Получаем скидочные правила, добавляем цену первого периода
					        $product_price_rules_filled = array_replace( array_fill_keys( range( 1,
						        array_key_last( $product_price_rules ) ), null ), $product_price_rules );

					        // Заполняем пропуски в массиве
					        foreach ( $product_price_rules_filled as $k => &$val ) {
						        $next_key = ( $k + 1 );
						        if ( array_key_exists( $next_key,
								        $product_price_rules_filled ) && $product_price_rules_filled[ $next_key ] === null ) {
							        $product_price_rules_filled[ $next_key ] = $val;
						        }
					        }
				        }
			        }
			        ?>
                    <div class="modal fade" id="specialty-modal-<?= $specialty_term->term_id; ?>-yearly"
                         tabindex="-1"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                                    <h5 class="modal-sub-title">Доступ к специальности</h5>
                                    <h5 class="modal-title"><?= $specialty_term->name; ?> (подписка)</h5>
                                </div>
                                <div class="modal-body">
                                    <ul>
                                        <li>При оплате данного тарифа вам откроется доступ к выбранной специальности до следующего списания средств согласно графику списаний.</li>
                                        <li>График списания средств: каждые 12 месяцев.</li>
                                        <li>Размер ежегодных списаний равен стоимости подписки на момент первичной оплаты. То есть цена для вас будет «заморожена».</li>
                                        <li>При неудачной попытке запланированного списания доступ к статьям по выбранной специальности будет закрыт.</li>
                                        <li>Всего будет несколько автоматических попыток списания. Запланированное списание можно активировать в личном кабинете вручную.</li>
                                        <li>Если в течение 14 дней после окончания последнего доступа оплата не пройдет, подписка отключится окончательно и продлить ее по «старой» цене будет невозможно.</li>
                                        <li>Если запланированное списание средств пройдет успешно в течение 14 дней после окончания последнего доступа, то доступ будет продлен до следующего списания и так далее.</li>
                                        <li>Автоматическое продление тарифа можно отключить в своем личном кабинете самостоятельно, либо можно написать на электронную почту в техподдержку info@medvisement.com</li>
                                    </ul>

		                            <?php if ( in_array( $specialty_yearly['product']->get_type(),
			                            [ 'subscription', 'variable-subscription' ] ) ): ?>

			                            <?php if ( ! empty( $product_price_rules ) ): ?>

				                            <?php
				                            $subscription_has_long_periods = false;
				                            $previous_period               = 0;
				                            foreach ( $product_price_rules as $period => $price ) {
					                            if ( $period !== array_key_first( $product_price_rules ) && ( $period - 3 ) > $previous_period ) {
						                            $subscription_has_long_periods = true;
						                            break;
					                            }
					                            $previous_period = $period;
				                            }

				                            $price_table_items = $subscription_has_long_periods ? $product_price_rules : $product_price_rules_filled;
				                            ?>

                                            <h2>График платежей</h2>
                                            <table class="shop_table">

                                                <thead>
                                                <tr>
                                                    <th>
                                                        <span class="nobr">
                                                            <?php
                                                            if ( 'year' === $specialty_yearly['product']->get_meta('_subscription_period', true) ) {
                                                                echo 'Год';
                                                            }
                                                            elseif ( 'month' === $specialty_yearly['product']->get_meta('_subscription_period', true) ) {
                                                                echo 'Месяц';
                                                            }
                                                            elseif ( 'week' === $specialty_yearly['product']->get_meta('_subscription_period', true) ) {
                                                                echo 'Неделя';
                                                            }
                                                            else {
                                                                echo 'День';
                                                            }
                                                            ?>
                                                        </span>
                                                    </th>
                                                    <th>
                                                        <span>Цена</span>
                                                    </th>
                                                </tr>
                                                </thead>

                                                <tbody>
					                            <?php
					                            $iterator             = new ArrayIterator( $price_table_items );

					                            while ( $iterator->valid() ) :

						                            $current_price = $iterator->current();
						                            $current_quantity = $iterator->key();

						                            $iterator->next();

						                            if ( $iterator->valid() ) {
							                            $quantity = $current_quantity;

							                            if ( intval( $iterator->key() - 1 != $current_quantity ) ) {
								                            $quantity = number_format_i18n( $quantity ) . ' - ' . number_format_i18n( intval( $iterator->key() - 1 ) );
							                            }
						                            } else {
							                            $quantity = number_format_i18n( $current_quantity ) . ' и далее';
						                            }
						                            ?>
                                                    <tr>
                                                        <td>
                                                            <span>
                                                                <?= $quantity; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                        <span data-price-rules-formated-price="">
                                                            <span class="woocommerce-Price-amount amount"><?= $current_price; ?><span class="woocommerce-Price-currencySymbol">₽</span>
                                                            </span>
                                                        </span>
                                                        </td>
                                                    </tr>
					                            <?php endwhile; ?>

                                                </tbody>
                                            </table>
			                            <?php endif; ?>
		                            <?php endif; ?>

                                    <h2>Условия по оплате и продлению тарифа</h2>
                                    <p>1. При приобретении доступа подключается автоматическое списание средств согласно графику платежей.</p>
                                    <p>2. Автоматическое списание средств можно отменить в любой момент в личном кабинете.</p>
                                </div>
                                <div class="modal-footer"></div>
                            </div>
                        </div>
                    </div>
		        <?php endif; ?>

			        <?php if ( $specialty_monthly ): ?>
			        <?php
                    // Получаем правила цен для модального окна
			        $product_price_rules    = get_post_meta( $specialty_monthly['product']->get_id(),
				        '_fixed_subscription_discounts', true );
			        $product_price_rules[1] = get_post_meta( $specialty_monthly['product']->get_id(),
				        '_subscription_price', true );
			        ksort( $product_price_rules );

                    // Заполняем массив цен для таблицы в модальном окне
			        $product_price_rules_filled = [];
			        if ( $specialty_monthly['product']->get_type() !== 'simple' ) {
				        if ( \MeowCrew\SubscriptionsDiscounts\DiscountsManager::getDiscountsType( $specialty_monthly['product']->get_id() ) === 'fixed' ) {
					        // Получаем скидочные правила, добавляем цену первого периода
					        $product_price_rules_filled = array_replace( array_fill_keys( range( 1,
						        array_key_last( $product_price_rules ) ), null ), $product_price_rules );

					        // Заполняем пропуски в массиве
					        foreach ( $product_price_rules_filled as $k => &$val ) {
						        $next_key = ( $k + 1 );
						        if ( array_key_exists( $next_key,
								        $product_price_rules_filled ) && $product_price_rules_filled[ $next_key ] === null ) {
							        $product_price_rules_filled[ $next_key ] = $val;
						        }
					        }
				        }
			        }
			        ?>
                    <div class="modal fade" id="specialty-modal-<?= $specialty_term->term_id; ?>-monthly"
                         tabindex="-1"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                                    <h5 class="modal-sub-title">Доступ к специальности</h5>
                                    <h5 class="modal-title"><?= $specialty_term->name; ?> (подписка)</h5>
                                </div>
                                <div class="modal-body">
                                    <ul>
                                        <li>При оплате данного тарифа вам откроется доступ к выбранной специальности до следующего списания средств согласно графику списаний.</li>
                                        <li>График списаний: списания начнутся через 12 месяцев после оплаты (на 13-й месяц) и с тех пор будут происходить ежемесячно.</li>
                                        <li>Размер ежемесячных списаний равен стоимости годовой подписки на момент первичной оплаты, поделенной на 12. То есть цена для вас будет «заморожена» и разбита на 12 ежемесячных платежей.</li>
                                        <li>При неудачной попытке запланированного списания доступ к статьям по выбранной специальности будет закрыт.</li>
                                        <li>Всего будет несколько автоматических попыток списания. Запланированное списание можно активировать в личном кабинете вручную.</li>
                                        <li>Если в течение 5 дней после окончания последнего доступа оплата не пройдет, подписка отключится окончательно и продлить ее по «старой» цене будет невозможно.</li>
                                        <li>Если запланированное списание средств пройдет успешно в течение 5 дней после окончания последнего доступа, то доступ будет продлен до следующего списания и так далее.</li>
                                        <li>Автоматическое продление тарифа можно отключить в своем личном кабинете самостоятельно, либо можно написать на электронную почту в техподдержку info@medvisement.com</li>
                                    </ul>

	                                <?php if ( in_array( $specialty_monthly['product']->get_type(),
		                                [ 'subscription', 'variable-subscription' ] ) ): ?>

		                                <?php if ( ! empty( $product_price_rules ) ): ?>

			                                <?php
			                                $subscription_has_long_periods = false;
			                                $previous_period               = 0;
			                                foreach ( $product_price_rules as $period => $price ) {
				                                if ( $period !== array_key_first( $product_price_rules ) && ( $period - 3 ) > $previous_period ) {
					                                $subscription_has_long_periods = true;
					                                break;
				                                }
				                                $previous_period = $period;
			                                }

			                                $price_table_items = $subscription_has_long_periods ? $product_price_rules : $product_price_rules_filled;
			                                ?>

                                            <h2>График платежей</h2>
                                            <table class="shop_table">

                                                <thead>
                                                <tr>
                                                    <th>
                                                        <span class="nobr">
                                                            <?php
                                                            if ( 'year' === $specialty_monthly['product']->get_meta( '_subscription_period', true ) ) {
                                                                echo 'Год';
                                                            } elseif ( 'month' === $specialty_monthly['product']->get_meta( '_subscription_period', true ) ) {
                                                                echo 'Месяц';
                                                            } elseif ( 'week' === $specialty_monthly['product']->get_meta( '_subscription_period', true ) ) {
                                                                echo 'Неделя';
                                                            } else {
                                                                echo 'День';
                                                            }
                                                            ?>
                                                        </span>
                                                    </th>
                                                    <th>
                                                        <span>Цена</span>
                                                    </th>
                                                </tr>
                                                </thead>

                                                <tbody>
				                                <?php
				                                $iterator             = new ArrayIterator( $price_table_items );

				                                while ( $iterator->valid() ) :

					                                $current_price = $iterator->current();
					                                $current_quantity = $iterator->key();

					                                $iterator->next();

					                                if ( $iterator->valid() ) {
						                                $quantity = $current_quantity;

						                                if ( intval( $iterator->key() - 1 != $current_quantity ) ) {
							                                $quantity = number_format_i18n( $quantity ) . ' - ' . number_format_i18n( intval( $iterator->key() - 1 ) );
						                                }
					                                } else {
						                                $quantity = number_format_i18n( $current_quantity ) . ' и далее';
					                                }
					                                ?>
                                                    <tr>
                                                        <td>
                                                            <span>
                                                                <?= $quantity; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                        <span data-price-rules-formated-price="">
                                                            <span class="woocommerce-Price-amount amount"><?= $current_price; ?><span class="woocommerce-Price-currencySymbol">₽</span>
                                                            </span>
                                                        </span>
                                                        </td>
                                                    </tr>
				                                <?php endwhile; ?>

                                                </tbody>
                                            </table>
		                                <?php endif; ?>
	                                <?php endif; ?>

                                    <h2>Условия по оплате и продлению тарифа</h2>
                                    <p>1. При приобретении доступа подключается автоматическое списание средств согласно графику платежей.</p>
                                    <p>2. Автоматическое списание средств можно отменить в любой момент в личном кабинете.</p>
                                </div>
                                <div class="modal-footer"></div>
                            </div>
                        </div>
                    </div>
		        <?php endif; ?>

			        <?php if ( $specialty_onetime ): ?>
                    <div class="modal fade" id="specialty-modal-<?= $specialty_term->term_id; ?>-onetime"
                         tabindex="-1"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                                    <h5 class="modal-sub-title">Доступ к специальности (подписка)</h5>
                                    <h5 class="modal-title"><?= $specialty_term->name; ?></h5>
                                </div>
                                <div class="modal-body">
                                    <p>
                                        Однократный платеж – <?= $specialty_price; ?> руб.<br>
                                        Открывается доступ к специальности
                                        «<a href="<?= get_term_link( $specialty_term->term_id ); ?>"
                                            target="_blank"><?= $specialty_term->name; ?></a>»
                                        на 365 дней с момента оплаты.<br>
                                        Отсутствует возможность заморозки и автоматического списания средств.
                                    </p>
                                </div>
                                <div class="modal-footer"></div>
                            </div>
                        </div>
                    </div>
		        <?php endif; ?>

		        <?php endforeach; ?>
            </div>

        </div>

        <!-- ------------------------------------------------------------------------------------------------------ -->

        <div class="subscribe-type" data-subtype="theme-packs" style="display: none">

            <p style="padding-bottom:30px;font-size: 1.1rem;">
                Вы можете приобрести доступ к набору статей, объединенных по тематике.<br>
                Каждая статья активируется при первом просмотре, доступ начинается с момента активации.
            </p>

            <div class="sub-tariffs__cards">
		        <?php foreach ( $theme_pack_products as $_product ):
			        global $product;
			        $product = $_product;

			        $articles       = carbon_get_post_meta( $product->get_id(), 'theme_pack_articles' );
			        $duration_days  = carbon_get_post_meta( $product->get_id(), 'theme_pack_duration_days' );
			        $articles_count = is_array( $articles ) ? count( $articles ) : 0;
			        ?>
                    <div class="tariff-card">
                        <div class="tariff-card__title">
	                        <?= $product->get_name(); ?>
                        </div>

                        <div class="tariff-card__duration">
                            Единовременная оплата:
                        </div>

                        <div class="tariff-card__price">
	                        <?= $product->get_price(); ?>₽
                        </div>

                        <div class="tariff-card__description">
                            <h4>Количество статей</h4>
                            <p><?= $articles_count; ?> <?= plural_russian( ['статья', 'статьи', 'статей'], $articles_count ); ?></p>
                            <h4>Срок доступа к каждой статье</h4>
                            <p><?= $duration_days; ?> <?= plural_russian( ['день', 'дня', 'дней'], $duration_days ); ?> с момента открытия</p>
                        </div>

				        <?php $product_card_points = carbon_get_post_meta( $product->get_id(), 'product_card_points' ); ?>
				        <?php if ( ! empty( $product_card_points ) ): ?>
                            <ul>
						        <?php foreach ( $product_card_points as $product_card_point ): ?>
                                    <li><?= $product_card_point['point_text']; ?></li>
						        <?php endforeach; ?>
                            </ul>
				        <?php endif; ?>

                        <div class="tariff-card__actions">
                            <button type="button" class="tariff-card__details-btn" data-bs-toggle="modal"
                                    data-bs-target="#theme-pack-modal-<?= $product->get_id(); ?>">
                                Подробное описание
                            </button>

					        <?php if ( is_user_logged_in() ): ?>
						        <?php ThemePackAccess::renderThemePackButton( $product->get_id() ); ?>
					        <?php else: ?>
                                <a href="/login" class="button">Войти <i
                                            class="fa-solid fa-right-to-bracket"></i></a>
					        <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="theme-pack-modal-<?= $product->get_id(); ?>"
                         tabindex="-1"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                                    <h5 class="modal-sub-title">Тематический тариф</h5>
                                    <h5 class="modal-title"><?= $product->get_name(); ?></h5>
                                </div>
                                <div class="modal-body">
							        <?= wpautop( $product->get_description() ); ?>

                                    <h2>Статьи в тарифе</h2>
							        <?php if ( ! empty( $articles ) ): ?>
                                        <ul style="margin: 10px 0 32px; padding-left: 20px;">
									        <?php foreach ( $articles as $article ):
										        $article_id = $article['id'];
										        $article_title = get_the_title( $article_id );
										        $article_url = get_permalink( $article_id );
										        ?>
                                                <li>
                                                    <a href="<?= esc_url( $article_url ); ?>" target="_blank">
												        <?= esc_html( $article_title ); ?>
                                                    </a>
                                                </li>
									        <?php endforeach; ?>
                                        </ul>
							        <?php else: ?>
                                        <p>Статьи не указаны</p>
							        <?php endif; ?>

                                    <h2>Условия</h2>
                                    <p>
                                        Однократный платеж – <?= $product->get_price(); ?> ₽.<br>
                                        Открывается доступ к <?= $articles_count; ?> <?= plural_russian( ['статье', 'статьям', 'статьям'], $articles_count ); ?>.<br>
                                        Доступ к каждой статье: <?= plural_russian( ['%d день', '%d дня', '%d дней'], $duration_days ); ?> с момента первого открытия.<br>
                                        Статьи активируются автоматически при первом просмотре.
                                    </p>
                                </div>

                            </div>
                        </div>
                    </div>
		        <?php endforeach; ?>
            </div>
        </div>

    </section>

	<?php
	return ob_get_clean();
}

add_filter( 'the_content', 'medvise_woo_shop_page_the_content', 11, 1 );

// Запрещаем добавлять в корзину, если не авторизован
function medvise_add_to_cart_validation( $passed, $product_id, $quantity ) {

	if ( ! is_user_logged_in() ) {
		wc_add_notice( __( 'Пожалуйста, сначала войдите в аккаунт.' ), 'error' );

		$passed = FALSE;
	}

	return $passed;
}

add_filter( 'woocommerce_add_to_cart_validation', 'medvise_add_to_cart_validation', 20, 3 );

// Отключаем страницу корзины для не авторизованных
function medvise_cart_redirect(){

	if ( is_cart() && ! is_user_logged_in() ) {
		wp_safe_redirect( get_site_url() . '/subscribe/' );
		exit();
	}

}

add_action( 'template_redirect', 'medvise_cart_redirect' );

// Отключаем ссылки на продукт
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
add_filter( 'woocommerce_cart_item_permalink', function () {
	return get_permalink( wc_get_page_id( 'shop' ) );
} );

// Отключаем страницы продуктов
function medvise_hide_product_page( $args ) {
	$args["publicly_queryable"] = FALSE;
	$args["public"]             = FALSE;

	return $args;
}

add_filter( 'woocommerce_register_post_type_product', 'medvise_hide_product_page', 12, 1 );

// Поля на при оформлении заказа
function medvise_woocommerce_checkout_fields( $fields ) {

	unset($fields['billing']['billing_country']);
	unset($fields['billing']['billing_phone']);

	return $fields;
}

add_filter( 'woocommerce_checkout_fields' , 'medvise_woocommerce_checkout_fields' );

// Предупреждение при ошибке оплаты
function medvise_message_error_during_payment() {
	?>
    <p style="padding-bottom:20px;font-weight:500;font-size: 1.1rem;">
        В случае проблем с оплатой, напишите нам на почту
        <a href="mailto:info@medvisement.com">info@medvisement.com</a>
    </p>
	<?php
}
add_action( 'woocommerce_review_order_before_submit', 'medvise_message_error_during_payment', 10 );
add_action( 'woocommerce_cart_collaterals', 'medvise_message_error_during_payment', 1 );