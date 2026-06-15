<?php
/** @var int $userId */
/** @var int $postId */
/** @var object $userVote */
/** @var float $avgRating */
/** @var int $votesQty */

?>
    <form class="post-rating" method="post">
		<?php if ( \MedvisementPostRating\PostRating::allowedToReadPostRating() ) { ?>
            <div class="post-rating__no-vote" style="<?php echo $votesQty > 0 ? 'display: none' : '' ?>">
                Статью пока никто не оценил
            </div>
            <div class="post-rating__avg" style="<?php echo $votesQty > 0 ? '' : 'display: none' ?>">
                Рейтинг статьи
                <span class="post-rating__avg-value"><?php echo number_format( $avgRating, 2 ); ?></span>
                (голосов <span class="post-rating__qty"><?php echo $votesQty; ?></span>)
            </div>
		<?php } ?>
        <div class="post-rating__vote-wrap">
            Оцените статью:
            <div class="post-rating__buttons">
				<?php for ( $i = 1; $i <= 5; $i ++ ) { ?>
                    <div class="post-rating__btn <?php echo ( $userVote && $userVote->vote == $i ) ? 'active' : ''; ?>"
                         data-value="<?php echo $i; ?>"><?php echo $i; ?></div>
				<?php } ?>
            </div>
            <select name="vote" style="display: none">
				<?php
				for ( $i = 1; $i <= 5; $i ++ ) {
					$selected = $userVote && $userVote->vote === $i ? $i : 5;
					?>
                    <option value="<?php echo $i; ?>" <?php echo $selected === $i ? 'selected="selected"' : ''; ?>><?php echo $i; ?></option>
				<?php } ?>
            </select>
        </div>
        <div class="post-rating__require-message" style="<?= ( $userVote && $userVote->vote < 4 ) ? 'display:block;' : ''; ?>">
            <textarea name="message" rows="8" class="post-report__field" <?= ( $userVote && $userVote->vote < 4 ) ? 'required' : ''; ?> placeholder="Расскажите, что вам не понравилось в статье"><?php echo $userVote->message; ?></textarea>
            <input name="submit" type="submit" class="submit" value="Оценить">
        </div>
        <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
        <input type="hidden" name="action" value="medinfo_post_rating">
		<?php wp_nonce_field( 'medinfo_post_rating_nonce', 'medinfo_post_rating_nonce' ); ?>
        <div class="post-rating__messages"></div>
    </form>
    <script>
        (function ($) {
            $(function ($) {
                const $form = $('.post-rating');
                const $select = $form.find('select[name="vote"]');
                const $messagesContainer = $('.post-rating__messages');
                const $messageArea = $('.post-rating__require-message');

                $('.post-rating__buttons .post-rating__btn').on('click', function (e) {

                    if ( $form.hasClass('processing') ) {
                        e.preventDefault();
                        return false;
                    }

                    const value = $(this).data('value');

                    $messagesContainer.empty();
                    $('.post-rating__btn').removeClass('active');
                    $(this).addClass('active');

                    $select.val(value).change();

                    if (value >= 4) {
                        $form.find('textarea').prop('required', false);
                        $messageArea.slideUp();
                        $form.find('textarea').val('');
                        $form.submit();
                    } else {
                        $form.find('textarea').prop('required', true);
                        $messageArea.slideDown();
                    }

                });

                $form.on('submit', function (e) {
                    const $form = $(this);
                    const data = $form.serialize();
                    const $messageArea = $('.post-rating__require-message');

                    if ( $form.hasClass('processing') ) {
                        e.preventDefault();
                        return false;
                    }

                    $messagesContainer.empty();
                    $form.addClass('processing');
                    $form.find('textarea').prop('disabled', true);

                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        data: data,
                        method: 'post',
                        dataType: 'json'
                    }).then((r) => {
                        if (r.message) {
                            const type = r.status === 'ok' ? 'success' : 'error';
                            $messagesContainer.append('<div class="message message_' + type + '">' + r.message + '</div>');
                        }

                        $('.post-rating__no-vote').hide();
                        $('.post-rating__avg').show();

                        if (r.data && typeof r.data.avgRating !== 'undefined') {
                            $('.post-rating__avg-value').text(r.data.avgRating);
                        }

                        if (r.data && typeof r.data.votesQty !== 'undefined') {
                            $('.post-rating__qty').text(r.data.votesQty);
                        }
                    }).fail(() => {
                        $messagesContainer.append('<div class="message message_error">При выполнении запроса возникла ошибка</div>');
                    }).always(() => {
                        $form.removeClass('processing');
                        $form.find('textarea').prop('disabled', false);
                    });

                    return false;
                });
            });
        })(jQuery);
    </script>
<?php
