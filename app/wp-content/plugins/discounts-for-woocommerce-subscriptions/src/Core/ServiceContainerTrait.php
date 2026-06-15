<?php namespace MeowCrew\SubscriptionsDiscounts\Core;

trait ServiceContainerTrait {

	public function getContainer() {
		return ServiceContainer::getInstance();
	}

}
