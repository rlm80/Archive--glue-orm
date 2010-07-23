<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 */

class Glue {
	public static function create($entity_name, $array) {
		return glue::entity($entity_name)->create($array);
	}

	public static function select($entity_name, &$set = null, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		return new Glue_Query_Select($entity_name, $set, $conditions, $order_by, $limit, $offset);
	}

	public static function delete($entity_name, &$set = null, $conditions = null) {
		return new Glue_Query_Delete($entity_name, $set, $conditions);
	}

	public static function clear($entity_name = null) {
		if (isset($entity_name))
			glue::entity($entity_name)->clear();
		else {
			foreach(Glue_Entity::get_all() as $mapper)
				$mapper->clear();
		}
	}

	public static function param($name) {
		return new Glue_Param_Set($name);
	}

	public static function bind(&$var) {
		return new Glue_Param_Bound($var);
	}

	public static function set() {
		$args	= func_get_args();
		$set	= new Glue_Set;
		if (count($args) > 0) $set->set($args);
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
