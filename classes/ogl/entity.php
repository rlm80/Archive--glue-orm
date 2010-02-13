<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Entity {
	// Entity cache :
	static protected $entities = array();

	// Properties that may NOT be set in children classes (either passed to constructor or deduced from other properties) :
	public $name;
	public $pk;
	public $fk;
	public $joins;
	
	// Properties that may be set in children classes :
	public $model;
	public $tables;
	public $fields;

	// Identity map :
	protected $map = array();

	protected function __construct($name) {
		// Set properties :
		$this->name	= $name;

		// Init properties (order matters !!!) :
		if ( ! isset($this->model))		$this->model	= $this->default_model();
		if ( ! isset($this->tables))	$this->tables	= $this->default_tables();
		if ( ! isset($this->fields))	$this->fields	= $this->default_fields();

		// Check fields array format :
		foreach($this->fields as $name => $data) {
			// Check columns property :
			if ( ! isset($data['columns']) || ! is_array($data['columns']))
				throw new Kohana_Exception("No 'columns' property found for field '".$this->name.".".$name."' or property not an array.");
			foreach ($data['columns'] as $col)
				if (strpos($col, '.') === false)
					throw new Kohana_Exception("Format 'table.column' expected, '".$col."' found instead.");
		}

		// Extract pk and fk from fields array for efficiency purposes :
		$this->pk = array();
		$this->fk = array();
		foreach($this->fields as $name => $data) {
			if (isset($data['pk'])) {
				$this->pk[]			= $name;
				$this->fk[$name]	= $this->name.'_'.$name;
			}
		}

		// Build joins array :
		$this->joins = array();
		if (count($this->tables) > 1) {
			foreach($this->fields as $name => $data) {
				$columns	= $data['columns'];
				$count		= count($columns);
				if ($count > 1) {
					for ($i = 0; $i < $count; $i++) {
						list($table_i, $col_i) = explode('.', $columns[$i]);
						for ($j = 0; $j < $count; $j++) {
							if ($j !== $i) {
								list($table_j, $col_j) = explode('.', $columns[$j]);
								$this->joins[$table_i][$table_j][$col_i] = $col_j;
							}
						}
					}
				}
			}
		}
	}

	protected function default_model() {
		$model = 'OGL_Model_'.ucfirst($this->name);
		return class_exists($model) ? $model : 'StdClass';
	}

	protected function default_tables() {
		return array(inflector::plural($this->name));
	}

	protected function default_fields() {
		// Init fields array :
		$fields  = array();

		// Loop on tables, look up columns properties by database introspection and populate fields array :
		foreach($this->tables as $table) {
			$cols = Database::instance()->list_columns($table);
			foreach($cols as $col => $data) {
				if ( ! isset($fields[$col])) {
					// Field doesn't exist yet ? Create it :
					$fields[$col] = array(
						'columns'	=> array($table.'.'.$col),
						'phptype'	=> $data['type'],
						'property'	=> $col
					);
					if (isset($data['key']) && $data['key'] === 'PRI')
						$fields[$col]['pk'] = $this->name.'_'.$col;
				}
				else
					// Otherwise add current column to its columns list :
					$fields[$col]['columns'][] = $table.'.'.$col;
			}
		}

		return $fields;
	}

	public function query_init($query, $alias) {
		// Add entity tables to from :
		$this->query_from($query, $alias);

		// Add pk to select :
		$this->add_fields($query, $this->pk, $alias);

		// Add conditions on pk to where :
		if (count($this->pk) === 1)
			$query->where($this->field_expr($alias, $this->pk[0]), 'IN', new Database_Expression(':_pks'));
		else
			foreach($this->pk as $f)
				$query->where($this->field_expr($alias, $f), '=', new Database_Expression(':_'.$f));
	}

	public function query_from($query, $alias, $type = 'INNER') {
		// First table :
		$query->from(array($this->tables[0], $alias.'__'.$this->tables[0]));

		// Join other tables :
		$count = count($this->tables);
		if ($count > 1) {
			for ($i = 1; $i < $count; $i++) {
				$table1 = $this->tables[$i];
				$table1_alias = $alias.'__'.$table1;
				$query->join(array($table1, $table1_alias), $type);
				for($j = $i - 1; $j >= 0; $j--) {
					$table2 = $this->tables[$j];
					$table2_alias = $alias.'__'.$table2;
					if (isset($this->joins[$table1][$table2])) {
						foreach($this->joins[$table1][$table2] as $col1 => $col2)
							$query->on($table1_alias.'.'.$col1, '=', $table2_alias.'.'.$col2);
					}
				}
			}
		}
	}

	public function query_join($query, $alias, $mappings, $type = 'INNER') {
		// Group mappings by tables :
		$new = array();
		foreach($mappings as $field => $expr) {
			$table = $this->fields[$field]['table'];
			$new[$table][$field] = $expr;
		}
		$mappings = $new;

		// Join main table :
		$table_alias = $alias.'__'.$this->table;
		$query->join(array($this->table, $table_alias), $type);
		foreach($mappings[$this->table] as $field => $expr) {
			$column = $this->fields[$field]['column'];
			$query->on($table_alias.'.'.$column, '=', $expr);
		}

		// Join other tables :
		foreach($this->joins as $table => $columns) {
			$table_alias = $alias.'__'.$table;
			$query->join(array($table, $table_alias), $type);
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

	public function query_exec($query, $objects) {
		// No objects ? No result :
		if (count($objects) === 0)
			return array();

		// Get array of pk values :
		$pkvals = array_map('array_pop', array_map(array($this, 'get_pk'), $objects));

		// Exec query :
		$result = array();
		if (count($this->pk) === 1) {
			// Use only one query :
			$result = $query->param(':_pks', $pkvals)->execute()->as_array();
		}
		else {
			// Use one query for each object and aggregate results :
			foreach($pkvals as $pkval) {
				foreach($pkval as $f => $val)
					$query->param( ':_'.$f, $val);
				$rows = $query->execute()->as_array();
				if (count($rows) >= 1)
					array_merge($result, $rows);
			}
		}
		return $result;
	}

	public function field_expr($alias, $field) {
		$table	= $this->fields[$field]['table'];
		$column	= $this->fields[$field]['column'];
		return $alias . '__' . $table . '.' . $column;
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
			$query->select(array($this->field_expr($alias, $name), $alias.':'.$name));
	}
}


