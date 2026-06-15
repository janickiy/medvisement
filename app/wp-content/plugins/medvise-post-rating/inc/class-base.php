<?php

namespace MedvisementPostRating;

class Base {
	private static $instance;

	public $pluginPath;

	private function __construct() {
		$this->pluginPath = dirname( __DIR__ );

		PostReport::init();
		PostRating::init();
	}

	public static function pluginActivation() {
		PostReport::activate();
		PostRating::activate();
	}

	public static function pluginDeactivation() {
		PostReport::deactivate();
		PostRating::deactivate();
	}

	public static function init() {
		self::getInstance();
	}

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}