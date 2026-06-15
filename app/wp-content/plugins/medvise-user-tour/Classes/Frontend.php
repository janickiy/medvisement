<?php

namespace MedvisementUserTour;

class Frontend {

	public static function getInstance() {
		static $instance = FALSE;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	public function setup() {

		add_filter( 'woocommerce_get_query_vars', [ $this, 'woocommerce_get_query_vars' ], 10, 1 );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'woocommerce_account_menu_items' ], 99, 1 );
		add_filter( 'woocommerce_endpoint_tours_title', [ $this, 'woocommerce_endpoint_tours_title' ], 10, 1 );
		add_filter( 'woocommerce_account_tours_endpoint', [ $this, 'woocommerce_account_tours_endpoint' ], 10, 1 );

		add_action( 'wp_enqueue_scripts', [ $this, 'load_scripts' ] );

		add_action( 'wp_ajax_completed_tour', [ $this, 'ajax_completed_tour'] );
	}

	public function woocommerce_get_query_vars( $vars ) {
		$vars['tours'] = 'tours';
		return $vars;
	}

	public function woocommerce_endpoint_tours_title( $title ) {
		return 'Инструкции по использованию сайта';
	}

	public function woocommerce_account_tours_endpoint() {

		$ToursHandler = TourHandler::getInstance();

		?>
		<ul style="list-style:none;margin-left:0;">
			<?php foreach ( $ToursHandler->getTours() as $tour ): ?>
				<?php
				if ( ! $tour->hasAccess() ) {
					continue;
				}
				?>
			<li><?= $tour::name; ?>: <a target="_blank" href="<?= $tour->getStartPage(); ?>?tour=force">Запустить</a></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	public function woocommerce_account_menu_items( $items ) {

		$account_position = array_search( 'edit-account', array_keys($items) ) + 1;

		return array_slice( $items, 0, $account_position, true ) +
		       ['tours' => 'Инструкции по использованию сайта'] +
		       array_slice( $items, $account_position, count( $items ), true );
	}

	public function load_scripts() {

		wp_enqueue_style( 'ut-driver', MEDVISEUSERTOUR_PLUGIN_URL . 'assets/frontend/style.css', array(), MEDVISEUSERTOUR_PLUGIN_VERSION );

		wp_enqueue_script( 'ut-driver', MEDVISEUSERTOUR_PLUGIN_URL . 'node_modules/driver.js/dist/driver.js.iife.js', array(
			'jquery'
		), MEDVISEUSERTOUR_PLUGIN_VERSION, TRUE );

		wp_enqueue_script( 'ut-handler', MEDVISEUSERTOUR_PLUGIN_URL . 'assets/frontend/script.js', array(
			'ut-driver'
		), MEDVISEUSERTOUR_PLUGIN_VERSION, TRUE );

		wp_add_inline_script( 'ut-handler', $this->generateToursObject() );

	}

	public function ajax_completed_tour() {

		$current_user = wp_get_current_user();
		$ToursHandler = TourHandler::getInstance();

		$completed_tours = get_user_meta( $current_user->ID, 'completed_tours', TRUE );

		if ( empty($completed_tours) ) {
		    $completed_tours = [];
		}

		if ( empty($_POST['tour']) || ! in_array( $_POST['tour'], $ToursHandler->getToursClassesAsArray() ) ) {
			wp_send_json( [
				'success' => FALSE
			] );
		}

		$new_value = array_merge( $completed_tours, [$_POST['tour']] );

		update_user_meta( $current_user->ID, 'completed_tours', $new_value );

		wp_send_json( [
			'success' => TRUE
		] );
	}

	private function generateToursObject() {

		$ToursHandler = TourHandler::getInstance();

		ob_start();
		?>
        const MedviseTours = [
		<?php foreach ( $ToursHandler->getVisibleTours() as $tour ): ?>
            {
                'name': '<?= ( new \ReflectionClass( $tour ) )->getShortName(); ?>',
                'callback': function () { <?= $tour->getJS(); ?> }
            },
		<?php endforeach; ?>
        ];
		<?php

		return ob_get_clean();
	}
}