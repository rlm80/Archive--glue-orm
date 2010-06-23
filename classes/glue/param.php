<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 */

abstract class Glue_Param {
	public $symbol;

	abstract public function value();
}
