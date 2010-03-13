<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL {
	// Constants :
	const ROOT	= 1;
	const SLAVE	= 2;
	const AUTO	= 3;

	public static function load($entity_name, &$set) {
		return new OGL_Query($entity_name, $set);
	}

	public static function param($name) {
		return new OGL_Param_Set($name);
	}

	public static function bind(&$var) {
		return new OGL_Param_Bound($var);
	}

	public static function entity($entity_name) {
		return OGL_Entity::get($entity_name);
	}
}