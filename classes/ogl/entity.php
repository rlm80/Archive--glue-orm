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
	private $name;

	// Properties that may be set in children classes :
	protected $pk;
	protected $default_fk;
	protected $table;
	protected $model;
	protected $fields;

	// Identity map : (move this at query level ?)
	protected $map = array();

	protected function __construct($name, $pk=null, $table=null, $model=null) {
		// Set properties :
		$this->name		= $name;
		$this->pk		= $pk;
		$this->table	= $table;
		$this->model	= $model;
	}

	final public function name() {
		return $this->name;
	}

	public function pk() {
		if ( ! isset($this->pk))
			$this->pk = array('id');
		return $this->pk;
	}

	public function table() {
		if ( ! isset($this->table))
			$this->table = inflector::plural($this->name);
		return $this->table;
	}

	public function model() {
		if ( ! isset($this->model)) {
			$model			= 'OGL_Model_'.ucfirst($this->name);
			$this->model	= class_exists($model) ? $model : 'StdClass';
		}
		return $this->model;
	}

	public function relationship($name) {
		return OGL_Relationship::get($this->name, $name);
	}

	public function default_fk() {
		if ( ! isset($this->default_fk)) {
			$this->default_fk = array();
			$name = $this->name();
			foreach ($this->pk() as $f)
				$this->default_fk[$f] = $name.'_'.$f;
		}
		return $this->default_fk;
	}

	public function fields() {
		if ( ! isset($this->fields)) {
			// Get fields data from db :
				$cols = Database::instance()->list_columns($this->table(), null, true);

			// Create fields array (this part needs more work...)
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
					$this->fields[$name] = array(
						'phptype'	=> $phptype,
						'property'	=> $name,
						'column'	=> $name,
					);
				}
		}
		return $this->fields;
	}

	// Return pk string representations of rows and creates missing objects in id map :
	public function get_pks($rows, $alias = null) {
		// Data :
		$prefix = isset($alias) ? $alias.':' : '';

		// Get pk string representations of rows :
		$prefixes = array_fill(0, count($rows), $prefix);
		$pks = array_map(array($this, 'pk_encode'), $rows, $prefixes);

		// Add new objects to id map :
		$diff = array_diff_key(array_flip($pks), $this->map);
		foreach($diff as $pk => $index)
			$this->map[$pk] = $this->create_object($rows[$index], $prefix);
		
		return $pks;
	}

	protected function pk_encode($row, $prefix) {
		foreach($this->pk() as $f)
			$vals[] = $this->cast($f, $row[$prefix.$f]);
		return json_encode($vals);
	}

	static public function pk_decode($pk) {
		return json_decode($pk);
	}

	protected function cast($field, $str) {
		$fields = $this->fields();
		switch($fields[$field]['phptype']) {
			case 'int' :		$val = (int)$str;		break;
			case 'float' :		$val = (float)$str;     break;
			case 'boolean' :	$val = (boolean)$str;   break;
			default :			$val = $str;
		}
		return $val;
	}

	protected function create_object($row, $prefix) {
		$fields = $this->fields();
		$class	= $this->model();
		$object = new $class;
		foreach(array_keys($fields) as $f) {
			if (isset($row[$prefix.$f])) {
				$property = $fields[$f]['property'];
				$object->$property = $this->cast($f, $row[$prefix.$f]);
			}
		}
		return $object;
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
		$fields = $this->fields();

		// Null req_fields means all fields are required :
		if ( ! isset($req_fields))
			$req_fields = array_keys($fields);

		// Add pk :
		$req_fields = array_merge($req_fields, array_diff($this->pk(), $req_fields));

		// Check fields :
		$errors = array_diff($req_fields, $fields);
		if (count($errors) > 0)
			throw new Kohana_Exception("The following fields do not belong to entity ".$this->name()." : ".implode(',', $errors));

		// Add fields to query :
		foreach ($req_fields as $name)
			$query->select(array($alias.'.'.$fields[$name]['column'], $alias.':'.$name));
	}
}


