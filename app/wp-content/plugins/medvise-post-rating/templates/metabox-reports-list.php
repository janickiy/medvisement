<?php
/** @var array $reports */

if ( empty( $reports ) ) {
	echo 'Сообщения об ошибках от пользователей отсутствуют.';

	return;
}
?>
<table width="100%">
    <tr>
        <td width="10%"><b>Дата</b></td>
        <td width="15%"><b>Пользователь</b></td>
        <td width="75%"><b>Сообщение</b></td>
    </tr>
	<?php foreach ( $reports as $report ) {
		$date = date( 'd.m H:i', strtotime( $report->time ) );
		$user = get_user_by( 'id', $report->user_id );
		?>
        <tr>
            <td><?php echo $date; ?></td>
            <td>
                <a href="/wp-admin/user-edit.php?user_id=<?php echo $user->ID; ?>"><?php echo esc_html( $user->data->display_name ); ?></a>
            </td>
            <td><?php echo esc_html( $report->message ); ?></td>
        </tr>
	<?php } ?>
</table>
