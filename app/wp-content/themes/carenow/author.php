<?php
get_header();

$author = get_queried_object();

?>

	<div class="container">
		<div class="row">
			<div class="col-md-12">
				<div id="primary" class="content-area">
					<main id="main" class="post-wrap" role="main">

						<h1 class="entry-title"><?= $author->display_name; ?></h1>

						<?= do_shortcode( '[custom_profile_page id="' . $author->ID . '"]' ); ?>

					</main><!-- #main -->
				</div><!-- #primary -->
				<?php
				if (themesflat_get_opt('sidebar_layout') == 'sidebar-left' || themesflat_get_opt('sidebar_layout') == 'sidebar-right') :
					get_sidebar();
				endif;
				?>
			</div><!-- /.col-md-12 -->
		</div><!-- /.row -->
	</div><!-- /.container -->
<?php get_footer(); ?>