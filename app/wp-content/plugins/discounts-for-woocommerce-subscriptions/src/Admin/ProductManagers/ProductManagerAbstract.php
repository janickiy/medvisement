<?php namespace MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;

/**
 * Class ProductManagerAbstract
 *
 * @package MeowCrew\SubscriptionsDiscounts\Admin\ProductManagers
 */
abstract class ProductManagerAbstract {

	use ServiceContainerTrait;

	/**
	 * Product Manager constructor.
	 *
	 * Register menu items and handlers
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Register manager hooks
	 */
	abstract protected function hooks();
}
