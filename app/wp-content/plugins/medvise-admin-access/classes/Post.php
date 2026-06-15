<?php


namespace MedvisementAdminAccess;


class Post {

	public static function factory() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		// Для записей со статусом pending показываем контент предыдущей ревизии (post_parent=ID, post_type=revision)
		add_filter( 'the_content', [ $this, 'the_content' ], 1, 1 );
		// Тоже самое для индексации эластиком
		add_filter( 'ep_post_sync_args', [ $this, 'ep_post_sync_args' ], 1, 2 );

		add_filter( 'pre_delete_post', [ $this, 'pre_delete_post' ], 10, 3 );

		// Преднаполнение препаратов и заболеваний
		if ( is_admin() ) {
			add_filter( 'wp_insert_post_data', [ $this, 'custom_insert_substance' ], 99, 2 );
		}

		add_action( 'wp_insert_post', [ $this, 'wp_insert_post' ], 10, 3 );

		add_action( 'transition_post_status', [ $this, 'process_pending_post' ], 10, 3);

		add_filter( 'pre_get_posts', [ $this, 'preview_pending_posts' ] );

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ], 99 );
	}

	public function the_content( $content ) {

		global $post;
		global $wpdb;

		if ( is_singular() && in_array( $post->post_type, [ 'disease', 'substance' ] ) && $post->post_status === 'pending' ) {
			return $this->get_post_revisioned_content( $post );
		}

		return $content;
	}

	public function ep_post_sync_args( $post_args, $post_id ) {

		$post = get_post( $post_id );

		if ( in_array( $post->post_type, [ 'disease', 'substance' ] ) && $post->post_status === 'pending' ) {
			$post_args['post_content'] = $this->get_post_revisioned_content( $post );
		}

		return $post_args;
	}

	public function pre_delete_post( $delete, $post, $force_delete ) {

		// Авторы/редакторы не могут удалять пост навсегда
		if ( Helpers::is_editor_or_author() ) {
			return FALSE;
		}

		return $delete;
	}

	public function custom_insert_substance( $data, $postarr ) {

		// При создании препаратов  преднаполняем шаблоном
		if ( $postarr['post_type'] == 'substance' && $postarr['post_status'] == 'auto-draft' ) {
			$data['post_content'] = file_get_contents( MEDVISEADMINACCESS_PLUGIN_DIR . 'plain-templates/substance-prefill.html' );
		}

		return $data;
	}

	public function wp_insert_post( $post_id, $post, $update ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return TRUE;
		}

		if ( ! in_array( $post->post_type, [ 'disease', 'substance' ] ) || ! in_array( $post->post_status, [ 'publish', 'pending' ] ) ) {
			return TRUE;
		}

		if ( ! Helpers::is_editor_or_author() ) {
			return TRUE;
		}

		// Для редакторов, авторов и переводчиков эти поля не отображаются и не сохраняются - сохраняем в бэкенде. Берутся стандартные значения.
		$post_authors = carbon_get_the_post_meta( 'med_article_authors' );
		carbon_set_post_meta( $post->ID, 'med_article_authors', $post_authors );

		$post_editors = carbon_get_the_post_meta( 'med_article_editors' );
		carbon_set_post_meta( $post->ID, 'med_article_editors', $post_editors );

        $post_translators = carbon_get_the_post_meta( 'med_article_translators' );
        carbon_set_post_meta( $post->ID, 'med_article_translators', $post_translators );

	}

	public function process_pending_post( $new_status, $old_status, $post ) {

		if ( ! in_array( $post->post_type, [ 'disease', 'substance' ] ) ) {
			return true;
		}

		// Опубликовали пост - удаяем мета
		if ( 'publish' === $new_status ) {
			delete_post_meta( $post->ID, 'last_revisioned_revision_id' );
		}

		if ( 'publish' === $old_status && 'pending' === $new_status ) {
			// Сохраняем ID опубликованной ревизии поста
			$post_revisions  = wp_get_post_revisions( $post );
			$latest_revision = array_shift( $post_revisions );

			update_post_meta( $post->ID, 'last_revisioned_revision_id', $latest_revision->ID );
		}

	}

	public function preview_pending_posts( $query ) {

		if ( is_admin() || get_query_var( 'suppress_filters' ) || $query->get('post_type') !== 'post' ) {
			return $query;
		}

		if ( Helpers::is_editor_or_author() || current_user_can( 'administrator' ) ) {
			$query->set( 'post_status', [ 'publish', 'pending' ] );
		}

		return $query;
	}

	public function enqueue_block_editor_assets() {

		if ( Helpers::is_editor_or_author() ) {
			// Убираем скрипт эластика для скрытия из поиска
			wp_dequeue_script( 'ep-search-editor' );
		}
	}

	protected function get_post_revisioned_content( $post ) {

		global $wpdb;

		$last_revisioned_revision_id = get_post_meta( $post->ID, 'last_revisioned_revision_id', TRUE );

		$query  = "SELECT `post_content` FROM `wp_posts` WHERE ID={$last_revisioned_revision_id};;";
		$result = $wpdb->get_var( $query );

		if ( empty( $result ) ) {
			return $post->post_content;
		}

		return $result;
	}
}