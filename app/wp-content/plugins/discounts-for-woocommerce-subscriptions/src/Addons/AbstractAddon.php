<?php namespace MeowCrew\SubscriptionsDiscounts\Addons;

use MeowCrew\SubscriptionsDiscounts\Core\ServiceContainerTrait;

abstract class AbstractAddon {

	use ServiceContainerTrait;

	abstract public function getName();

	abstract public function isActive();

	abstract public function run();
}
