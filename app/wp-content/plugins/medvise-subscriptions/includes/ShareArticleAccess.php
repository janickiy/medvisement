<?php /** @noinspection SqlNoDataSourceInspection */

namespace MedviseSubscriptions;

use MedviseSubscriptions\Subscriber\Subscriber;

class ShareArticleAccess {

	public static $succesfullySharedArticle = false;
	public static $tokensPerYear = 7;
	public static $usagesPerToken = 7;
	public static $durationDays = 3;
	private static $tableName = 'medvise_page_share_tokens';

	public function init(): void {
		add_action( 'template_redirect', [ $this, 'open_article_access' ], 12 );

		add_action( 'wp_ajax_medvise_create_share_article_token', [ $this, 'create_share_article_token_ajax' ] );
	}

	/*
	 * Открытие статьи по токену
	 */
	public function open_article_access() {
		if ( empty( $_GET['access_token'] ) ) {
			return;
		}

		// Если пользователь не авторизован - выкидываем
		if ( ! is_user_logged_in() ) {
			return;
		}

		global $wpdb;
		global $post;
		$user_id = get_current_user_id();

		// Доступ уже есть
		if ( Subscriber::hasAccess( $post ) ) {
			return;
		}

		$token = (string) ( $_GET['access_token'] );

		// Существует ли токен
		$token_data = self::get_share_token( $token );
		if ( empty( $token_data ) ) {
			return;
		}

		// Год токена = текущий
		$current_year = wp_date( 'Y' );
		if ( $current_year != $token_data->year_created ) {
			return;
		}

		// Проверяем, не превышен ли лимит
		if ( $token_data->usage_count >= self::$usagesPerToken ) {
			return;
		}

		// Проверяем соответствие записи
		if ( $post->ID != $token_data->post_id ) {
			return;
		}

		// Если уже открывали доступ по этому токену
		$source           = 'share_' . $token;
		$had_token_access = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}medvise_page_views WHERE user_id={$user_id} AND post_id={$post->ID} AND `source`='{$source}';"
		);

		if ( $had_token_access ) {
			return;
		}

		// Все ок - открываем доступ
		$datetime_now    = wp_date( 'Y-m-d H:i:s' );
		$datetime_expiry = wp_date( 'Y-m-d H:i:s', strtotime( '+' . self::$durationDays . ' days' ) );

		$wpdb->insert(
			$wpdb->prefix . 'medvise_page_views',
			[
				'user_id'     => $user_id,
				'post_id'     => $post->ID,
				'source'      => $source,
				'date_open'   => $datetime_now,
				'date_expiry' => $datetime_expiry
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		// Чтобы передать данные в шаблон темы
		self::$succesfullySharedArticle = true;
	}

	/*
	 * Создание токена для открытия статьи
	 */
	public function create_share_article_token_ajax() {

		check_ajax_referer( 'share_article_nonce', 'nonce' );

		$user_id = get_current_user_id();
		$post_id = (int) $_POST['post_id'];

		// Если статьей нельзя делиться
		if ( ! self::is_post_shareable( $post_id ) ) {
			wp_send_json_error( 'Нельзя открыть доступ к данной статье' );
		}

		// Если пользователь уже поделился статьей
		$share_token = self::get_user_actual_share_article_token( $user_id, $post_id );
		if ( ! empty( $share_token ) ) {
			wp_send_json_success( [
				'token' => $share_token->token,
			] );
		}

		// Есть ли права у пользователя на поделиться статьей
		if ( ! self::user_can_share_article( $user_id, $post_id ) ) {
			wp_send_json_error( 'У вас нет прав на открытие доступа' );
		}

		// Исчерпал ли пользователь годовой лимит токенов
		$user_share_tokens = self::get_user_share_tokens( $user_id );

		if ( count( $user_share_tokens ) >= self::$tokensPerYear ) {
			wp_send_json_error( 'Вы исчерпали лимит на открытие статей' );
		}

		global $wpdb;

		$current_datetime = current_datetime();
		$share_token      = bin2hex( random_bytes( 10 ) );

		$query = "
			INSERT IGNORE INTO `" . $wpdb->prefix . self::$tableName ."` (
			  user_id, post_id, token, year_created, date_created
			) 
			VALUES 
			  (%d, %d, %s, %s, %s);
		";

		$query = $wpdb->prepare( $query,
			$user_id,
			$post_id,
			$share_token,
			$current_datetime->format( 'Y' ),
			$current_datetime->format( 'Y-m-d H:i:s' )
		);

		$wpdb->query( $query );

		if ( $wpdb->rows_affected === 1 ) {
			wp_send_json_success( [
				'token' => $share_token,
				'usages' => ( count( $user_share_tokens ) + 1 )
			] );
		}

		wp_send_json_error( 'Ошибка открытия доступа к статье' );
	}

	/*
	 * Можно ли поделиться статьей
	 */
	public static function is_post_shareable( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		// Открывать можно только заболевания
		if ( $post->post_type !== 'disease' ) {
			return false;
		}

		// Пост итак бесплатный
		if ( get_post_meta( $post->ID, 'ep_free', true ) == 1 ) {
			return false;
		}

		// Клин. реки доступны всем
		$disease_article_type = wp_get_object_terms( $post->ID, 'article-type', [ 'fields' => 'id=>slug' ] );
		if ( in_array( 'clinical-guidelines', $disease_article_type ) ) {
			return false;
		}

		return true;
	}

	/*
	 * Может ли пользователь делиться статьями
	 */
	public static function user_can_share_article( $user_id, $post_id ) {

		$user = get_user_by( 'id', $user_id );
		$post = get_post( $post_id );

		if ( ! $user || ! $post ) {
			return false;
		}

		// Персонал сайта может делиться статьями без подписки
		if ( in_array( 'administrator', $user->roles ) ||
		     in_array( 'author', $user->roles ) ||
		     in_array( 'editor', $user->roles ) ) {
			return true;
		}

		global $wpdb;

		$has_access = false;

		// Есть ли подписка (годовая, в рассрочку, из-за рубежа)
		if ( Subscriber::hasSubscription( $post ) ) {
			$has_access = true;
		}

		// Есть ли доступ к специальности записи
		if ( ! $has_access ) {
			$post_specialties        = wp_get_post_terms( $post->ID, 'specialty', [ 'fields' => 'ids' ] );
			$post_specialties_string = implode( ',', $post_specialties );

			$specialty_view_access = $wpdb->get_row(
				"SELECT * FROM `{$wpdb->prefix}medvise_specialty_views` " .
				"WHERE user_id={$user->ID} AND specialty_id IN ({$post_specialties_string}) AND date_expiry >= NOW();"
			);

			// Доступ есть
			if ( $specialty_view_access !== null ) {
				$has_access = true;
			}
		}

		if ( ! $has_access ) {
			return false;
		}

		return true;
	}

	/*
	 * Получение данных токена статьи, которой поделился пользователь
	 */
	public static function get_user_actual_share_article_token( $user_id, $post_id ) {
		global $wpdb;

		$current_year = wp_date( 'Y' );

		$query = "SELECT * FROM `" . $wpdb->prefix . self::$tableName ."` WHERE " .
		         "user_id = %d AND post_id = %d AND year_created = %d;";

		return $wpdb->get_row( $wpdb->prepare( $query, $user_id, $post_id, $current_year ) );
	}

	/*
	 * Получение данных о токене
	 */
	public static function get_share_token( $token ) {
		global $wpdb;

		$source = 'share_' . $token;

		$query = "
			SELECT 
			  sl.token AS token, 
			  COUNT(pv.source) AS usage_count , 
			  sl.user_id AS user_id, 
			  sl.post_id AS post_id, 
			  sl.year_created AS year_created, 
			  sl.date_created AS date_created
			FROM 
			  `" . $wpdb->prefix . self::$tableName ."` sl 
			  LEFT JOIN `wp_medvise_page_views` pv ON pv.source = %s
			WHERE
			  sl.token = %s
			GROUP BY 
			  sl.token, 
			  pv.source;
		";

		return $wpdb->get_row( $wpdb->prepare( $query, $source, $token ) );
	}

	public static function get_user_share_tokens( $user_id, $current_year = null ) {
		global $wpdb;

		if ( empty( $current_year ) ) {
			$current_year = wp_date( 'Y' );
		}

		$query         = "SELECT * FROM `" . $wpdb->prefix . self::$tableName . "` WHERE " .
		                 "user_id = %d AND YEAR(date_created) = %d;";

		return $wpdb->get_results( $wpdb->prepare( $query, $user_id, $current_year ) );
	}
}