<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Entity {
	// Entity cache :
	static protected $entities = array();

	// Properties that may NOT be set in children classes (either passed to constructor or deduced from other properties) :
	protected $name;
	protected $pk;
	protected $fk;
	protected $joins;

	// Properties that may be set in children classes :
	protected $model;
	protected $tables;
	protected $fields;

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
		$this->multipk = (count($this->pk ) > 1);

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
		return class_exists($model) ? $model : 'stdClass'; // no upper case !
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
		// Build conditions array :
		$conds = array();
		if (count($this->pk) === 1)
			$conds[] = array($this->pk[0], 'IN', new Database_Expression(':_pks'));
		else
			foreach($this->pk as $f)
				$conds[] = array($f, '=', new Database_Expression(':_'.$f));
		
		// Add entity to from/where :
		$this->query_from($query, $alias, $conds);
		
		// Add pk to fields :
		$this->query_fields($query, $alias, $this->pk);
	}

	public function query_from($query, $alias, $conds = array(), $type = 'LEFT') {
		return $this->query_add($query, $alias, $conds, 'from', $type);
	}

	public function query_join($query, $alias, $conds = array(), $type = 'LEFT') {
		return $this->query_add($query, $alias, $conds, 'join', $type);
	}

	protected function query_add($query, $alias, $conds, $type_add, $type_join) {
		// Group $conds array by tables and columns :
		$new = array();
		foreach($conds as $cond) {
			list($field, $op, $expr) = $cond;
			list($table, $column) = explode('.', $this->fields[$field]['columns'][0]);
			$new[$table][$column] = array($op, $expr);
		}
		$conds = $new;

		// Reorder tables so that the ones that appear in $conds come first :
		$tables = array_keys($conds);
		$tables = array_merge($tables, array_diff($this->tables, $tables));

		// Join tables :
		$count = count($tables);
		for ($i = 0; $i < $count; $i++) {
			// Join :
			$table1			= $tables[$i];
			$table1_alias	= $alias.'__'.$table1;
			if ($i === 0 && $type_add === 'from')
				$query->from(array($table1, $table1_alias));
			else
				$query->join(array($table1, $table1_alias), $type_join);

			// Add external join conditions :
			if (isset($conds[$table1])) {
				foreach($conds[$table1] as $col => $cond) {
					list($op, $expr) = $cond;
					if ($i === 0 && $type_add === 'from')
						$query->where($table1_alias.'.'.$col, $op, $expr);
					else
						$query->on($table1_alias.'.'.$col, $op, $expr);
				}
			}

			// Add internal join conditions :
			for($j = $i - 1; $j >= 0; $j--) {
				$table2			= $tables[$j];
				$table2_alias	= $alias.'__'.$table2;
				if (isset($this->joins[$table1][$table2])) {
					foreach($this->joins[$table1][$table2] as $col1 => $col2)
						$query->on($table1_alias.'.'.$col1, '=', $table2_alias.'.'.$col2);
				}
			}
		}
	}

	public function query_exec($query, $objects) {
		// No objects ? No result :
		if (count($objects) === 0)
			return array();

		// Get pk values :
		$pkvals = array_map(array($this, 'object_pk'), $objects);

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
			$query->select(array($this->query_field_expr($alias, $name), $alias.':'.$name));
	}

	public function query_field_expr($alias, $field) {
		list($table, $column) = explode('.', $this->fields[$field]['columns'][0]);
		return $alias . '__' . $table . '.' . $column;
	}

	public function object_load(&$rows, $prefix = '') {
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

		// Create pattern object :
		if ( ! isset($pattern)) {
			$class = $this->model;
			$pattern = new $class;
		}

		// Create object :
		$object = clone $pattern;

		// Set object properties :
		foreach($array as $field => $val) {
			$data = $this->fields[$field];
			$prop = $data['property'];
			$type = $data['phptype'];
			if ($type !== 'string')
				settype($val, $type);
			$object->$prop = $val;
		}

		return $object;
	}

	// For single column pk, returns the pk value.
	// For multiple columns pk, returns an associative array with pk field names and values.
	protected function object_pk($object) {
		// Efficiently deal with single column pk :
		static $prop;
		if (isset($prop)) return $object->$prop;
		if (count($this->pk) === 1) {
			$prop = $this->fields[$this->pk[0]]['property'];
			return $object->$prop;
		}

		// Multiple column pk :
		foreach($this->pk as $f)
			$pk[$f] = $object->{$this->fields[$f]['property']};
		return $pk;
	}

	// Return relationship $name of this entity.
	public function relationship($name) {
		return OGL_Relationship::get($this->name, $name);
	}

	public function name() {
		return $this->name;
	}

	public function pk() {
		return $this->pk;
	}

	public function fk() {
		return $this->fk;
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


