<?php

namespace MedvisementUserTour;

class TourHandler {

	private $tours = [];

	public function setup() {
		$this->tours = $this->loadTours();
	}

	public function getTours() {
		return $this->tours;
	}

	public function getVisibleTours() {

		$current_user = wp_get_current_user();
		$visibleTours = [];
		$completed_tours = get_user_meta( $current_user->ID, 'completed_tours', TRUE );

		foreach ( $this->tours as $tour ) {
			$tour_name = ( new \ReflectionClass( $tour ) )->getShortName();

			// Если тур отключен
			if ( ! $tour->isVisible() ) {
				continue;
			}

			// Принудительно показываем тур
			if ( isset($_GET['tour']) && $_GET['tour'] === 'force' ) {
				$visibleTours[] = $tour;
				continue;
			}

			// Если тур уже был пройден
			if ( is_array($completed_tours) && in_array( $tour_name, $completed_tours ) ) {
				continue;
			}

			$visibleTours[] = $tour;
		}


		// Сортируем по приоритету
		usort($visibleTours, function($a, $b) {
			return strnatcmp($a::priority, $b::priority);
		});

		return $visibleTours;
	}

	public function getToursClassesAsArray() {
		$toursClasses = [];

		foreach ( $this->tours as $tour ) {
			$toursClasses[] = ( new \ReflectionClass( $tour ) )->getShortName();
		}

		return $toursClasses;
	}

	private function loadTours() {
		$tours = [];

		foreach (glob(MEDVISEUSERTOUR_PLUGIN_DIR . 'Tours/*.php') as $file)
		{
			require_once $file;

			$class = "MedvisementUserTour\\Tours\\" . basename($file, '.php');

			if ( ! class_exists( $class ) ) {
				continue;
			}

			$tours[] = new $class;
		}

		// Сортируем по алфавиту
		usort($tours, function($a, $b) {
			return strcmp($a::name, $b::name);
		});

		return $tours;
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