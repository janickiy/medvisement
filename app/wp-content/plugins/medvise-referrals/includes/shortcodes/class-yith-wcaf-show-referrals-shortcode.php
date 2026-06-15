<?php
/**
 * Affiliate Dashboard shortcode - Referrals
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH/Affiliates/Classes
 * @version 2.0.0
 */

if ( ! defined( 'YITH_WCAF' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'YITH_WCAF_Show_Referrals_Shortcode' ) ) {
	/**
	 * Offer methods for basic shortcode handling
	 *
	 * @since 2.0.0
	 */
	class YITH_WCAF_Show_Referrals_Shortcode extends YITH_WCAF_Abstract_Shortcode {

		/**
		 * Current dashboard section
		 *
		 * It matches the endpoint if any; default section is summary
		 * This base class manages summary only; child classes will handle other sections
		 *
		 * @var string
		 */
		protected $section;

		/* === INIT === */

		/**
		 * Performs any required init operation
		 */
		public function init() {
			// configure shortcode basics.
			$this->tag         = 'yith_wcaf_show_referrals';
			$this->title       = "Приглашенные пользователи";
			$this->section     = 'referrals';
			$this->template    = "dashboard-{$this->section}.php";
			$this->description = "Приглашенные пользователи с обрезанными данными";
			$this->supports    = array();
		}

		/* === SECTION HANDLING === */

		public function render_section( $atts = array(), $content = '' ) {

            $valid_partner_time = time() - 604800;

			$users = get_users(
				array(
					'meta_query'  => array(
						'relation' => 'AND',
						[
							'key'     => 'partner_time',
							'value'   => $valid_partner_time,
							'compare' => '>=',
							'type'    => 'NUMERIC',
						],
						[
							'key'     => 'partner_id',
							'value' => get_current_user_id()
						],
					),
					'meta_key'    => 'partner_time',
					'number'      => 999,
					'count_total' => false,
					'orderby'     => 'meta_value_num',
                    'order'       => 'DESC',
				)
			);

			$re = '/([a-z0-9.!#$%&\'*+-\/=?^_`{|}~]{1,2})([a-z0-9.!#$%&\'*+-\/=?^_`{|}~]*)@([a-z0-9-]+)\.([a-z0-9-]+)/mi';
			?>
            <p>
                Список пользователей без оплаченных заказов, которые зарегистрировались или перешли по вашей ссылке за последние 7 дней.<br>
                По истечении 7 дней или совершения покупки, пользователи пропадают из этого списка.<br>
                Все ваши комиссии по оплатам отображаются во вкладке «Комиссии».
            </p>
            <table style="text-align: right">
                <tr>
                    <th>Email</th>
                    <th>Дата привязки</th>
                </tr>
	            <?php if ( empty( $users ) ): ?>
                    <tr>
                        <td colspan="2" style="text-align: center">Результатов не найдено</td>
                    </tr>
	            <?php endif; ?>
	            <?php foreach ( $users as $user ): ?>
                <?php
		            $customer_orders = get_posts( array(
			            'numberposts' => 1,
			            'meta_key'    => '_customer_user',
			            'meta_value'  => $user->ID,
			            'post_type'   => 'shop_order',
			            'post_status' => 'wc-completed',
			            'fields'      => 'ids',
		            ) );
                    // Делал покупку - не выводим
		            if ( count( $customer_orders ) >= 1 ) {
			            continue;
		            }
                    ?>
                    <tr>
                        <td>
				            <?php if ( empty( $user->user_email ) ): ?>
                                Telegram
				            <?php else: ?>
					            <?= preg_replace( $re, "$1***@***.$4", $user->user_email ); ?>
				            <?php endif; ?>
                        </td>
                        <td><?= date( "Y-m-d H:i", get_user_meta( $user->ID, 'partner_time', true ) ); ?></td>
                    </tr>
	            <?php endforeach; ?>
            </table>
			<?php
			return ob_get_clean();
		}

	}
}
