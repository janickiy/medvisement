<?php
/*
Plugin Name: Medvisement - Gutenberg страницы авторов
Description:
Version: 1.0.0
Author: Medvisement
Text Domain: medvise-post-rating
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
};

include 'helpers.php';

use CustomProfilePage\Helpers as Helpers;

register_activation_hook( __FILE__, [ 'CustomProfilePage', 'pluginActivation' ] );
register_deactivation_hook( __FILE__, [ 'CustomProfilePage', 'pluginDeactivation' ] );

class CustomProfilePage {
	const FOR_ROLES = [ 'author', 'editor' ];
	public static $instance;
	public $shortcodeShows = FALSE;

	private function __construct() {
		add_action( 'init', [ $this, 'registerCPT' ] );
		add_action( 'current_screen', [ $this, 'createPageIfHaveTo' ] );
		add_filter( 'pre_get_posts', [ $this, 'hideOtherUsersPages' ], 1000 );

		add_shortcode( 'custom_profile_page', [ $this, 'shortcode' ] );

		//Отключаем страницу author.php для всех кроме авторов и редакторов
		add_action( 'template_redirect', [ $this, 'template_redirect' ] );
		//Генерацию ссылок тоже убираем
		add_filter( 'author_link', [ $this, 'author_link' ], 10, 2 );
	}

	public static function init() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
	}

	public static function pluginActivation() {
		$adminCapabilities = [
			'read_custom-profile-page',
			'read_custom-profile-pages',
			'read_private_custom-profile-pages',
			'edit_custom-profile-page',
			'edit_custom-profile-pages',
			'edit_published_custom-profile-pages',
			'edit_others_custom-profile-pages',
			'publish_custom-profile-page',
			'publish_custom-profile-pages',
			'delete_custom-profile-pages',
			'delete_custom-profile-page',
			'delete_others_custom-profile-pages',
			'delete_published_custom-profile-pages'
		];

		$authorCapabilities = [
			'read_custom-profile-page',
			'read_custom-profile-pages',
			'edit_custom-profile-page',
			'edit_custom-profile-pages',
			'edit_published_custom-profile-pages',
			'publish_custom-profile-page',
			'publish_custom-profile-pages',
		];

		Helpers\addCapsToRole( 'administrator', $adminCapabilities );

		foreach ( self::FOR_ROLES as $role ) {
			Helpers\addCapsToRole( $role, $authorCapabilities );
		}
	}

	public static function pluginDeactivation() {
		$capsToDelete = [
			'read_custom-profile-page',
			'read_custom-profile-pages',
			'read_private_custom-profile-pages',
			'edit_custom-profile-page',
			'edit_custom-profile-pages',
			'edit_published_custom-profile-pages',
			'edit_others_custom-profile-pages',
			'publish_custom-profile-page',
			'publish_custom-profile-pages',
			'delete_custom-profile-pages',
			'delete_custom-profile-page',
			'delete_others_custom-profile-pages',
			'delete_published_custom-profile-pages'
		];

		Helpers\removeCapsFromRole( 'administrator', $capsToDelete );

		foreach ( self::FOR_ROLES as $role ) {
			Helpers\removeCapsFromRole( $role, $capsToDelete );
		}
	}

	public function registerCPT() {
		register_post_type( 'custom-profile-page',
			array(
				'labels'             => array(
					'name'          => __( 'Страница автора' ),
					'singular_name' => __( 'Страница автора' ),
					'not_found'     => __( 'Страница автора не найдена' ),
					'add_new'       => __( 'Добавить страницу автора' ),
					'add_new_item'  => __( 'Добавить страницу автораopm' ),
					'edit_item'     => __( 'Редактировать' )
				),
				'menu_position'      => 4,
				'menu_icon'          => 'dashicons-clipboard',
				'public'             => TRUE,
				'has_archive'        => FALSE,
				'show_in_rest'       => TRUE,
				'rewrite'            => FALSE,
				'query_var'          => FALSE,
				'publicly_queryable' => FALSE,
				'supports'           => array( 'editor', 'title', 'author' ),
				'capability_type'    => [ 'custom-profile-page', 'custom-profile-pages' ],
				'capabilities'       => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'       => TRUE,
			)
		);
	}

	public function hideOtherUsersPages( $query ) {
		if ( $query->is_admin && $query->query['post_type'] === 'custom-profile-page' ) {
			$userId = get_current_user_id();
			if ( $userId && ! current_user_can( 'edit_others_posts' ) ) {
				$query->set( 'author', $userId );
			}
		}

		return $query;
	}

	public function createPageIfHaveTo() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();

		if ( $screen->id === 'edit-custom-profile-page' ) {
			$this->findOrCreateProfilePage( get_current_user_id() );
		}
	}

	public function findOrCreateProfilePage( $userId ) {
		$postId = $this->findProfilePage( $userId );

		//if user has no profile page - create it
		if ( ! $postId ) {
			$postId = $this->createProfilePage( $userId );
		}

		return $postId;
	}

	public function findProfilePage( $userId ) {
		$query = new WP_Query( [
			'post_type'      => 'custom-profile-page',
			'author'         => $userId,
			'posts_per_page' => 1,
			'fields'         => 'ids'
		] );

		$posts = $query->get_posts();

		if ( $posts ) {
			return $posts[0];
		}

		return NULL;
	}

	public function createProfilePage( $userId ) {
		if ( ! $userId ) {
			return NULL;
		}

		if ( ! Helpers\userInRoles( $userId, self::FOR_ROLES ) ) {
			return NULL;
		}

		$postId = wp_insert_post( array(
			'post_title'   => 'My Page',
			'post_content' => '',
			'post_status'  => 'draft',
			'post_author'  => $userId,
			'post_type'    => 'custom-profile-page'
		) );

		if ( $postId ) {
			$this->setPageForUser( $userId, $postId );
		}

		return $postId;
	}

	public function setPageForUser( $userId, $postId ) {
		update_user_meta( $userId, 'custom-profile-page', $postId );
	}

	public function shortcode( $atts, $content ) {
		//only one instance of shortcode
		if ( $this->shortcodeShows ) {
			return '';
		}
		$this->shortcodeShows = TRUE;

		$atts = shortcode_atts( array(
			'id' => ''
		), $atts );

		$userId = (int) $atts['id'];

		if ( ! $userId ) {
			return '';
		}

		$profilePageId = $this->getProfilePageId( $userId );
		$profilePage   = get_post( $profilePageId );

		if ( $profilePage && $profilePage->post_status === 'publish' ) {
			return apply_filters( 'the_content', get_the_content( '', FALSE, $profilePage ) );
		} else {
			return get_user_meta( $userId, 'description', TRUE );
		}
	}

	public function getProfilePageId( $userId ) {
		$postId = get_user_meta( $userId, 'custom-profile-page', TRUE );

		//if user has no saved profile page id - find
		if ( ! $postId ) {
			$postId = $this->findProfilePage( $userId );
			$this->setPageForUser( $userId, $postId );
		}

		return $postId;
	}

	// Отключаем страницу пользователя автоматически генерируемую
	public function template_redirect() {
		global $wp_query;

		if ( ! is_author() ) {
			return;
		}

		$user = get_queried_object();

		//Не автор и не редактор - 404
		if ( ! in_array( 'author', $user->roles ) && ! in_array( 'editor', $user->roles ) ) {
			$wp_query->set_404();
		}
	}

	// Ссылка на страницу пользователя в админке
	public function author_link( $link, $author_id ) {
		$user = get_user_by( 'ID', $author_id );

		//Не редактор или автор - выключаем ссылку
		if ( ! in_array( 'author', $user->roles ) && ! in_array( 'editor', $user->roles ) ) {
			return '';
		}

		return $link;
	}
}

CustomProfilePage::init();