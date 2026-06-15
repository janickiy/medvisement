<?php

namespace MedviseMoneyPot;

class Frontend {

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		// скрываем страницу котла
		add_action( 'template_redirect', [ $this, 'hide_dashboard_from_users' ] );

		// Шорткод
		add_shortcode( 'medvise_moneypot', [ $this, 'render_moneypot_shortcode' ] );

	}

	public function hide_dashboard_from_users() {

		if ( ! is_page( 'moneypot' ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url() );
			die;
		}

		if ( ! Helper::can_see_moneypot( get_current_user_id() ) ) {
			wp_redirect( home_url() );
			die;
		}

		return true;
	}

	public function render_moneypot_shortcode() {

		global $wpdb;

		if ( current_user_can( 'administrator' ) ) {
			$specialty_terms    = get_terms( [
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
			] );
			$user_specialty_ids = wp_list_pluck( $specialty_terms, 'term_id' );
		} else {
			$user_specialty_ids = carbon_get_user_meta( get_current_user_id(), 'medvise_moneypot_specialties' );
		}

		$user_specialty_ids_implode = implode( ',', $user_specialty_ids );

		ob_start();
		?>
        <h3>Специальности:</h3>

        <ul style="font-size:16px">
			<?php foreach ( $user_specialty_ids as $specialty_id ): ?>
				<?php
				$query = "SELECT SUM(amount) FROM `{$wpdb->prefix}medvise_transactions` WHERE `target`=%s AND `target_id`=%d;";

				$specialty_amount = (int) $wpdb->get_var( $wpdb->prepare( $query, [
					'specialty',
					$specialty_id
				] ) );
				?>
                <li><?= get_term( $specialty_id )->name; ?>: <?= $specialty_amount; ?>₽
                </li>
			<?php endforeach; ?>
        </ul>

        <h5>Специальности: последние 5 выплат</h5>

		<?php
		$query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s AND `target_id` IN ({$user_specialty_ids_implode}) ORDER BY `id` DESC LIMIT 5;";

		$specialty_last_payouts = $wpdb->get_results( $wpdb->prepare( $query, [
			'admin',
			'specialty'
		] ) );
		?>

		<?php if ( empty( $specialty_last_payouts ) ): ?>
            <p style="font-style: italic;">Пусто</p>
		<?php else: ?>
            <ol>
				<?php foreach ( $specialty_last_payouts as $specialty_last_payout ): ?>
                    <li>
						<?= $specialty_last_payout->amount; ?>₽ -
						<?= $specialty_last_payout->note; ?>
                        (<?= $specialty_last_payout->created_at; ?>)
                    </li>
				<?php endforeach; ?>
            </ol>
		<?php endif; ?>

        <h5>Специальности: последние 10 начислений</h5>

		<?php
		$query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s AND `target_id` IN ({$user_specialty_ids_implode}) ORDER BY `id` DESC LIMIT 10;";

		$specialty_last_received = $wpdb->get_results( $wpdb->prepare( $query, [
			'order',
			'specialty'
		] ) );
		?>

		<?php if ( empty( $specialty_last_received ) ): ?>
            <p style="font-style: italic;">Пусто</p>
		<?php else: ?>
            <ol>
				<?php foreach ( $specialty_last_received as $specialty_last_receive ): ?>
                    <li>
						<?php if ( current_user_can( 'administrator' ) ): ?>
							<?= $specialty_last_receive->amount; ?>₽
						<?php endif; ?>
						<?= get_term( $specialty_last_receive->target_id )->name; ?>.
                        Заказ №<?= $specialty_last_receive->source_id; ?>,
                        (<?= $specialty_last_receive->created_at; ?>)
                    </li>
				<?php endforeach; ?>
            </ol>
		<?php endif; ?>

		<?php if ( current_user_can( 'administrator' ) ): ?>

			<?php
			$query = "SELECT SUM(amount) FROM `{$wpdb->prefix}medvise_transactions` WHERE `target`=%s AND `target_id`=%d;";

			$platform_amount = (int) $wpdb->get_var( $wpdb->prepare( $query, [
				'platform',
				1
			] ) );
			?>
            <h3>Платформа: <?= number_format( $platform_amount, 2 ); ?>₽</h3>

            <h5>Платформа: последние 5 выплат</h5>

			<?php
			$query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s ORDER BY `id` DESC LIMIT 5;";

			$specialty_last_payouts = $wpdb->get_results( $wpdb->prepare( $query, [
				'admin',
				'platform'
			] ) );
			?>

			<?php if ( empty( $specialty_last_payouts ) ): ?>
                <p style="font-style: italic;">Пусто</p>
			<?php else: ?>
                <ol>
					<?php foreach ( $specialty_last_payouts as $specialty_last_payout ): ?>
                        <li>
							<?= $specialty_last_payout->amount; ?>₽ -
							<?= $specialty_last_payout->note; ?>
                            (<?= $specialty_last_payout->created_at; ?>)
                        </li>
					<?php endforeach; ?>
                </ol>
			<?php endif; ?>

            <h5>Платформа: последние 10 начислений</h5>

			<?php
			$query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s ORDER BY `id` DESC LIMIT 10;";

			$specialty_last_received = $wpdb->get_results( $wpdb->prepare( $query, [
				'order',
				'platform'
			] ) );
			?>

			<?php if ( empty( $specialty_last_received ) ): ?>
                <p style="font-style: italic;">Пусто</p>
			<?php else: ?>
                <ol>
					<?php foreach ( $specialty_last_received as $specialty_last_receive ): ?>
                        <li>
							<?= $specialty_last_receive->amount; ?>₽
                            Заказ №<?= $specialty_last_receive->source_id; ?>,
                            (<?= $specialty_last_receive->created_at; ?>)
                        </li>
					<?php endforeach; ?>
                </ol>
			<?php endif; ?>

		<?php endif; ?>

        <p></p>

		<?php
		return ob_get_clean();
	}

}