<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Entity {
	// Entity cache :
	static protected $entities = array();
	
	// Identity map :
	protected $map = array();

	// Properties that may NOT be set in children classes (passed to constructor) :
	protected $name;

	// Properties that may be set in children classes :
	protected $db;
	protected $model;
	protected $tables;
	protected $fields;
	protected $properties;
	protected $columns;
	protected $types;
	protected $autoincrement;
	protected $pk;
	protected $fk;

	// Internal details :
	private $partial;
	private $sort;
	private $pattern;

	protected function __construct($name) {
		// Set properties :
		$this->name	= $name;

		// Init properties (order matters !!!) :
		if ( ! isset($this->db))			$this->db			 = $this->default_db();
		if ( ! isset($this->model))			$this->model		 = $this->default_model();
		if ( ! isset($this->tables))		$this->tables		 = $this->default_tables();
		if ( ! isset($this->fields))		$this->fields		 = $this->default_fields();
		if ( ! isset($this->properties))	$this->properties	 = $this->default_properties();
		if ( ! isset($this->columns))		$this->columns		 = $this->default_columns();
		if ( ! isset($this->types))			$this->types		 = $this->default_types();
		if ( ! isset($this->pk))			$this->pk			 = $this->default_pk();
		if ( ! isset($this->fk))			$this->fk			 = $this->default_fk();
		if ( ! isset($this->autoincrement))	$this->autoincrement = $this->default_autoincrement();
	}

	protected function default_db() {
		return 'default';
	}

	protected function default_model() {
		$model = 'Glue_Model_'.ucfirst($this->name);
		return class_exists($model) ? $model : 'stdClass'; // no upper case !
	}

	protected function default_tables() {
		return array(inflector::plural($this->name));
	}

	protected function default_fields() {
		// Init fields array :
		$fields  = array();

		// Loop on tables, look up columns by database introspection and populate fields array :
		$data = $this->introspect();
		foreach($this->tables as $table) {
			foreach($data[$table] as $col => $coldata) {
				if ( ! in_array($col, $fields))
					$fields[] = $col;
			}
		}

		return $fields;
	}
	
	protected function default_properties() {
		return array_combine($this->fields, $this->fields);
	}

	// par défault, associe chaque field à la colonne du même nom. Si il y plusieurs tables, ça demande un accès db, sinon pas.
	protected function default_columns() {
		$columns = array();

		if (count($this->tables) === 1) {
			foreach($this->fields as $f)
				$columns[$f][$this->tables[0]] = $f;
		}
		else {
			$data = $this->introspect();
			foreach($this->fields as $f) {
				foreach($this->tables as $table) {
					if (isset($data[$table][$f]))
						$columns[$f][$table] = $f;
				}
				if ( ! isset($columns[$f]))
					throw new Kohana_Exception("Impossible to guess onto which table and column the following field must be mapped : '".$f."' (entity '".$this->name."'");
			}
		}

		return $columns;
	}

	protected function default_types() {
		$types = array();

		$data = $this->introspect();
		foreach($this->columns as $f => $arr) {
			foreach ($arr as $table => $column) break;
			$types[$f] = $data[$table][$column]['type'];
		}

		return $types;
	}

	protected function default_pk() {
		$pk = array();

		$data = $this->introspect();
		foreach($this->columns as $f => $arr) {
			foreach ($arr as $table => $column) {
				if (isset($data[$table][$column]['key']) && $data[$table][$column]['key'] === 'PRI') {
					$pk[] = $f;
					break;
				}
			}
		}

		return $pk;
	}

	protected function default_fk() {
		$fk = array();
		foreach($this->pk as $f) $fk[$f] = $this->name.'_'.$f;
		return $fk;
	}

	protected function default_autoincrement() {
		if (isset($this->pk[1]))
			$auto = false;
		else {
			$pk = $this->pk[0];
			if ($this->types[$pk] !== 'int')
				$auto =  false;
			else {
				$table	= $this->tables[0];
				$column	= $this->columns[$this->pk[0]][$table];
				$data	= $this->introspect();
				if (isset($data[$table][$column]['extra']) && strpos($data[$table][$column]['extra'], 'auto_increment') !== false)
					$auto =  true;
				else
					$auto =  false;
			}
		}

		return $auto;
	}

	protected function introspect() {
		if ( ! isset($this->introspect)) {
			foreach($this->tables as $table)
				$this->introspect[$table] = glue::show_columns($table, $this->db);
		}
		return $this->introspect;
	}

	public function query_from($query, $alias) {
		$partial = $this->query_partial($alias);
		$query->from(DB::expr($partial));
	}

	public function query_join($query, $alias, $type = 'LEFT') {
		$partial = $this->query_partial($alias);
		$query->join(DB::expr($partial), $type);
	}

	public function query_on($query, $alias, $field, $op, $expr) {
		$col = $this->query_field_expr($alias, $field);
		$query->on($col, $op, $expr);
	}

	public function query_where($query, $alias, $field, $op, $expr) {
		$col = $this->query_field_expr($alias, $field);
		$query->where($col, $op, $expr);
	}

	public function query_order_by($query, $alias, $field, $order) {
		$col = $this->query_field_expr($alias, $field);
		$query->order_by($col, $order);
	}

	protected function query_partial($alias) {
		// Init partial cache :
		if ( ! isset($this->partial)) {
			// Init fake select query (we need this hack because query builder doesn't support nested joins) :
			$fake = DB::select();

			// Add tables :
			$count = count($this->tables);
			for ($i = 0; $i < $count; $i++) {
				// Join :
				$table1			= $this->tables[$i];
				$table1_alias	= '%ALIAS%'.'__'.$table1;
				if ($i === 0)
					$fake->from(array($table1, $table1_alias));
				else
					$fake->join(array($table1, $table1_alias), 'INNER');

				// Add internal join conditions :
				for($j = $i - 1; $j >= 0; $j--) {
					$table2			= $this->tables[$j];
					$table2_alias	= '%ALIAS%'.'__'.$table2;
					foreach($this->pk as $pkf) {
						$col1 = $this->columns[$pkf][$table1];
						$col2 = $this->columns[$pkf][$table2];
						$fake->on($table1_alias.'.'.$col1, '=', $table2_alias.'.'.$col2);
					}
				}
			}

			// Capture from clause :
			$sql			= $fake->compile(Database::instance($this->db));
			list(, $sql)	= explode('FROM ', $sql);
			$this->partial	= ($count > 1) ? '('.$sql.')' : $sql;
		}

		return str_replace('%ALIAS%', $alias, $this->partial);
	}

	public function query_select($query, $alias, $fields) {
		foreach ($fields as $name)
			$query->select(array($this->query_field_expr($alias, $name), $alias.':'.$name)); // TODO move aliasing logic to calling function
	}

	public function query_field_expr($alias, $field) {
		foreach($this->columns[$field] as $table => $column)
			return $alias . '__' . $table . '.' . $column;
	}
	
	public function fields_validate($fields) {
		return array_diff($fields, $this->fields);
	}

	public function fields_opposite($fields) {
		return array_diff($this->fields, $fields);
	}

	public function fields_all() {
		return $this->fields;
	}

	public function object_load(&$rows, $prefix = '') {
		// No rows ? Do nothing :
		if (count($rows) === 0) return array();

		// Build columns => fields mapping :
		$mapping = array();
		$len_prefix	= strlen($prefix);
		foreach($rows[0] as $col => $val) {
			if (substr($col, 0, $len_prefix) === $prefix) {
				$field = substr($col, $len_prefix);
				$mapping[$col] = $field;
			}
		}

		// Get pk string representations of each row :
		$arr	= array();
		$pks	= array();
		$pkcols	= array();
		foreach($this->pk as $f) $pkcols[] = $prefix.$f;
		foreach($rows as $row) {
			$no_obj = true;
			foreach($pkcols as $i => $col) {
				$arr[$i] = $row[$col];
				if ($row[$col] !== null)
					$no_obj = false;
			}
			$pks[] = $no_obj ? 0 : json_encode($arr);
		}

		// Add new objects to id map :
		$indexes = array_flip($pks); // Distinct pk => row index mapping
		unset($indexes[0]);					// Remove key that represents "no object".
		$diff = array_diff_key($indexes, $this->map);
		foreach($diff as $pk => $index) {
			$vals	= array_intersect_key($rows[$index], $mapping);
			$array	= array_combine($mapping, $vals);
			$this->map[$pk] = $this->create($array);
		}

		// Build distinct objects list :
		$distinct = array_intersect_key($this->map, $indexes);

		// Load objects into result set :
		$key = $prefix.'__object';
		foreach($pks as $index => $pk) {
			$rows[$index][$key] = ($pk === 0) ? null : $this->map[$pk];
		}

		// Return distinct objects :
		return array_values($distinct);
	}

	public function create($array) {
		// Create object :
		$object = clone $this->get_pattern();

		// Set object properties :
		foreach($array as $field => $val) {
			settype($val, $this->types[$field]);
			$object->glue_set($field, $val);
		}

		return $object;
	}
	
	protected function get_pattern() {
		if ( ! isset($this->pattern))
			$this->pattern = $this->create_pattern();
		return $this->pattern;
	}
	
	protected function create_pattern() {
		$class = $this->proxy_class_name();
		$pattern = new $class;
		$this->proxy_unset($pattern);
		return $pattern;
	}

	// Returns an associative array with pk field names and values.
	public function object_pk($objects) {
		if (is_array($objects)) {
			foreach($this->pk as $f)
				$vals[$f] = array_map(array());
		}
		else {

		}
		//return $object->glue_pk();
	}

	// Sorts an array of objects according to sort criteria.
	public function sort(&$objects, $clause) {
		// Parse sort clause and set current sort :
		$this->sort = array();
		$clause = preg_replace('/\s+/', ' ', $clause);
		$clause = explode(',', $clause);
		foreach($clause as $c) {
			$parts	= explode(' ', trim($c));
			$sort	= $this->properties[$parts[0]];
			$order	= ((! isset($parts[1])) || strtolower(substr($parts[1], 0, 1)) === 'a') ? +1 : -1;
			$this->sort[$sort] = $order;
		}

		// Sort objects :
		usort($objects, array($this, 'object_multi_cmp'));
	}

	// Compares two objects according to current sort criteria.
	private function object_multi_cmp($a, $b) {
        foreach($this->sort as $sort => $order) {
			$cmp = $this->object_cmp($sort, $a, $b) * $order;
            if ($cmp !== 0) return $cmp;
        }
        return 0;
    }

	// Compares two objects according to given sort criteria.
	protected function object_cmp($sort, $a, $b) {
		if ($a->$sort < $b->$sort) return -1;
		if ($a->$sort > $b->$sort) return +1;
		return 0;
    }

	public function object_delete($objects) {
		// No objects ? Do nothing :
		if (count($objects) === 0) return;

		// Get pk values :
		$pkvals = array_map(array($this, 'object_pk'), $objects);

		// Delete rows, table by table :
		foreach($this->tables as $table) {
			$query = DB::delete($table);
			if ( ! isset($this->pk[1])) {	// single pk
				$pkcol = $this->columns[$this->pk[0]][$table];
				$query->where($pkcol, 'IN', array_map('array_pop', $pkvals));
				$query->execute($this->db);
			}
			else {							// multiple pk
				// Build query :
				foreach($this->pk as $f)
					$query->where($this->columns[$f][$table], '=', DB::expr(':__'.$f));
				$query = DB::query(Database::DELETE, $query->compile(Database::instance($this->db))); // Needed because query builder doesn't support parameters

				// Exec queries :
				foreach($pkvals as $pkval) {
					foreach($pkval as $f => $val)
						$query->param( ':__'.$f, $val);
					$query->execute($this->db);
				}
			}
		} 
	}

	public function delete($arg = null) {
		// No param given :
		if ( ! isset($arg)) {
			$this->select()->delete();
			return;
		}

		// Object given :
		if (is_object($arg)) {
			if ($arg instanceof Glue_Set)
				$arg->delete();
			else
				$this->object_delete(array($arg));
			return;
		}

		// Array given :
		if (is_array($arg)) {
			if (count($arg) !== 0) {
				if (isset($arg[0]) && is_object($arg[0]))
					$this->object_delete($arg);
				else
					$this->select($arg)->delete();
			}
			return;
		}
		
		// Scalar given :
		$this->object_delete(array($this->select($arg)));
	}

	public function insert($objects) {
		// Glue_Set given ? Get array of objects :
		if ($objects instanceof Glue_Set) $objects = $objects->as_array();
	
		// Object given ? Wrap it in an array :
		if ( ! is_array($objects)) $objects = array($objects);

		// No objects ? Do nothing :
		if (count($objects) === 0) return;

		// Insert rows, table by table :
		foreach($this->tables as $table) {
			// Build columns - properties array :
			$cp = array();
			foreach($this->columns as $f => $data) {
				if ($this->autoincrement && $f === $this->pk[0] && $table === $this->tables[0])
					continue;
				if (isset($data[$table]))
					$cp[$data[$table]] = $this->properties[$f];
			}

			// Build query :
			$query = DB::insert($table, array_keys($cp));

			// Add values :
			foreach($objects as $obj) {
				$values = array();
				foreach($cp as $column => $property)
					$values[] = $obj->$property;
				$query->values($values);
			}			

			// Exec query :
			$result = $query->execute($this->db);

			// Set auto-increment values :
			if ($this->autoincrement && $table === $this->tables[0]) {
				$i = $result[0];
				$p = $this->properties[$this->pk[0]];
				foreach($objects as $obj) {
					$obj->$p = $i;
					$i++;
				}
			}
		}
	}

	function update($objects, $fields = null) {
		// Glue_Set given ? Get array of objects :
		if ($objects instanceof Glue_Set) $objects = $objects->as_array();

		// Single object given ? Wrap it in an array :
		if ( ! is_array($objects)) $objects = array($objects);

		// No objects ? Do nothing :
		if (count($objects) === 0) return;

		// No fields given ? Default = all fields :
		if ( ! isset($fields))
			$fields = $this->fields_all();

		// Single field given ? Wrap it in an array :
		if ( ! is_array($fields)) $fields = array($fields);

		// Remove pk :
		$fields = array_diff($fields, $this->pk);

		// Validate fields :
		$this->fields_validate($fields);

		// Build list of tables to be updated :
		$tables = array();
		foreach($fields as $f)
			foreach ($this->columns[$f] as $table => $column)
				$tables[$table][$f] = $column;

		// Update tables :
		foreach($tables as $table => $data) {
			// Build query :
			$query = DB::update($table);
			foreach($this->pk as $f)
				$query->where($this->columns[$f][$table], '=', DB::expr(':__'.$f));
			foreach($data as $f => $column)
				$query->value($column, DB::expr(':__'.$f));
			 $query = DB::query(Database::UPDATE, $query->compile(Database::instance($this->db)));

			// Loop on objects and update table :
			foreach($objects as $obj) {
				// Set pk values :
				foreach($obj->glue_pk() as $f => $val)
					$query->param(':__'.$f, $val);

				// Set fields values :
				foreach($fields as $f)
					$query->param(':__'.$f, $obj->{$this->properties[$f]});

				// Execute query :
				$query->execute($this->db);
			}
		}
	}

	public function select($conditions = array(), $order_by = null, $limit = null, $offset = null) {
		// Init Glue query :
		$q = glue::qselect($this->name, $result);

		// Must return a set or a single object ?
		$return_set = is_array($conditions);

		// Single column Pk value passed ? Warp it in an array :
		if ( ! is_array($conditions)) {
			if (count($this->pk) > 1)
				throw new Kohana_Exception("Only one value passed for multiple columns pk !");
			$conditions = array($this->pk[0] => $conditions);
		}

		// Add conditions :
		foreach ($conditions as $field => $value) {
			if (is_array($value))
				$q->where($field, 'IN', $value);
			else
				$q->where($field, '=', $value);
		}

		// Add sort, limit, offset :
		if (isset($order_by))   $q->order_by($order_by);
		if (isset($limit))		$q->limit($limit);
		if (isset($offset))		$q->offset($offset);

		// Execute query :
		$q->execute();

		// Return object, or set of objects :
		if ($return_set)
			return $result;
		else
			return isset($result[0]) ? $result[0] : null;
			
	}

	// Return relationship $name of this entity.
	public function relationship($name) {
		return glue::relationship($this->name, $name);
	}

	// Proxy class name :
	protected function proxy_class_name() {
		return 'Glue_Proxy_' . ucfirst($this->name);
	}

	// Load proxy class :
	public function proxy_load_class() {
		eval(
			View::factory('glue_proxy')
				->set('proxy_class',	$this->proxy_class_name())
				->set('model_class',	$this->model)
				->set('entity',			$this->name)
				->set('properties',		$this->properties)
				->set('lazy_props',		$this->lazy_props)
				->set('types',			$this->lazy_props)
		);
//		echo View::factory($this->proxy_view)
//				->set('proxy_class',	$this->proxy_class_name())
//				->set('model_class',	$this->model)
//				->set('entity_name',	$this->name)
//				->set('properties',		$this->properties);
//		die;
	}

	public function load_field($object, $field) {
		// Build query :
		$query = glue::qselect($this->name, $set);
		foreach($this->object_pk($object) as $f => $val)
			$query->where($f, '=', $val);
		$query->fields($field);

		// Execute query :
		$query->execute();
	}

	public function load_relationship($object, $relationship) {
		// Build query :
		$query = glue::qselect($this->name, $set);
		foreach($this->object_pk($object) as $f => $val)
			$query->where($f, '=', $val);
		$query->with($set, $relationship);

		// Execute query :
		$query->execute();
	}

	public function proxy_unset($objects) {
		return call_user_func(array($this->proxy_class_name(), 'glue_unset'), $objects);
	}

	// Getters :
	public function name()	{ return $this->name;	}
	public function pk()	{ return $this->pk;		}
	public function fk()	{ return $this->fk;		}
	public function db()	{ return $this->db;		}

	// Debug :
	public function debug() {
		return View::factory('glue_entity')
			->set('name',			$this->name)
			->set('fields',			$this->fields)
			->set('columns',		$this->columns)
			->set('properties',		$this->properties)
			->set('types',			$this->types)
			->set('db',				$this->db)
			->set('pk',				$this->pk)
			->set('fk',				$this->fk)
			->set('autoincrement',	$this->autoincrement)
			->set('model',			$this->model);
	}

	// Lazy loads an entity object, stores it in cache, and returns it :
	static public function get($name) {
		$name = strtolower($name);
		if( ! isset(self::$entities[$name]))
			self::$entities[$name] = self::build($name);
		return self::$entities[$name];
	}

	// Chooses the right entity class to use, based on the name of the entity and
	// the available classes.
	static protected function build($name) {
		$class = 'Glue_Entity_'.ucfirst($name);
		if (class_exists($class))
			$entity = new $class($name);
		else
			$entity	= new self($name);
		return $entity;
	}
}


