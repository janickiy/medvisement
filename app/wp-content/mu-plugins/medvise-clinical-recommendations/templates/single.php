<?php

$medvise_clinical_recommendations = medvise_clinical_recommendations_frontend();
$context                          = $medvise_clinical_recommendations->get_single_context();

if ( empty( $context ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	include get_404_template();
	return;
}

$recommendation = $context['recommendation'];

get_header();
?>

<section class="med-cr-detail-page">
	<div class="container">
		<div class="med-cr-detail">
			<div class="med-cr-detail__nav">
				<a href="<?php echo esc_url( $context['archive_url'] ); ?>" class="med-cr-detail__back">
					<i class="fa-solid fa-arrow-left-long"></i>
					<span>К списку клинических рекомендаций</span>
				</a>
				<?php if ( ! empty( $recommendation['pdf_url'] ) ) : ?>
					<a href="<?php echo esc_url( $recommendation['pdf_url'] ); ?>" class="med-cr-detail__pdf" target="_blank" rel="noopener">
						<i class="fa-regular fa-file-pdf"></i>
						<span>Открыть PDF</span>
					</a>
				<?php endif; ?>
			</div>

			<header class="med-cr-detail__header">
				<span class="med-cr-detail__eyebrow">Клиническая рекомендация</span>
				<h1 class="med-cr-detail__title"><?php echo esc_html( $recommendation['name'] ); ?></h1>

				<div class="med-cr-detail__meta">
					<?php if ( ! empty( $recommendation['publish_year'] ) ) : ?>
						<span class="med-cr-badge med-cr-badge--year"><?php echo esc_html( $recommendation['publish_year'] ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $recommendation['age_category'] ) ) : ?>
						<span class="med-cr-badge med-cr-badge--age"><?php echo esc_html( $recommendation['age_category'] ); ?></span>
					<?php endif; ?>

					<span class="med-cr-badge med-cr-badge--date"><?php echo esc_html( $medvise_clinical_recommendations->format_publish_date( $recommendation['publish_date'] ) ); ?></span>
				</div>

				<?php if ( ! empty( $recommendation['specialties'] ) ) : ?>
					<div class="med-cr-detail__specialties">
						<?php foreach ( $recommendation['specialties'] as $specialty ) : ?>
							<a class="med-cr-tag" href="<?php echo esc_url( $specialty['url'] ); ?>"><?php echo esc_html( $specialty['name'] ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</header>

			<div class="med-cr-detail__content">
				<?php echo $recommendation['formatted_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
