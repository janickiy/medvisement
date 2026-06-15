<?php

namespace MedvisementUserTour\Interfaces;

interface Tour {

	public const name = 'Инструкция';

	public const priority = 10;

	public function isVisible();

	public function hasAccess();

	public function getJS();

	public function getStartPage();
}