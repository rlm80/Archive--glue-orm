<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * Entities are objects responsible for handling the conversion between database
 * table rows and model objects, for those elements defined as entities in an entity-relationship
 * diagram. They hold all available data about the underlying table, its columns and its primary key,
 * and about the model objects and their properties.
 *
 * Entities are always requested from OGL by name :
 * - by referring to them in an OGL query (ogl::load('users')),
 * - by requesting OGL to save an object in the database (ogl::save('user', $user)),
 * - by requesting OGL to delete an object from the database (ogl::delete('user', $user)),
 * - ...
 *
 * Entity objects behave as singletons. When an entity object is required by name,
 * OGL will instanciate :
 * a) the class OGL_Entity_<name> if it exists,
 * b) the default class OGL_Entity if no such class is found.
 *
 * Conventions used by the default OGL_Entity class :
 * - table : pluralized entity name
 * - pk : 'id'
 * - model class : 'Model_'.entity name if there is such a class, StdObject otherwise.
 *
 * If you wish to define your own pk, table or model, you need to create your
 * own OGL_Entity_<name> entity class.
 *
 * Idées pour les types :
 * - ne PAS introduire de fonction du genre cast_value() destinée à être redéfinie
 *   pour permettre les types exotiques. Ca alourdit et ralentit les choses et c'est
 *   inutile : les types en question peuvent être obtenus via des getters si nécessaires.
 * - ne PAS introduire de fonction du genre write_property() et read_property() destinées
 *   à être redéfinies pour permettre getters/setters. Des getters seraient nécessaires pour
 *   toutes les prioriétés de toutes façons, donc autant les mettre public.
 */

class OGL_Entity {
	// Entity cache :
	static protected $entities = array();

	// Properties that may NOT be set in children classes :
	public $name;
	public $fields;

	// Properties that may be set in children classes :
	public $pk;
	public $default_fk;
	public $table;
	public $model;

	// Identity map :
	protected $map = array();

	protected function __construct($name, $pk=null, $table=null, $fields=null, $model=null) {
		// Set properties :
		$this->name		= $name;
		$this->pk		= $pk;
		$this->table	= $table;
		$this->fields	= $fields;
		$this->model	= $model;

		// Init properties (order matters !!!) :
		if ( ! isset($this->model))			$this->model		= $this->default_model();
		if ( ! isset($this->table))			$this->table		= $this->default_table();
		if ( ! isset($this->fields))		$this->fields		= $this->default_fields();
		if ( ! isset($this->pk))			$this->pk			= $this->default_pk();
		if ( ! isset($this->default_fk))	$this->default_fk	= $this->default_default_fk();
	}

	protected function default_model() {
		$model = 'OGL_Model_'.ucfirst($this->name);
		return class_exists($model) ? $model : 'StdClass';
	}

	protected function default_table() {
		return inflector::plural($this->name);
	}

	protected function default_fields() {
		// Get fields data from db :
		$cols = Database::instance()->list_columns($this->table, null, true);

		// Create fields array :
		$fields = array();
		foreach($cols as $name => $data) {
			$dbtype = $data['COLUMN_TYPE'];
			if (preg_match('/^tinyint\b|^smallint\b|^mediumint\b|^int\b|^integer\b|^bigint\b/i', $dbtype) > 0)
				$phptype = 'int';
			elseif (preg_match('/^float\b|^numeric\b|^double\b|^decimal\b|^real\b/i', $dbtype) > 0)
				$phptype = 'float';
			elseif (preg_match('/^boolean\b|^bit\b/i', $dbtype) > 0)
				$phptype = 'boolean';
			else
				$phptype = 'string';
			$fields[$name] = array(
				'phptype'	=> $phptype,
				'property'	=> $name,
				'column'	=> $name,
			);
		}

		return $fields;
	}

	protected function default_pk() {
		return array('id');
	}

	protected function default_default_fk() {
		$default_fk = array();
		foreach ($this->pk as $f)
			$default_fk[$f] = $this->name.'_'.$f;
		return $default_fk;
	}

	public function relationship($name) {
		return OGL_Relationship::get($this->name, $name);
	}

	public function get_objects(&$rows, $alias = null) {
		// Data :
		$prefix = isset($alias) ? $alias.':' : '';
		$fields = $this->fields;
		$class	= $this->model;

		// Get pk string representations of each row :
		$pks = array();
		foreach($this->pk as $f) $cols[$prefix.$f] = 1;
		$nbr_rows = count($rows);
		for($i = 0; $i < $nbr_rows; $i++) {
			$arr = array_intersect_key($rows[$i], $cols);
			ksort($arr);
			$pks[$i] = json_encode(array_values($arr));
		}

		// Add new objects to id map :
		$indexes		= array_flip($pks); // distinct pk => row index mapping
		$field_names	= array_keys($fields);
		$diff			= array_diff_key($indexes, $this->map);
		foreach($diff as $pk => $index) {
			$row	= $rows[$index];
			$object = new $class;
			foreach($field_names as $f) {
				if (isset($row[$prefix.$f])) {
					$val = $row[$prefix.$f];
					settype($val, $fields[$f]['phptype']);
					$property = $fields[$f]['property'];
					$object->$property = $val;
				}
			}
			$this->map[$pk] = $object;
		}

		// Build distinct objects list :
		$distinct = array_intersect_key($this->map, $indexes);

		// Load objects into result set :
		$key = $prefix.'__object';
		$objects = array();
		foreach($pks as $index => $pk)
			$rows[$index][$key] = $this->map[$pk];

		// Return objects :
		return $distinct;
	}

	// Returns an associative array with pk field names and values for the given object
	public function get_pk($obj) {
		$fields = $this->fields;
		foreach($this->pk as $f) {
			$property = $fields[$f]['property'];
			$pk[$f] = $obj->$property;
		}
		return $pk;
	}

	// Lazy loads an entity object, stores it in cache, and returns it :
	static public function get($name, $pk=null, $table=null, $model=null) {
		if( ! isset(self::$entities[$name]))
			self::$entities[$name] = self::create($name, $pk, $table, $model);
		return self::$entities[$name];
	}

	// Chooses the right entity class to use, based on the name of the entity and
	// the available classes.
	static protected function create($name, $pk=null, $table=null, $model=null) {
		$class = 'OGL_Entity_'.ucfirst($name);
		if (class_exists($class))
			$entity = new $class($name, $pk, $table, $model);
		else
			$entity	= new self($name, $pk, $table, $model);
		return $entity;
	}

	/* QUERY BUILDING STUFF */
	public function add_fields($query, $req_fields, $alias) {
		// Null req_fields means all fields are required :
		if ( ! isset($req_fields))
			$req_fields = array_keys($this->fields);
		else {
			// Add pk :
			$req_fields = array_merge($req_fields, array_diff($this->pk, $req_fields));

			// Check fields :
			$errors = array_diff($req_fields, array_keys($this->fields));
			if (count($errors) > 0)
				throw new Kohana_Exception("The following fields do not belong to entity ".$this->name." : ".implode(',', $errors));
		}

		// Add fields to query :
		foreach ($req_fields as $name)
			$query->select(array($alias.'.'.$this->fields[$name]['column'], $alias.':'.$name));
	}
}


