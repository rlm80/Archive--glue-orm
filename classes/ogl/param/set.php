<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Param_Set extends OGL_Param {
	public $name;
	public $value;

	public function __construct($name) {
		$this->name = $name;
	}

	public function value() {
		return $this->value;
	}	
}