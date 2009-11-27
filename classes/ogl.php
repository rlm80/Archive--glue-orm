<?php defined('SYSPATH') OR die('No direct access allowed.');
// test git   
class OGL {
	// Single OGL instance :
	protected static $instance;

	// Private constructor :
	protected function  __construct() {}

	// Get single instance :
	static protected function instance() {
		if ( ! isset(self::$instance))
			self::$instance = new self;
		return self::$instance;
	}

	public static function load($expr) {
		return self::instance()->_load($expr);
	}

	protected function _load($expr) {
		return new OGL_Query($expr);
	}
}