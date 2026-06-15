<?php /** @noinspection SqlNoDataSourceInspection */

namespace MedviseMoneyPot;

class Admin {

	public function setup() {

		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

	}

	public function admin_menu() {

		$can_see_moneypot = Helper::can_see_moneypot( get_current_user_id() );

		if ( $can_see_moneypot ) {
			add_menu_page( 'Котел денежный', 'Котел денежный', 'publish_posts', 'moneypot',
				[ $this, 'moneypot_page' ], 'dashicons-chart-bar', 71 );
		}

		add_submenu_page( 'moneypot', 'Создать выплату', 'Создать выплату', 'manage_options',
			'moneypot-payout',
			[ $this, 'moneypot_payout_page' ] );
	}

	public function moneypot_page() {

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

		?>
        <div class="wrap">
            <h3>Специальности: </h3>
            <ul style="font-size:14px">
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

            <h4>Специальности: последние 5 выплат</h4>

            <?php
	        $query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s AND `target_id` IN ({$user_specialty_ids_implode}) ORDER BY `id` DESC LIMIT 5;";

	        $specialty_last_payouts = $wpdb->get_results( $wpdb->prepare( $query, [
		        'admin',
		        'specialty'
	        ] ) );
	        ?>

	        <?php if ( empty( $specialty_last_payouts ) ): ?>
            <em>Пусто</em>
	        <?php else: ?>
            <ol>
                <?php foreach ($specialty_last_payouts as $specialty_last_payout): ?>
                <li>
                    <?= $specialty_last_payout->amount; ?>₽ -
                    <?= $specialty_last_payout->note; ?>
                    (<?= $specialty_last_payout->created_at; ?>)
                </li>
            <?php endforeach; ?>
            </ol>
	        <?php endif; ?>

            <h4>Специальности: последние 10 начислений</h4>

	        <?php
	        $query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s AND `target_id` IN ({$user_specialty_ids_implode}) ORDER BY `id` DESC LIMIT 10;";

	        $specialty_last_received = $wpdb->get_results( $wpdb->prepare( $query, [
		        'order',
		        'specialty'
	        ] ) );
	        ?>

	        <?php if ( empty( $specialty_last_received ) ): ?>
                <em>Пусто</em>
	        <?php else: ?>
                <ol>
			        <?php foreach ($specialty_last_received as $specialty_last_receive ): ?>
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

                <h4>Платформа: последние 5 выплат</h4>

	            <?php
	            $query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s ORDER BY `id` DESC LIMIT 5;";

	            $specialty_last_payouts = $wpdb->get_results( $wpdb->prepare( $query, [
		            'admin',
		            'platform'
	            ] ) );
	            ?>

	            <?php if ( empty( $specialty_last_payouts ) ): ?>
                    <em>Пусто</em>
	            <?php else: ?>
                    <ol>
			            <?php foreach ($specialty_last_payouts as $specialty_last_payout): ?>
                            <li>
	                            <?= $specialty_last_payout->amount; ?>₽ -
	                            <?= $specialty_last_payout->note; ?>
					            (<?= $specialty_last_payout->created_at; ?>)
                            </li>
			            <?php endforeach; ?>
                    </ol>
	            <?php endif; ?>

                <h4>Платформа: последние 10 начислений</h4>

	            <?php
	            $query = "SELECT * FROM `{$wpdb->prefix}medvise_transactions` WHERE `source`=%s AND `target`=%s ORDER BY `id` DESC LIMIT 10;";

	            $specialty_last_received = $wpdb->get_results( $wpdb->prepare( $query, [
		            'order',
		            'platform'
	            ] ) );
	            ?>

	            <?php if ( empty( $specialty_last_received ) ): ?>
                    <em>Пусто</em>
	            <?php else: ?>
                    <ol>
			            <?php foreach ($specialty_last_received as $specialty_last_receive ): ?>
                            <li>
	                            <?= $specialty_last_receive->amount; ?>₽
                                Заказ №<?= $specialty_last_receive->source_id; ?>,
					            (<?= $specialty_last_receive->created_at; ?>)
                            </li>
			            <?php endforeach; ?>
                    </ol>
	            <?php endif; ?>

            <?php endif; ?>
        </div>
		<?php
	}

	public function moneypot_payout_page() {

		global $wpdb;

		$specialty_terms    = get_terms( [
			'taxonomy'   => 'specialty',
			'hide_empty' => false,
		] );
		$specialties = wp_list_pluck( $specialty_terms, 'name', 'term_id' );

		if ( ! empty( $_POST['amount'] ) && ! empty( $_POST['target'] ) ) {

			$datetime_now = new \DateTime('now');
            $amount = (int) $_POST['amount'];
            $amount = $amount * -1;
			$note = (string) $_POST['note'];

            // Выплата на платформу
            if ( $_POST['target'] === 'platform' ) {
	            $query = "INSERT INTO `{$wpdb->prefix}medvise_transactions` (source, source_id, target, target_id, amount, created_at, note) " .
	                     "VALUES (%s, %d, %s, %d, %s, %s, %s);";

	            $wpdb->query( $wpdb->prepare( $query, [
		            'admin',
		            get_current_user_id(),
		            'platform',
		            1,
		            $amount,
		            $datetime_now->format( 'Y-m-d' ),
		            $note
	            ] ) );
            }
            else {

                $target = (int) $_POST['target'];

	            $query = "INSERT INTO `{$wpdb->prefix}medvise_transactions` (source, source_id, target, target_id, amount, created_at, note) " .
	                     "VALUES (%s, %d, %s, %d, %s, %s, %s);";

	            $wpdb->query( $wpdb->prepare( $query, [
		            'admin',
		            get_current_user_id(),
		            'specialty',
		            $target,
		            $amount,
		            $datetime_now->format( 'Y-m-d' ),
		            $note
	            ] ) );
            }

			?>
            <div id="message" class="notice notice-success is-dismissible">
                <p>Выплата успешно создана.</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Закрыть сообщение.</span></button>
            </div>
			<?php
		}

		?>
        <div class="wrap">
            <form method="POST">
                <p>
                    <label for="target">Выплатить из: </label>
                    <select id="target" name="target">
                        <option value="platform">Платформа</option>
	                    <?php foreach ( $specialties as $specialty_id => $specialty_name ): ?>
                            <option value="<?= $specialty_id; ?>"><?= $specialty_name; ?></option>
	                    <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="amount">Сумма:</label>
                    <input type="number" id="amount" name="amount" min="1" step="1" />
                </p>
                <p>
                    <label for="amount">Заметка:</label>
                    <textarea id="note" name="note" rows="5"></textarea>
                </p>
                <p>
                    <input type="submit" value="Создать выплату" />
                </p>
            </form>
        </div>
		<?php
	}

	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'chart_umd_js', MEDVISE_MONEYPOT_PLUGIN_DIR . '/js/chart.umd.min.js' );
	}

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}