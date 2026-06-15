<?php
/** @var int $userId */
/** @var int $postId */
/** @var int $attempts */

?>
    <form class="post-report" method="post">
        <div class="post-report__title">Если вы нашли ошибку, напишите нам об этом:</div>
        <div class="post-report__attempts">
			<?php if ( $attempts > 0 ) { ?>
                Осталось сообщений:
                <div class="post-report__attempts-value"><?php echo esc_html( $attempts ); ?></div>
			<?php } else { ?>
                Вы исчерпали лимит сообщений
			<?php } ?>
        </div>
        <div class="post-report__messages"></div>
		<?php if ( $attempts > 0 ) { ?>
            <div class="post-report__form-inner">
                <div class="post-report__field-wrap">
                    <textarea maxlength="<?php echo MedvisementPostRating\PostReport::MAX_MESSAGE_LENGTH; ?>" name="message" rows="8"
                              class="post-report__field" placeholder="Опишите ошибку"></textarea>
                </div>
                <div class="post-report__submit">
                    <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                    <input type="hidden" name="action" value="medinfo_post_report">
					<?php wp_nonce_field( 'medinfo_post_report_nonce', 'medinfo_post_report_nonce' ); ?>
                    <input name="submit" type="submit" class="submit" value="Сообщить об ошибке">
                </div>
            </div>
		<?php } ?>
    </form>
    <script>
        (function ($) {
            $(function ($) {
                const $form = $('.post-report');
                const $messagesContainer = $('.post-report__messages');

                $form.on('submit', function (e) {
                    const $form = $(this);
                    const data = $form.serialize();

                    if ($form.hasClass('form-submission')) {
                        return false;
                    }

                    $form.addClass('form-submission');

                    $messagesContainer.empty();

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

                        if (r.data && typeof r.data.attempts !== 'undefined') {
                            if (r.data.attempts > 0) {
                                $('.post-report__attempts-value').text(r.data.attempts);
                            } else {
                                $('.post-report__form-inner').hide();
                                $('.post-report__attempts').text('Вы исчерпали лимит сообщений');
                            }
                        }
                    }).fail(() => {
                        $messagesContainer.append('<div class="message message_error">При выполнении запроса возникла ошибка</div>');
                    }).always(() => {
                        $form.removeClass('form-submission');
                        $form.get(0).reset();
                    });

                    return false;
                });
            });
        })(jQuery);
    </script>
<?php
