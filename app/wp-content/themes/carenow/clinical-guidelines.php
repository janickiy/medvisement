<?php
/**
 * Template Name: Клинические рекомендации
 * Template Post Type: page
 *
 * @package carenow
 */

get_header();

while ( have_posts() ) :
	the_post();

	$selected_specialty = medvise_get_selected_clinical_guidelines_specialty();
	$search_term        = medvise_get_clinical_guidelines_search_term();
	$current_page       = isset( $_GET['cr_page'] ) ? max( 1, absint( $_GET['cr_page'] ) ) : 1;

	$specialties_data = medvise_get_clinical_guidelines_specialties();
	$guidelines_query = medvise_get_clinical_guidelines_query( $selected_specialty, $search_term, $current_page );
	$page_url         = get_permalink();

	if ( $search_term !== '' ) {
		$results_heading  = 'Результаты поиска';
		$results_subtitle = sprintf(
			'По запросу «%1$s» найдено %2$d клинических рекомендаций',
			$search_term,
			(int) $guidelines_query->found_posts
		);
	} elseif ( $selected_specialty ) {
		$results_heading  = $selected_specialty->name;
		$results_subtitle = sprintf(
			'Найдено %d клинических рекомендаций',
			(int) $guidelines_query->found_posts
		);
	} else {
		$results_heading  = 'Последние обновления';
		$results_subtitle = 'Недавно добавленные или обновленные клинические рекомендации';
	}
	?>

    <div class="clinical-guidelines-page">
        <section class="clinical-guidelines-hero">
            <div class="container">
                <div class="clinical-guidelines-hero__inner">
                    <h1 class="clinical-guidelines-hero__title"><?php the_title(); ?></h1>
                    <p class="clinical-guidelines-hero__subtitle">
                        Поиск по базе клинических рекомендаций Минздрава РФ и профильных сообществ
                    </p>

                    <form class="search-form clinical-guidelines-search" action="<?php echo esc_url( $page_url ); ?>" method="get">
                        <input type="hidden" name="section_name[]" value="disease_clinical-guidelines">

                        <div class="clinical-guidelines-search__row">
                            <div class="clinical-guidelines-search__specialty">
                                <span class="clinical-guidelines-search__label">Специальность:</span>
                                <select
                                    name="cr_specialty"
                                    class="clinical-guidelines-search__select"
                                    aria-label="Специальность"
                                >
                                    <option value="">Не выбрана</option>
									<?php foreach ( $specialties_data['terms'] as $specialty ) : ?>
                                        <option
                                            value="<?php echo esc_attr( $specialty->slug ); ?>"
                                            data-term-id="<?php echo esc_attr( $specialty->term_id ); ?>"
											<?php selected( $selected_specialty ? $selected_specialty->slug : '', $specialty->slug ); ?>
                                        >
											<?php echo esc_html( $specialty->name ); ?>
                                        </option>
									<?php endforeach; ?>
                                </select>
                            </div>

                            <div class="clinical-guidelines-search__input-wrap">
                                <i class="fa-solid fa-magnifying-glass clinical-guidelines-search__icon" aria-hidden="true"></i>
                                <input
                                    type="search"
                                    name="cr_query"
                                    value="<?php echo esc_attr( $search_term ); ?>"
                                    class="clinical-guidelines-search__input"
                                    placeholder="Введите название диагноза или ключевое слово"
                                />
                                <div class="clinical-guidelines-search__specialties-hidden">
									<?php if ( $selected_specialty ) : ?>
                                        <input type="hidden" name="specialties[]" value="<?php echo esc_attr( $selected_specialty->term_id ); ?>">
									<?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="clinical-guidelines-search__submit">Найти</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="clinical-guidelines-content">
            <div class="container">
                <div class="clinical-guidelines-layout">
                    <aside class="clinical-guidelines-sidebar">
                        <div class="clinical-guidelines-sidebar__panel">
                            <h2 class="clinical-guidelines-sidebar__title">Специальности</h2>

                            <nav class="clinical-guidelines-sidebar__nav" aria-label="Фильтр по специальностям">
                                <a
                                    href="<?php echo esc_url( medvise_get_clinical_guidelines_page_url( [ 'cr_query' => $search_term ] ) ); ?>"
                                    class="clinical-guidelines-sidebar__link <?php echo $selected_specialty ? '' : 'is-active'; ?>"
                                    data-specialty-slug=""
                                >
                                    <span>Все специальности</span>
                                    <strong><?php echo esc_html( $specialties_data['total'] ); ?></strong>
                                </a>

								<?php foreach ( $specialties_data['terms'] as $specialty ) : ?>
                                    <a
                                        href="<?php echo esc_url( medvise_get_clinical_guidelines_page_url( [
	                                        'cr_specialty' => $specialty->slug,
	                                        'cr_query'     => $search_term,
                                        ] ) ); ?>"
                                        class="clinical-guidelines-sidebar__link <?php echo ( $selected_specialty && $selected_specialty->term_id === $specialty->term_id ) ? 'is-active' : ''; ?>"
                                        data-specialty-slug="<?php echo esc_attr( $specialty->slug ); ?>"
                                    >
                                        <span><?php echo esc_html( $specialty->name ); ?></span>
                                        <strong><?php echo esc_html( $specialty->clinical_guidelines_count ); ?></strong>
                                    </a>
								<?php endforeach; ?>
                            </nav>
                        </div>
                    </aside>

                    <div class="clinical-guidelines-results">
                        <div class="clinical-guidelines-results__header">
                            <h2 class="clinical-guidelines-results__title"><?php echo esc_html( $results_heading ); ?></h2>
                            <p class="clinical-guidelines-results__subtitle"><?php echo esc_html( $results_subtitle ); ?></p>
                        </div>

						<?php if ( $guidelines_query->have_posts() ) : ?>
                            <div class="clinical-guidelines-results__list">
								<?php
								while ( $guidelines_query->have_posts() ) :
									$guidelines_query->the_post();

									$pdf_url      = medvise_get_clinical_guideline_pdf_url( get_the_ID() );
									$status_badge = medvise_get_clinical_guideline_status_badge( get_the_ID() );
									$age_labels   = medvise_get_clinical_guideline_age_labels( get_the_ID() );
									?>

                                    <article class="clinical-guidelines-card">
                                        <div class="clinical-guidelines-card__main">
                                            <h3 class="clinical-guidelines-card__title">
                                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                            </h3>

                                            <div class="clinical-guidelines-card__badges">
                                                <span class="clinical-guidelines-badge is-year">
													<?php echo esc_html( medvise_get_clinical_guideline_year( get_the_ID() ) ); ?>
                                                </span>

												<?php foreach ( $age_labels as $age_label ) : ?>
                                                    <span class="clinical-guidelines-badge is-age">
														<?php echo esc_html( $age_label ); ?>
                                                    </span>
												<?php endforeach; ?>

												<?php if ( ! empty( $status_badge ) ) : ?>
                                                    <span class="clinical-guidelines-badge <?php echo esc_attr( $status_badge['class'] ); ?>">
														<?php echo esc_html( $status_badge['label'] ); ?>
                                                    </span>
												<?php endif; ?>
                                            </div>
                                        </div>

										<?php if ( $pdf_url ) : ?>
                                            <a
                                                class="clinical-guidelines-card__file"
                                                href="<?php echo esc_url( $pdf_url ); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                PDF
                                            </a>
										<?php endif; ?>
                                    </article>
								<?php endwhile; ?>
                            </div>

							<?php
							echo paginate_links( [
								'base'      => esc_url_raw( add_query_arg( 'cr_page', '%#%', remove_query_arg( 'cr_page' ) ) ),
								'format'    => '',
								'current'   => $current_page,
								'total'     => max( 1, (int) $guidelines_query->max_num_pages ),
								'prev_text' => 'Назад',
								'next_text' => 'Вперед',
								'type'      => 'list',
							] );
							?>
						<?php else : ?>
                            <div class="clinical-guidelines-empty">
                                <h3>Ничего не найдено</h3>
                                <p>Попробуйте изменить поисковый запрос или выбрать другую специальность.</p>
                            </div>
						<?php endif; ?>

						<?php wp_reset_postdata(); ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

	<?php
endwhile;

get_footer();
