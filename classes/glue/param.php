<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class Glue_Param {
	public $symbol;

	abstract public function value();
}