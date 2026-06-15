<?php

$medvise_clinical_recommendations = medvise_clinical_recommendations_frontend();
$context                          = $medvise_clinical_recommendations->get_archive_context();

get_header();
?>

<section class="med-cr-page">
	<div class="med-cr-hero">
		<div class="container">
			<div class="med-cr-hero__inner">
				<h1 class="med-cr-hero__title">Клинические рекомендации</h1>
				<p class="med-cr-hero__text">Поиск по базе клинических рекомендаций Минздрава РФ и профильных сообществ.</p>

				<form class="med-cr-search js-med-cr-search search-form" action="<?php echo esc_url( $context['archive_url'] ); ?>" method="get">
					<label class="screen-reader-text" for="med-cr-search-input">Поиск клинических рекомендаций</label>
					<div class="med-cr-search__shell">
						<div class="med-cr-search__specialty">
							<i class="fa-solid fa-stethoscope"></i>
							<select name="cr_specialty" class="js-med-cr-specialty" aria-label="Специальность">
								<option value="">Все специальности</option>
								<?php foreach ( $context['specialties'] as $specialty ) : ?>
									<option
										value="<?php echo esc_attr( $specialty['slug'] ); ?>"
										data-term-id="<?php echo esc_attr( $specialty['term_id'] ); ?>"
										<?php selected( ! empty( $context['selected_specialty'] ) && (int) $context['selected_specialty']->term_id === (int) $specialty['term_id'] ); ?>
									>
										<?php echo esc_html( $specialty['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="med-cr-search__input-wrap">
							<i class="fa-solid fa-magnifying-glass"></i>
							<input
								type="search"
								id="med-cr-search-input"
								name="s"
								value="<?php echo esc_attr( $context['search'] ); ?>"
								class="js-med-cr-input"
								placeholder="Введите диагноз или ключевое слово"
								autocomplete="off"
							/>
						</div>

						<button type="submit" class="med-cr-search__submit">Найти</button>
					</div>

					<div class="med-cr-search__hidden">
						<label class="screen-reader-text">
							<input type="checkbox" name="section_name[]" value="disease_clinical-guidelines" checked="checked" />
						</label>
						<input
							type="hidden"
							class="js-med-cr-specialty-term-id"
							<?php if ( ! empty( $context['selected_specialty'] ) ) : ?>
								name="specialties[]"
							<?php endif; ?>
							value="<?php echo ! empty( $context['selected_specialty'] ) ? esc_attr( $context['selected_specialty']->term_id ) : ''; ?>"
						/>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="container">
		<div class="med-cr-layout">
			<aside class="med-cr-sidebar">
				<div class="med-cr-sidebar__card">
					<h2 class="med-cr-sidebar__title">Специальности</h2>

					<nav class="med-cr-sidebar__nav" aria-label="Список специальностей">
						<a class="med-cr-sidebar__link <?php echo empty( $context['selected_specialty'] ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $context['all_specialties_url'] ); ?>">
							<span class="med-cr-sidebar__name">Все специальности</span>
							<span class="med-cr-sidebar__count"><?php echo esc_html( $context['all_total'] ); ?></span>
						</a>

						<?php foreach ( $context['specialties'] as $specialty ) : ?>
							<a class="med-cr-sidebar__link <?php echo $specialty['active'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( $specialty['url'] ); ?>">
								<span class="med-cr-sidebar__name"><?php echo esc_html( $specialty['name'] ); ?></span>
								<span class="med-cr-sidebar__count"><?php echo esc_html( $specialty['count'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</nav>
				</div>
			</aside>

			<div class="med-cr-results">
				<div class="med-cr-results__head">
					<div>
						<h2 class="med-cr-results__title"><?php echo esc_html( $context['heading'] ); ?></h2>
						<p class="med-cr-results__subtitle"><?php echo esc_html( $context['subheading'] ); ?></p>
					</div>
				</div>

				<?php if ( empty( $context['items'] ) ) : ?>
					<div class="med-cr-empty">
						<h3>Подходящих рекомендаций пока нет</h3>
						<p>Попробуйте изменить поисковый запрос или выбрать другую специальность.</p>
					</div>
				<?php else : ?>
					<div class="med-cr-cards">
						<?php foreach ( $context['items'] as $item ) : ?>
							<article class="med-cr-card">
								<div class="med-cr-card__main">
									<h3 class="med-cr-card__title">
										<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
									</h3>

									<div class="med-cr-card__badges">
										<?php if ( ! empty( $item['publish_year'] ) ) : ?>
											<span class="med-cr-badge med-cr-badge--year"><?php echo esc_html( $item['publish_year'] ); ?></span>
										<?php endif; ?>

										<?php if ( ! empty( $item['age_category'] ) ) : ?>
											<span class="med-cr-badge med-cr-badge--age"><?php echo esc_html( $item['age_category'] ); ?></span>
										<?php endif; ?>

										<span class="med-cr-badge med-cr-badge--date"><?php echo esc_html( $medvise_clinical_recommendations->format_publish_date( $item['publish_date'] ) ); ?></span>
									</div>

									<?php if ( ! empty( $item['specialties'] ) ) : ?>
										<div class="med-cr-card__specialties">
											<?php foreach ( $item['specialties'] as $specialty ) : ?>
												<a class="med-cr-tag" href="<?php echo esc_url( $specialty['url'] ); ?>"><?php echo esc_html( $specialty['name'] ); ?></a>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>

								<div class="med-cr-card__actions">
									<?php if ( ! empty( $item['pdf_url'] ) ) : ?>
										<a class="med-cr-card__pdf" href="<?php echo esc_url( $item['pdf_url'] ); ?>" target="_blank" rel="noopener">
											<i class="fa-regular fa-file-pdf"></i>
											<span>PDF</span>
										</a>
									<?php endif; ?>
									<a class="med-cr-card__more" href="<?php echo esc_url( $item['permalink'] ); ?>">Открыть</a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<?php if ( ! empty( $context['pagination'] ) ) : ?>
						<div class="med-cr-pagination">
							<?php echo wp_kses_post( $context['pagination'] ); ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
