<?php /** @noinspection SqlNoDataSourceInspection */

namespace MedviseSubscriptions;
class ShareArticleWoocommerce {

	public function init() {
		add_filter( 'woocommerce_get_query_vars', [ $this, 'woocommerce_get_query_vars' ], 10, 1 );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'woocommerce_account_menu_items' ], 100, 1 );
		add_filter( 'woocommerce_endpoint_share-article_title', [ $this, 'woocommerce_endpoint_share_article_title' ], 10, 1 );
		add_filter( 'woocommerce_account_share-article_endpoint', [ $this, 'woocommerce_account_share_article_endpoint' ], 10, 1 );
	}

	public function woocommerce_get_query_vars( $vars ) {
		$vars['share-article'] = 'share-article';
		return $vars;
	}

	public function woocommerce_endpoint_share_article_title( $title ) {
		return 'Статьи для других';
	}

	public function woocommerce_account_share_article_endpoint() {

		$share_tokens = ShareArticleAccess::get_user_share_tokens( get_current_user_id() );
		?>
        <p>
            Вы можете открыть какую-либо статью на сайте пользователям, у которых нет доступа.<br>
            Для этого необходимо перейти на страницу статьи, нажать кнопку "поделиться статьей" и выбрать нижнюю ссылку.<br>
            Всего вы можете открыть 7 различных <?= plural_russian( [ 'статью', 'статьи', 'статей' ], 7 ); ?> в течение года. <br>
            Каждая из них может быть открыта для 7 различных пользователей.<br>
            На этой странице ниже будут отображаться статьи, которые вы открыли и количество открытий каждой статьи.
        </p>
		<?php
		if ( empty( $share_tokens ) ) {
			return;
		}
		?>

        <table>
            <tr>
                <th>Статья</th>
                <th>Поделиться статьей</th>
                <th>Использований</th>
                <th>Дата создания</th>
            </tr>
			<?php foreach ( $share_tokens as $token ):
                $token_data = ShareArticleAccess::get_share_token( $token->token );
				$token_post = get_post( $token->post_id );
                $datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $token_post->post_date, wp_timezone() );
				?>
				<tr>
                    <td>
                        <a href="<?= get_permalink( $token_post ); ?>" target="_blank">
	                        <?= $token_post->post_title; ?>
                        </a>
                    </td>
                    <td>
                        <a href="<?= get_permalink( $token_post ) . "?access_token={$token->token}"; ?>" target="_blank">
		                    Ссылка
                        </a>
                    </td>
                    <td>
                        <?= $token_data->usage_count . "/" . ShareArticleAccess::$usagesPerToken ?>
                    </td>
                    <td><?= $datetime->format('d-m-Y H:i'); ?></td>
                </tr>
			<?php endforeach; ?>
        </table>
		<?php
	}

	public function woocommerce_account_menu_items( $items ) {

		$account_position = array_search( 'edit-account', array_keys($items) ) + 1;

		return array_slice( $items, 0, $account_position, true ) +
		       ['share-article' => 'Статьи для других'] +
		       array_slice( $items, $account_position, count( $items ), true );
	}
}