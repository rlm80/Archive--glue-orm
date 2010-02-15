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
		$this->query_from($query, $alias);
		$this->query_fields($query, $alias, $this->pk);
		if (count($this->pk) === 1)
			$query->where($this->field_expr($alias, $this->pk[0]), 'IN', new Database_Expression(':_pks'));
		else {
			foreach($this->pk as $f)
				$query->where($this->field_expr($alias, $f), '=', new Database_Expression(':_'.$f));
		}
	}

	public function query_from($query, $alias, $type = 'LEFT') {
		// First table :
		$table			= $this->tables[0];
		$table_alias	= $alias.'__'.$table;
		$query->from(array($table, $table_alias));

		// Join other tables :
		$count = count($this->tables);
		if ($count > 1) {
			for ($i = 1; $i < $count; $i++) {
				$table1			= $this->tables[$i];
				$table1_alias	= $alias.'__'.$table1;
				$query->join(array($table1, $table1_alias), $type);
				for($j = $i - 1; $j >= 0; $j--) {
					$table2			= $this->tables[$j];
					$table2_alias	= $alias.'__'.$table2;
					if (isset($this->joins[$table1][$table2])) {
						foreach($this->joins[$table1][$table2] as $col1 => $col2)
							$query->on($table1_alias.'.'.$col1, '=', $table2_alias.'.'.$col2);
					}
				}
			}
		}
	}

	public function query_join($query, $alias, $ons, $type = 'LEFT') {
		// Group $ons array by tables and columns :
		$new = array();
		foreach($ons as $field => $expr) {
			list($table, $column) = explode('.', $this->fields[$field]['columns'][0]);
			$new[$table][$column] = $expr;
		}
		$ons = $new;

		// Reorder tables so that the ones that appear in "$ons" come first :
		$tables = array_keys($ons);
		foreach($this->tables as $t)
			if ( ! in_array($t, $tables))
				$tables[] = $t;

		// First table :
		$table			= $tables[0];
		$table_alias	= $alias.'__'.$table;
		$query->join(array($table, $table_alias), $type);
		if (isset($ons[$table]))
			foreach($ons[$table] as $col => $expr)
				$query->on($table_alias.'.'.$col, '=', $expr);

		// Join other tables :
		$count = count($tables);
		if ($count > 1) {
			for ($i = 1; $i < $count; $i++) {
				$table1			= $tables[$i];
				$table1_alias	= $alias.'__'.$table1;
				$query->join(array($table1, $table1_alias), $type);
				for($j = $i - 1; $j >= 0; $j--) {
					$table2			= $tables[$j];
					$table2_alias	= $alias.'__'.$table2;
					if (isset($this->joins[$table1][$table2])) {
						foreach($this->joins[$table1][$table2] as $col1 => $col2)
							$query->on($table1_alias.'.'.$col1, '=', $table2_alias.'.'.$col2);
					}
				}
				if (isset($ons[$table1]))
					foreach($ons[$table1] as $col => $expr)
						$query->on($table1_alias.'.'.$col, '=', $expr);
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

	public function query_fields($query, $alias, $req_fields) {
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

	public function field_expr($alias, $field) {
		list($table, $column) = explode('.', $this->fields[$field]['columns'][0]);
		return $alias . '__' . $table . '.' . $column;
	}

	public function load_objects(&$rows, $prefix = '') {
		// No rows ? Do nothing :
		if (count($rows) === 0) return;

		// Build columns => fields mapping :
		$mapping = array();
		$len_prefix	= strlen($prefix);
		foreach($rows[0] as $col => $val) {
			if (substr($col, 0, $len_prefix) === $prefix) {
				$field = substr($col, $len_prefix);
				if ( ! isset($this->fields[$field]))
					throw new Kohana_Exception("The following field doesn't belong to entity '".$this->name."' : '".$field."'");
				$mapping[$col] = $field;
			}
		}

		// Get pk string representations of each row :
		$arr	= array();
		$pks	= array();
		$pkcols	= array();
		foreach($this->pk as $f) $pkcols[] = $prefix.$f;
		foreach($rows as $row) {
			foreach($pkcols as $i => $col) $arr[$i] = $row[$col];
			$pks[] = json_encode($arr);
		}

		// Add new objects to id map :
		$indexes		= array_flip($pks); // distinct pk => row index mapping
		$field_names	= array_keys($this->fields);
		$diff			= array_diff_key($indexes, $this->map);
		foreach($diff as $pk => $index) {
			$vals	= array_intersect_key($rows[$index], $mapping);
			$array	= array_combine($mapping, $vals);
			$this->map[$pk] = $this->object_create($array);
		}

		// Build distinct objects list :
		$distinct = array_intersect_key($this->map, $indexes);

		// Load objects into result set :
		$key = $prefix.'__object';
		foreach($pks as $index => $pk)
			$rows[$index][$key] = $this->map[$pk];

		// Return distinct objects :
		return $distinct;
	}

	protected function object_create($array) {
		static $pattern;

		// Create pattern object if one doesn't exist yet :
		if ( ! isset($pattern)) {
			$class = $this->model;
			$pattern = new $class;
		}

		// Clone pattern object :
		$object = clone $pattern;

		// Set object properties :
		foreach($array as $field => $val) {
			$data = $this->fields[$field];
			settype($val, $data['phptype']);
			$object->{$data['property']} = $val;
		}

		return $object;
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
}


