<?php

namespace MedviseUserModules;

class Woocommerce {

	public static function getInstance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		// Добавляем шаблоны в меню вукомерса
		add_filter( 'woocommerce_get_query_vars', [ $this, 'woocommerce_get_query_vars' ], 10, 1 );
		add_filter( 'woocommerce_endpoint_templates-tab_title', [ $this, 'woocommerce_endpoint_templates_tab_title' ], 10, 1 );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'woocommerce_account_menu_items' ], 100, 1 );
		add_action( 'woocommerce_account_templates-tab_endpoint', [ $this, 'templates_tab_content' ], 10, 1 );

	}

	public function woocommerce_get_query_vars( $vars ) {
		$vars['templates-tab'] = 'templates-tab';

		return $vars;
	}

	public function woocommerce_endpoint_templates_tab_title() {
		return "Сохраненные шаблоны";
	}

	public function woocommerce_account_menu_items( $items ) {

		$account_position = array_search( 'edit-account', array_keys( $items ) ) + 1;

		return array_slice( $items, 0, $account_position, true ) +
		       [ 'templates-tab' => 'Список шаблонов' ] +
		       array_slice( $items, $account_position, count( $items ), true );
	}

	public function templates_tab_content(): void {

		$current_user = wp_get_current_user();
		if ( isset( $current_user ) ) {
			echo $this->display_templates_data_shortcode( $current_user->ID );
		}

	}

	public function display_templates_data_shortcode( $id_user, $per_page = 10 ) {
		$page          = isset( $_GET['templates-tab'] ) ? abs( (int) $_GET['templates-tab'] ) : 1; // Current page
		$total_records = $this->get_total_user_templates( $id_user );
		$data          = $this->get_user_templates( $id_user, $per_page, $page );

		ob_start();
		?>
        <div class="templates-table-container">
            <table class="templates-table">
                <thead>
                <tr>
                    <th>Статья</th>
                    <th>Название</th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $data as $row ): ?>
                    <tr>
                        <td>
                            <a href="<?= get_post_permalink($row->post_id); ?>" target="_blank"><?= get_the_title($row->post_id); ?></a>
                        </td>
                        <td><?= $row->title; ?></td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>

			<?php
			$total_pages = ceil( $total_records / $per_page );
			if ( $total_pages > 1 ):
				?>
                <div class="templates-table-pagination">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'templates-tab', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo; Previous' ),
						'next_text' => __( 'Next &raquo;' ),
						'total'     => $total_pages,
						'current'   => $page,
						'type'      => 'plain'
					) );
					?>
                </div>
			<?php endif; ?>
        </div>
		<?php
		return ob_get_clean();
	}

	public function get_user_templates( $user_id, $per_page = 10, $page_number = 1 ) {

		global $wpdb;
		$offset = ( $page_number - 1 ) * $per_page;

		$query = $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}medvise_user_templates` WHERE user_id=%d ORDER BY `post_id`, `id` ASC LIMIT %d, %d",
			[
				$user_id,
				$offset,
				$per_page
			] );

		return $wpdb->get_results( $query );
	}

	public function get_total_user_templates( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'medvise_user_templates';

		$query = "SELECT COUNT(*) as total FROM $table_name WHERE user_id=" . $user_id;

		return $wpdb->get_var( $query );
	}

}