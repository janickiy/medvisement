<?php
namespace MedvisementPostRating;

class PostRating {
	const VOTE_TABLE = 'medinfo_vote';
	const ALLOW_POST_TYPES = [ 'substance', 'disease' ];
	private static $instance;

	private function __construct() {
		add_action( 'wp_ajax_medinfo_post_rating', [ $this, 'handleFormSubmission' ] );
		add_action( 'add_meta_boxes', [ $this, 'addMetabox' ] );
		add_action( 'post_updated', [ $this, 'clearPostRating' ], 10, 3 );

		add_shortcode( 'medinfo_post_rating', [ $this, 'shortcode' ] );
	}

	public static function activate() {
		self::createTable();
	}

	private static function createTable() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::VOTE_TABLE;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id mediumint(9) NOT NULL,
            post_id mediumint(9) NOT NULL,
            vote tinyint NOT NULL,
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

		$table_name = $wpdb->prefix . self::VOTE_TABLE;

		$sql = "DROP TABLE $table_name";

		$wpdb->query( $sql );
	}

	public function clearPostRating( $postId, $postAfter, $postBefore ) {
		$this->clearForPost( $postId );
	}

	public function clearForPost( $postId ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . self::VOTE_TABLE,
			[
				'post_id' => $postId,
			]
		);
	}

	public function addMetabox() {
		add_meta_box( 'medinfo_post_rating', 'Рейтинг статьи', [ $this, 'metaboxHtml' ], self::ALLOW_POST_TYPES );
	}

	public function metaboxHtml( $post, $meta ) {
		$userId = get_current_user_id();

		$votes = NULL;
		if ( user_can( $userId, 'manage_options' ) ) {
			$votes = $this->getPostVotes( $post->ID );
		}


		Helpers::getTpl( 'metabox-rating', [
			'avgRating' => $this->getAvgRating( $post->ID ),
			'votesQty'  => $this->countVotes( $post->ID ),
			'votes'     => $votes
		] );
	}

	public function getPostVotes( $postId ) {
		global $wpdb;

		$tableName = $wpdb->prefix . self::VOTE_TABLE;
		$query     = $wpdb->prepare( "
            SELECT *
            FROM $tableName
            WHERE `post_id` = %d
            ORDER BY id DESC
        ", $postId );

		return $wpdb->get_results( $query );
	}

	public function getAvgRating( $postId ) {
		global $wpdb;

		$tableName = $wpdb->prefix . self::VOTE_TABLE;
		$query     = $wpdb->prepare( "
            SELECT AVG(vote)
            FROM $tableName
            WHERE `post_id` = %d
        ", $postId );

		return $wpdb->get_var( $query );
	}

	public function countVotes( $postId ) {
		global $wpdb;

		$tableName = $wpdb->prefix . self::VOTE_TABLE;
		$query     = $wpdb->prepare( "
            SELECT COUNT(*)
            FROM $tableName
            WHERE `post_id` = %d
        ", $postId );

		return $wpdb->get_var( $query );
	}

	public function handleFormSubmission() {
		$dataIsCorrect = wp_verify_nonce( $_POST['medinfo_post_rating_nonce'], 'medinfo_post_rating_nonce' );
		$dataIsCorrect = $dataIsCorrect && ! empty( $_POST['vote'] );
		$dataIsCorrect = $dataIsCorrect && ! empty( $_POST['post_id'] );

        if ( $_POST['vote'] < 4 && empty( $_POST['message'] ) ) {
            $dataIsCorrect = false;
        }

		if ( ! $dataIsCorrect ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Переданы некорректные данные'
			] ) );
		}

		if ( $_POST['vote'] > 5 || $_POST['vote'] < 1 ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Ваш голос должен быть от 1 до 5'
			] ) );
		}

		$postId = $_POST['post_id'];
		$vote   = (int) $_POST['vote'];
		$userId = get_current_user_id();
		$post   = get_post( $postId );
        $message = $_POST['message'];

		if ( ! $userId ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Вам необходимо авторизоваться'
			] ) );
		}

		if ( ! $post || $post->post_status !== 'publish' || ! in_array( $post->post_type, self::ALLOW_POST_TYPES ) ) {
			wp_die( json_encode( [
				'status'  => 'error',
				'message' => 'Пост недоступен'
			] ) );
		}

		$this->vote( $postId, $userId, $vote, $message );
		wp_die( json_encode( [
			'status'  => 'ok',
			'message' => 'Спасибо за вашу оценку!',
			'data'    => [
				'avgRating' => self::allowedToReadPostRating() ? number_format( $this->getAvgRating( $postId ), 2 ) : 0,
				'votesQty'  => self::allowedToReadPostRating() ? $this->countVotes( $postId ) : 0
			]
		] ) );
	}

	public function vote( $postId, $userId, $vote, $message ) {
		global $wpdb;

		$userVote = $this->getUserVote( $userId, $postId );

		if ( $userVote ) {
            $wpdb->update(
                $wpdb->prefix . self::VOTE_TABLE,
                ['vote' => $vote, 'message' => $message ],
                ['id' => $userVote->id]
            );

			return $userVote->id;
		} else {
			$done = $wpdb->insert(
				$wpdb->prefix . self::VOTE_TABLE,
				[
					'time'    => current_time( 'mysql' ),
					'user_id' => $userId,
					'post_id' => $postId,
					'vote'    => $vote,
                    'message' => $message
				]
			);

			if ( $done ) {
				return $wpdb->insert_id;
			}
		}

		return FALSE;
	}

	public function getUserVote( $userId, $postId ) {
		global $wpdb;

		$tableName = $wpdb->prefix . self::VOTE_TABLE;
		$query     = $wpdb->prepare( "
            SELECT *
            FROM $tableName
            WHERE `post_id` = %d
                AND `user_id` = %d
        ", $postId, $userId );

		return $wpdb->get_row( $query );
	}

	public static function allowedToReadPostRating() {
		$userId = get_current_user_id();
		if ( ! $userId ) {
			return FALSE;
		}

		$user = new \WP_User( $userId );

		return $user && ( in_array( 'editor', $user->roles ) ||
		                  in_array( 'author', $user->roles ) ||
		                  in_array( 'administrator', $user->roles ) );
	}

	public function shortcode( $atts, $content ) {
		$atts = shortcode_atts( array(
			'id' => ''
		), $atts );

		$userId = get_current_user_id();
		if ( ! $userId ) {
			return '';
		}

		$postId = (int) $atts['id'];

		$userVote = $this->getUserVote( $userId, $postId );

		return Helpers::getTpl( 'post-rating', [
			'userVote'  => $userVote,
			'postId'    => $postId,
			'userId'    => $userId,
			'avgRating' => $this->getAvgRating( $postId ),
			'votesQty'  => $this->countVotes( $postId ),
		], FALSE );
	}
}