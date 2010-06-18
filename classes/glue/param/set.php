<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Param_Set extends Glue_Param {
	public $name;
	public $value;

	public function __construct($name) {
		$this->name = $name;
	}

	public function value() {
		return $this->value;
	}	
}