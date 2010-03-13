<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL {
	// Constants :
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

	public static function param($name) {
		return self::instance()->_param($name);
	}

	protected function _param($name) {
		return new OGL_Param_Set($name);
	}

	public static function bind(&$var) {
		return self::instance()->_bind($var);
	}

	protected function _bind(&$var) {
		return new OGL_Param_Bound($var);
	}

	public static function sort($entity_name, &$objects, $clause) {
		return self::instance()->_sort($entity_name, $objects, $clause);
	}

	protected function _sort($entity_name, &$objects, $clause) {
		OGL_Entity::get($entity_name)->object_sort($objects, $clause);
	}

	public static function delete($entity_name, $objects) {
		return self::instance()->_delete($entity_name, $objects);
	}

	protected function _delete($entity_name, $objects) {
		OGL_Entity::get($entity_name)->object_delete($objects);
	}
}