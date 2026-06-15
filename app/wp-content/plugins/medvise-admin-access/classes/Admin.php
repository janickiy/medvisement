<?php


namespace MedvisementAdminAccess;


class Admin {

	public static function factory() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		add_action( 'wp_before_admin_bar_render', [ $this, 'remove_admin_bar_links' ] );

		add_action( 'load-post-new.php', [ $this, 'admin_page_restriction' ] );
		add_action( 'load-edit.php', [ $this, 'admin_page_restriction' ] );
		add_action( 'load-edit-tags.php', [ $this, 'admin_page_restriction' ] );
		add_action( 'load-edit-comments.php', [ $this, 'admin_page_restriction' ] );

		add_action( 'admin_menu', [ $this, 'medvise_admin_menu' ], 999 );
	}

	public function remove_admin_bar_links() {
		global $wp_admin_bar;
		// Убираем посты из топбара
		$wp_admin_bar->remove_menu( 'new-post' );
		$wp_admin_bar->remove_menu( 'new-page' );
	}

	public function admin_page_restriction() {

		if ( ! Helpers::is_editor_or_author() ) {
			return TRUE;
		}

		$restrict_access = FALSE;

		// По умолчанию edit_posts отключить нельзя - пропадает админка
		if ( ! empty( get_current_screen()->post_type ) && in_array( get_current_screen()->post_type, [ 'post' ] ) ) {
			$restrict_access = TRUE;
		}

		// Комментарии завязаны на edit_posts
		if ( get_current_screen()->id == 'edit-comments' ) {
			$restrict_access = TRUE;
		}

		if ( $restrict_access ) {
			// Кидаем на дешборд
			wp_redirect( get_admin_url( NULL, "index.php" ) );
		}

	}

	public function medvise_admin_menu() {
		if ( Helpers::is_editor_or_author() ) {
			// По умолчанию edit_posts отключить нельзя - пропадает админка. Убираем пункт меню
			remove_menu_page( 'edit.php' );
			// Комменарии тоже самое
			remove_menu_page( 'edit-comments.php' );
			// Инструменты тоже на edit_posts
			remove_menu_page( 'tools.php' );
			remove_menu_page( 'edit.php?post_type=product' );
		}
	}
}