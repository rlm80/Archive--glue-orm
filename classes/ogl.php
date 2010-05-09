<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL {
	public static function create($entity_name, $array) {
		return OGL::entity($entity_name)->create($array);
	}

	public static function select($entity_name, $conditions = array(), $order_by = null, $limit = null, $offset = null) {
		return OGL::entity($entity_name)->select($conditions, $order_by, $limit, $offset);
	}

	public static function delete($entity_name, $conditions = null) {
		return OGL::entity($entity_name)->delete($conditions);
	}		

	public static function insert($entity_name, $objects) {
		OGL::entity($entity_name)->insert($objects);
	}

	public static function update($entity_name, $objects, $fields = null) {
		OGL::entity($entity_name)->update($objects, $fields);
	}

	public static function qselect($entity_name, &$set) {
		return new OGL_Query_Select($entity_name, $set);
	}

	public static function qdelete($entity_name, &$set) {
		return new OGL_Query_Delete($entity_name, $set);
	}

	public static function param($name) {
		return new OGL_Param_Set($name);
	}

	public static function bind(&$var) {
		return new OGL_Param_Bound($var);
	}

	public static function set($entity_name, $objects = array()) {
		$set = new OGL_Set('', OGL::entity($entity_name));
		$set->set_objects($objects);
		return $set;
	}

	public static function entity($entity_name) {
		return OGL_Entity::get($entity_name);
	}

	public static function show_columns($table, $db = 'default') {
		static $cache = array();
		if ( ! isset($cache[$db][$table]))
			$cache[$db][$table] = Database::instance($db)->list_columns($table);
		return $cache[$db][$table];
	}
}