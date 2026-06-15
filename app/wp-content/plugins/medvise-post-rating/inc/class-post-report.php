<?php
namespace MedvisementPostRating;

class PostReport {
	const REPORT_TABLE = 'medinfo_report';
	const ATTEMPTS = 3;
	const MAX_MESSAGE_LENGTH = 500;
	const ALLOW_POST_TYPES = [ 'substance', 'disease' ];
	private static $instance;

	private function __construct() {
		add_action( 'wp_ajax_medinfo_post_report', [ $this, 'handleFormSubmission' ] );
		add_action( 'add_meta_boxes', [ $this, 'addMetabox' ] );
		add_action( 'post_updated', [ $this, 'clearPostReports' ], 10, 3 );

		add_shortcode( 'medinfo_post_report_form', [ $this, 'shortcode' ] );
	}

	public static function activate() {
		self::createTable();
	}

	private static function createTable() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::REPORT_TABLE;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id mediumint(9) NOT NULL,
            post_id mediumint(9) NOT NULL,
            message text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function deactivate() {
		//self::dropTable();
	}

	public static function init() {
		self::getInstance();
	}

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private static function dropTable() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::REPORT_TABLE;

		$sql = "DROP TABLE $table_name";

		$wpdb->query( $sql );
	}

	public function clearPostReports( $postId, $postAfter, $postBefore ) {
		$this->clearForPost( $postId );
	}

	public function clearForPost( $postId ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . self::REPORT_TABLE,
			[
				'post_id' => $postId,
			]
		);
	}

	public function addMetabox() {
		add_meta_box( 'medinfo_post_report', 'Сообщения об ошибках', [ $this, 'metaboxHtml' ], self::ALLOW_POST_TYPES );
	}

	public function metaboxHtml( $post, $meta ) {
		$reports = $this->getReports( $post->ID );
		Helpers::getTpl( 'metabox-reports-list', [
			'reports' => $reports
		] );
	}

	public function getReports( $postId ) {
		global $wpdb;

		$tableName = $wpdb->prefix . self::REPORT_TABLE;
		$query     = $wpdb->prepare( "
            SELECT *
            FROM $tableName
            WHERE `post_id` = %d
            ORDER BY id DESC
        ", $postId );

		return $wpdb->get_results( $query );
	}

	public function handleFormSubmission() {
		$dataIsCorrect = wp_verify_nonce( $_POST['medinfo_post_report_nonce'], 'medinfo_post_report_nonce' );
		$dataIsCorrect = $dataIsCorrect && ! empty( $_POST['message'] );
		$dataIsCorrect = $dataIsCorrect && ! empty( $_POST['post_id'] );

		if ( ! $dataIsCorrect ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Переданы некорректные данные'
			] ) );
		}

		if ( mb_strlen( $_POST['message'] ) > self::MAX_MESSAGE_LENGTH ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Длина сообщение не может превышать ' . self::MAX_MESSAGE_LENGTH . ' символов'
			] ) );
		}

		$postId  = $_POST['post_id'];
		$message = $_POST['message'];
		$userId  = get_current_user_id();
		$post    = get_post( $postId );

		if ( ! $post || $post->post_status !== 'publish' || ! in_array( $post->post_type, self::ALLOW_POST_TYPES ) ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Пост недоступен'
			] ) );
		}

		$attempts = $this->getUserAttempts( $userId, $postId );

		if ( $attempts < 1 ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Вы исчерпали лимит сообщений'
			] ) );
		}

		$this->addReport( $postId, $userId, $message );
		wp_die( json_encode( [
			'status'  => 'ok',
			'message' => 'Ваше сообщение успешно отправлено',
			'data'    => [
				'attempts' => $attempts - 1
			]
		] ) );
	}

	public function getUserAttempts( $userId, $postId ) {
		$reportsQty = $this->countUserReports( $userId, $postId );

		return max( self::ATTEMPTS - $reportsQty, 0 );
	}

	public function countUserReports( $userId, $postId ) {
		global $wpdb;

		$tableName = $wpdb->prefix . self::REPORT_TABLE;
		$query     = $wpdb->prepare( "
            SELECT COUNT(*)
            FROM $tableName
            WHERE `post_id` = %d
                AND `user_id` = %d
        ", $postId, $userId );

		return $wpdb->get_var( $query );
	}

	public function addReport( $postId, $userId, $message ) {
		global $wpdb;

		$done = $wpdb->insert(
			$wpdb->prefix . self::REPORT_TABLE,
			[
				'time'    => current_time( 'mysql' ),
				'user_id' => $userId,
				'post_id' => $postId,
				'message' => $message,
			]
		);

		if ( $done ) {
			return $wpdb->insert_id;
		}

		return FALSE;
	}

	public function shortcode( $atts, $content ) {
		$atts = shortcode_atts( array(
			'id' => ''
		), $atts );

		$userId = get_current_user_id();
		if ( ! $userId ) {
			return '';
		}

		$postId   = (int) $atts['id'];
		$attempts = $this->getUserAttempts( $userId, $postId );

		return Helpers::getTpl( 'post-report-form', [
			'attempts' => $attempts,
			'postId'   => $postId,
			'userId'   => $userId
		], FALSE );
	}
}