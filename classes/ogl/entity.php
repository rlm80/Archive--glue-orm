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
 */

class OGL_Entity {
	// Entity cache :
	static protected $entities = array();

	// Properties that may NOT be set in children classes :
	private $name;

	// Properties that may be set in children classes :
	protected $pk;
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

	public function default_fk() { // TODO pas utiliser col mais field[col]
		$fk		= array();
		$name	= $this->name();
		foreach ($this->pk() as $col)
			$fk[$col] = $name.'_'.$col;
		return $fk;
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

	public function get_object($row, $alias = null) {
		$pk = $this->pk_encode($row, $alias);
		if( ! isset($this->map[$pk]))
			$this->map[$pk] = $this->create_object($row, $alias);
		return array($pk, $this->map[$pk]);
	}

	protected function pk_encode($row, $alias) {
		$prefix = isset($alias) ? $alias.':' : '';
		foreach($this->pk() as $f) {
			if (isset($row[$prefix.$f]))
				$pkval[$f] = $this->cast_value($f, $row[$prefix.$f]);
			else
				throw new Kohana_Exception("Key ".$prefix.$f." is expected.");
		}
		return json_encode($pkval);
	}

	public function pk_decode($str) {
		return json_decode($str, true);
	}

	public function create_object($row, $alias = null) {
		$prefix = isset($alias) ? $alias.':' : '';
		$fields = $this->fields();
		$class	= $this->model();
		$object = new $class;
		foreach(array_keys($fields) as $f) {
			if (isset($row[$prefix.$f])) {
				$property = $fields[$f]['property'];
				$object->$property = $this->cast_value($f, $row[$prefix.$f]);
			}
		}
		return $object;
	}

	public function cast_value($field, $str) {
		$fields = $this->fields();
		switch($fields[$field]['phptype']) {
			case 'int' :		$val = (int)$str;		break;
			case 'float' :		$val = (float)$str;		break;
			case 'boolean' :	$val = (boolean)$str;	break;
			default :			$val = (string)$str;	break;
		}
		return $val;
	}

	// Hooks
	public function on_delete($query, $alias) {}
	public function on_load($query, $alias) {}

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


