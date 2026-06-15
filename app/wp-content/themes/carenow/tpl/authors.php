<?php
global $post;

$med_article_authors     = carbon_get_the_post_meta( 'med_article_authors' );
$med_article_editors     = carbon_get_the_post_meta( 'med_article_editors' );
$med_article_translators = carbon_get_the_post_meta( 'med_article_translators' );
$med_article_specialties = get_the_terms( $post->ID, 'specialty' );

//Убираем пустые значения
foreach ($med_article_authors as $k => $med_article_author) {
	if ($med_article_author == 0)
		unset($med_article_authors[$k]);
}
foreach ($med_article_editors as $k => $med_article_editor) {
	if ($med_article_editor == 0)
		unset($med_article_editors[$k]);
}
foreach ($med_article_translators as $k => $med_article_translator) {
	if ($med_article_translator == 0)
		unset($med_article_translators[$k]);
}

?>

<?php if ( ! empty( $med_article_authors ) || ! empty( $med_article_editors ) || ! empty( $med_article_specialties ) || ! empty( $med_article_translators ) ): ?>
	<div class="author-post <?= empty($args['shadow']) ? '' : 'author-post__content-shadow'; ?>">
		<div class="author-body clearfix">
			<div class="row">
				<?php if (!empty($med_article_authors)): ?>
					<div class="col-12 col-lg-6">
						<div class="author-post__type">Авторы:</div>
						<ul>
							<?php foreach ($med_article_authors as $k => $med_article_author): ?>
								<li>
									<a href="<?= get_author_posts_url($med_article_author); ?>"
									   target="_blank">
										<?= get_the_author_meta('display_name', $med_article_author); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if (!empty($med_article_editors)): ?>
					<div class="col-12 col-lg-6">
						<div class="author-post__type">Редакторы:</div>
						<ul>
							<?php foreach ($med_article_editors as $med_article_editor): ?>
								<li>
									<a href="<?= get_author_posts_url($med_article_editor); ?>"
									   target="_blank">
										<?= get_the_author_meta('display_name', $med_article_editor); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if (!empty($med_article_translators)): ?>
					<div class="col-12 col-lg-6">
						<div class="author-post__type">Переводчики:</div>
						<ul>
							<?php foreach ($med_article_translators as $k => $med_article_translator): ?>
								<li>
									<a href="<?= get_author_posts_url($med_article_translator); ?>"
									   target="_blank">
										<?= get_the_author_meta('display_name', $med_article_translator); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $med_article_specialties ) ): ?>
					<div class="col-12 col-lg-6">
						<div class="author-post__type">
							Разделы:
						</div>
						<?php foreach ( $med_article_specialties as $i => $specialty ): ?>
							<a href="<?= get_term_link( $specialty->term_id, 'specialty' ); ?>"
							   target="_blank">
								<?= $specialty->name; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div><!--/.author-info -->
	</div><!--/.author-body -->
<?php endif; ?>
