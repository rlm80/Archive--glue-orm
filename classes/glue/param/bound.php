<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Param_Bound extends Glue_Param {
    public $var;

	public function __construct(&$var) {
		$this->var =& $var;
	}

	public function value() {
		return $this->var;
	}
}