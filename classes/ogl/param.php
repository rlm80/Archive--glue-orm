<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class OGL_Param {
	public $symbol;

	abstract public function value();
}