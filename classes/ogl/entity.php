<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Entity {
	// Entity cache :
	static protected $entities = array();

	// Properties that may NOT be set in children classes :
	public $name;
	
	// Properties that may be set in children classes :
	public $pk;
	public $fk;
	public $table;
	public $joins;
	public $model;
	public $fields;

	// Identity map :
	protected $map = array();

	protected function __construct($name) {
		// Set properties :
		$this->name	= $name;

		// Init properties (order matters !!!) :
		if ( ! isset($this->model))		$this->model	= $this->default_model();
		if ( ! isset($this->table))		$this->table	= $this->default_table();
		if ( ! isset($this->joins))		$this->joins	= $this->default_joins();

		// Turn joins into something easier to work with :
		$this->adapt_joins();

		// Keep initializing properties :
		if ( ! isset($this->fields))	$this->fields	= $this->default_fields();
		if ( ! isset($this->pk))		$this->pk		= $this->default_pk();
		if ( ! isset($this->fk))		$this->fk		= $this->default_fk();
	}

	protected function default_model() {
		$model = 'OGL_Model_'.ucfirst($this->name);
		return class_exists($model) ? $model : 'StdClass';
	}

	protected function default_table() {
		return inflector::plural($this->name);
	}

	protected function default_joins() {
		return array();
	}

	protected function default_fields() {
		// Get fields from main table :
		$cols = Database::instance()->list_columns($this->table);
		foreach ($cols as $name => $data)
			$fields[$name] = array(
				'phptype'	=> $data['type'],
				'property'	=> $name,
				'table'		=> $this->table,
				'column'	=> $name
			);

		// Get fields from join tables :
		foreach ($joins as $table => $columns) {
			$cols = Database::instance()->list_columns($table);
			foreach($cols as $name => $data)
				if ( ! array_key_exists($name, $columns)) {
					$fields[$name] = array(
						'phptype'	=> $data['type'],
						'property'	=> $name,
						'table'		=> $table,
						'column'	=> $name
					);
				}
		}

		return $fields;
	}

	protected function default_pk() {
		return array('id');
	}

	protected function default_fk() {
		foreach ($this->pk as $f)
			$fk[$f] = $this->name.'_'.$f;
		return $fk;
	}

	public function from($query, $alias) {
		// Main table :
		$query->from(array($this->table, $alias.'__'.$this->table));

		// Join other tables :
		foreach($this->joins as $table => $columns) {
			$table_alias = $alias.'__'.$table;
			$query->join(array($table, $table_alias), 'INNER');
			foreach($columns as $column => $data) {
				list($table2, $column2) = $data;
				$query->on($table_alias.'.'.$column, '=', $alias.'__'.$table2.'.'.$column2);
			}
		}
	}

	public function join($query, $alias, $mappings) {
		// Group mappings by tables :
		$new = array();
		foreach($mappings as $field => $expr) {
			$table = $this->fields[$field]['table'];
			$new[$table][$field] = $expr;
		}
		$mappings = $new;

		// Join main table :
		$table_alias = $alias.'__'.$this->table;
		$query->join(array($this->table, $table_alias), 'INNER');
		foreach($mappings[$this->table] as $field => $expr) {
			$column = $this->fields[$field]['column'];
			$query->on($table_alias.'.'.$column, '=', $expr);
		}

		// Join other tables :
		foreach($this->joins as $table => $columns) {
			$table_alias = $alias.'__'.$table;
			$query->join(array($table, $table_alias), 'INNER');
			foreach($columns as $column => $data) {
				list($table2, $column2) = $data;
				$query->on($table_alias.'.'.$column, '=', $alias.'__'.$table2.'.'.$column2);
			}
			foreach($mappings[$table] as $field => $expr) {
				$column = $this->fields[$field]['column'];
				$query->on($table_alias.'.'.$column, '=', $expr);
			}
		}
	}

	public function field_expr($entity_alias, $field) {
		$table	= $this->fields[$field]['table'];
		$column	= $this->fields[$field]['column'];
		return $entity_alias . '__' . $table . '.' . $column;
	}

	public function relationship($name) {
		return OGL_Relationship::get($this->name, $name);
	}

	public function load_objects(&$rows, $alias = null) {
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

	private function adapt_joins() {
		// Build new joins array :
		$new = array();
		foreach($this->joins as $src => $trg) {
			// Add missing table prefixes :
			if (strpos('.', $src) === FALSE) $src = $this->table . '.' . $src;
			if (strpos('.', $trg) === FALSE) throw new Kohana_Exception("Format 'table.column' expected, '".$trg."' found instead.");

			// Add data to new joins array :
			list($trg_table, $trg_column) = explode('.', $trg);
			$new[$trg_table][$trg_column] = explode('.', $src);
		}
		$this->joins = $new;
	}

	// Lazy loads an entity object, stores it in cache, and returns it :
	static public function get($name) {
		if( ! isset(self::$entities[$name]))
			self::$entities[$name] = self::create($name);
		return self::$entities[$name];
	}

	// Chooses the right entity class to use, based on the name of the entity and
	// the available classes.
	static protected function create($name) {
		$class = 'OGL_Entity_'.ucfirst($name);
		if (class_exists($class))
			$entity = new $class($name);
		else
			$entity	= new self($name);
		return $entity;
	}

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


