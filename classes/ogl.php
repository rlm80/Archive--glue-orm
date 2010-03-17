<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL {
	public static function load($entity_name, &$set) {
		return new OGL_Query_Load($entity_name, $set);
	}

	public static function delete($entity_name, &$set) {
		return new OGL_Query_Delete($entity_name, $set);
	}

	public static function insert($entity_name, $objects) {
		OGL::entity($entity_name)->insert($objects);
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

	public static function show_columns($db, $table) {
		static $cache = array();
		if ( ! isset($cache[$db][$table]))
			$cache[$db][$table] = Database::instance($db)->list_columns($table);
		return $cache[$db][$table];
	}
}