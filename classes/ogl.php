<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL {
	// Constants :
	const ASC	= 1;
	const DESC	= 2;
	const ROOT	= 3;
	const SLAVE	= 4;
	const AUTO	= 5;

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

	public static function load($entity_name, &$set) {
		return self::instance()->_load($entity_name, $set);
	}

	protected function _load($entity_name, &$set) {
		return new OGL_Query($entity_name, $set);
	}
}