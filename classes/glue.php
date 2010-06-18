<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue {
	public static function create($entity_name, $array) {
		return glue::entity($entity_name)->create($array);
	}

	public static function select($entity_name, $conditions = array(), $order_by = null, $limit = null, $offset = null) {
		return glue::entity($entity_name)->select($conditions, $order_by, $limit, $offset);
	}

	public static function delete($entity_name, $conditions = null) {
		return glue::entity($entity_name)->delete($conditions);
	}		

	public static function insert($entity_name, $objects) {
		glue::entity($entity_name)->insert($objects);
	}

	public static function update($entity_name, $objects, $fields = null) {
		glue::entity($entity_name)->update($objects, $fields);
	}

	public static function qselect($entity_name, &$set) {
		return new Glue_Query_Select($entity_name, $set);
	}

	public static function qdelete($entity_name, &$set) {
		return new Glue_Query_Delete($entity_name, $set);
	}

	public static function param($name) {
		return new Glue_Param_Set($name);
	}

	public static function bind(&$var) {
		return new Glue_Param_Bound($var);
	}

	public static function set($entity_name, $objects = array()) {
		$set = new Glue_Set('', glue::entity($entity_name));
		$set->set_objects($objects);
		return $set;
	}

	public static function entity($entity_name) {
		return Glue_Entity::get($entity_name);
	}

	public static function relationship($entity_name, $relationship_name) {
		return Glue_Relationship::get($entity_name, $relationship_name);
	}

	public static function show_columns($table, $db = 'default') {
		static $cache = array();
		if ( ! isset($cache[$db][$table]))
			$cache[$db][$table] = Database::instance($db)->list_columns($table);
		return $cache[$db][$table];
	}
}