<?php

use MedviseSubscriptions\Subscriber\Subscriber;
use MedviseSubscriptions\ShareArticleAccess;

function medvise_render_share_article_button() {

	global $post;

	if ( ! in_array( $post->post_type, [ 'substance', 'disease' ] ) ) {
		return;
	}

	if ( Subscriber::hasSubscription()
	     && ShareArticleAccess::is_post_shareable( $post->ID )
         && ShareArticleAccess::user_can_share_article( get_current_user_id(), $post->ID )
    ):
		?>
        <div class="text-end">
            <button class="podelitsya-article button"
                    data-bs-toggle="modal" data-bs-target="#share-article-modal">
                Поделиться статьей <i class="fa-solid fa-share"></i>
            </button>
        </div>
	<?php else:
		$post_url = medvise_referral_generate_post_url( $post->ID );
		?>
        <div class="text-end">
            <a class="podelitsya-article button"
               href="<?= $post_url ?>"
               data-href="<?= $post_url; ?>">
                Поделиться статьей <i class="fa-solid fa-share"></i>
            </a>
        </div>
	<?php
	endif;
}

function medvise_render_share_article_modal() {
	global $post;

	if ( ! in_array( $post->post_type, [ 'substance', 'disease' ] ) ) {
		return;
	}

	if ( Subscriber::hasSubscription()
         && ShareArticleAccess::is_post_shareable( $post->ID )
         && ShareArticleAccess::user_can_share_article( get_current_user_id(), $post->ID )
    ) {
		?>
        <div class="modal fade modal-share-article" id="share-article-modal" tabindex="-1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                        <h5 class="modal-title">Ссылка на статью</h5>
                    </div>
                    <div class="modal-body text-center">

                        <div class="copy-article__wrapper">
                            <input type="text" class="input-text" name="short_link"
                                   value="<?= medvise_referral_generate_post_url( $post->ID ); ?>"
                                   disabled>
                            <button class="themesflat-button">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>

                        <hr>

                        <h5 class="modal-title">Поделиться доступом</h5>

	                    <?php
                        // Проверяем, открыл ли пользователь эту статью
	                    $post_share_token = ShareArticleAccess::get_user_actual_share_article_token( get_current_user_id(), $post->ID );
                        // Сколько у пользователя токенов
                        $user_share_tokens = ShareArticleAccess::get_user_share_tokens( get_current_user_id() );
	                    ?>

                        <p style="text-align: justify; margin-bottom: 12px;">
                            Вы можете открыть какую-либо статью на сайте пользователю, у которого нет доступа.
                            Для этого необходимо поделиться с ним ссылкой ниже.
                            Ссылка вверху просто ведет на статью, ссылка снизу - открывает доступ к статье.<br>

                            Всего вы можете открыть 7 различных <?= plural_russian( [ 'статью', 'статьи', 'статей' ], 7 ); ?>.
                            Каждая из них может быть открыта для 7 различных пользователей без доступа.
                        </p>

	                    <?php if ( ! empty( $post_share_token ) ): ?>
                            <div class="share-article__wrapper">
                                <input type="text" class="input-text" name="share_link"
                                       value="<?= get_permalink() . "?access_token={$post_share_token->token}"; ?>"
                                       disabled="">
                                <button class="themesflat-button share-article__copy"><i class="fa-solid fa-copy"></i>
                                </button>
                            </div>
	                    <?php elseif ( count( $user_share_tokens ) < ShareArticleAccess::$tokensPerYear ): ?>
                            <div class="share-article__wrapper">
                                <input type="hidden" name="post_id"
                                       value="<?= $post->ID; ?>">
                                <input type="hidden" name="share_article_nonce"
                                       value="<?= wp_create_nonce( 'share_article_nonce' ); ?>">
                                <button class="themesflat-button share-article__create">
                                <span class="text">
                                    Создать ссылку доступа
                                <i class="fa-solid fa-circle-plus"></i>
                                </span>
                                    <div class="loader-icon-2"></div>
                                </button>
                            </div>
	                    <?php endif; ?>

                        <div class="share-article__usages">
                            Создано ссылок:
                            <span class="share-article__usages_current"><?= count( $user_share_tokens ); ?></span>/<span class="share-article__usages_total"><?= ShareArticleAccess::$tokensPerYear; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<?php
	}
}

function medvise_render_shared_article_modal() {

	if ( empty( $_GET['access_token'] ) ) {
		return;
	}

	// Существует ли токен
	$token_data = ShareArticleAccess::get_share_token( $_GET['access_token'] );
	if ( empty( $token_data ) ) {
		return;
	}

	// Год токена = текущий
	$current_year = wp_date( 'Y' );
	if ( $current_year !== $token_data->year_created ) {
		return;
	}

	// Проверяем, не превышен ли лимит
	if ( $token_data->usage_count >= ShareArticleAccess::$usagesPerToken ) {
		return;
	}

    $philantropist_name = get_user_meta($token_data->user_id, 'first_name', true);
    if ( empty( $philantropist_name ) ) {
        $philantropist_name = 'Коллега';
    }

	global $post;

    if ( ! is_user_logged_in() ) {
        ?>
        <div class="modal fade modal-shared-article" id="shared-article-modal" tabindex="-1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                        <h5 class="modal-title">Вам открыли доступ к статье</h5>
                    </div>
                    <div class="modal-body text-center">
                        <p style="text-align: justify;">
                            <?= $philantropist_name; ?> отправил(а) вам ссылку для просмотра статьи
                            «<?= $post->post_title; ?>».<br>
                            Для просмотра этой статьи вам необходимо зарегистрироваться на сайте Medvisement.com,
                            а затем повторно перейти по этой ссылке.
                        </p>
                    </div>
                    <?php
                    $move_to_url = urlencode( THEMESFLAT_PROTOCOL . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
                    ?>
                    <div class="modal-footer">
                        <a class="themesflat-button" href="/login/?move_to=<?= $move_to_url; ?>" target="_blank">Войти</a>
                        <a class="themesflat-button" href="/register/?move_to=<?= $move_to_url; ?>" target="_blank">Регистрация</a>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                jQuery('#shared-article-modal').modal('show');
            });
        </script>
<?php
    } else {
	    // Доступ не открывали
	    if ( ! ShareArticleAccess::$succesfullySharedArticle ) {
		    return;
	    }
	    ?>
        <div class="modal fade modal-shared-article" id="shared-article-modal" tabindex="-1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Закрыть"><i class="fa-solid fa-xmark"></i></button>
                        <h5 class="modal-title">Вам открыли доступ к статье</h5>
                    </div>
                    <div class="modal-body text-center">
                        <p style="text-align: justify;">
	                        <?= $philantropist_name; ?> предоставил(а) вам доступ к данной статье.<br>
                            Доступ предоставлен на 3 дня с момента открытия статьи.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                jQuery('#shared-article-modal').modal('show');
            });
        </script>
	    <?php
    }

}