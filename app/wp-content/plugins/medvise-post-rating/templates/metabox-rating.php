<?php
/** @var int $votesQty */
/** @var int $avgRating */
/** @var array $votes */

if ( $votesQty < 1 ) {
	echo 'Статью пока еще никто не оценил';

	return;
}
?>
    Рейтинг статьи: <?php echo number_format( $avgRating, 2 ); ?> (<?php echo $votesQty; ?> голосов)

<?php if ( ! empty( $votes ) ) { ?>
    <table width="100%">
        <tr>
            <td width="15%"><b>Дата</b></td>
            <td width="15%"><b>Пользователь</b></td>
            <td width="10%"><b>Голос</b></td>
            <td width="60%"><b>Примечание</b></td>
        </tr>
		<?php foreach ( $votes as $vote ) {
			$date = date( 'd.m H:i', strtotime( $vote->time ) );
			$user = get_user_by( 'id', $vote->user_id );
			?>
            <tr>
                <td><?php echo $date; ?></td>
                <td>
                    <a href="/wp-admin/user-edit.php?user_id=<?php echo $user->ID; ?>"><?php echo esc_html( $user->data->display_name ); ?></a>
                </td>
                <td><?php echo (int) $vote->vote; ?></td>
                <td><?= $vote->message; ?></td>
            </tr>
		<?php } ?>
    </table>
	<?php
}
