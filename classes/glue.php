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

	public static function select($entity_name, &$set, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		// Init query :
		$query = new Glue_Query_Select($entity_name, $set);
		
		// Add conditions if any :
		self::add_conditions($query, $conditions);
		
		// Add order by, limit, offset if any :
		if (isset($order_by))	$query->order_by($order_by);
		if (isset($limit))		$query->limit($limit);
		if (isset($offset))		$query->offset($offset);
				
		return $query;
	}

	public static function delete($entity_name, &$set, $conditions = null) {
		// Init query :
		$query = new Glue_Query_Delete($entity_name, $set);
		
		// Add conditions if any :
		self::add_conditions($query, $conditions);		
		
		return $query;		
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

	public static function auto_load($class) {
		if(preg_match("/^Glue_Proxy_(.*)$/", $class, $matches) > 0)
			glue::entity($matches[1])->proxy_load_class();
	}
	
	protected static function add_conditions($query, $conditions) {
		if ( ! empty($conditions)) {
			// PK given ?
			if ( ! is_array($conditions)) {
				$pk = glue::entity($entity_name)->pk();
				if (count($pk) > 1)
					throw new Kohana_Exception("Scalar value used for multiple columns pk.");
				else
					$conditions = array($pk[0] => $conditions);
			}
			
			// Add conditions :			
			foreach($conditions as $field => $value) {
				if (is_array($value))
					$query->where($field, 'IN', $value);
				else
					$query->where($field, '=', $value);
			}
		}
	}	
}
