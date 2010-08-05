<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Entity {
	// Entity cache :
	static protected $entities = array();

	// Identity map :
	protected $map = array();

	// Properties that may NOT be set in children classes (passed to constructor) :
	protected $name;

	// Properties that may be set by user in children classes :
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
	protected $partial;
	protected $sort;
	protected $pattern;
	protected $proxy_class_name;

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

	public function query_field_expr($entity_alias, $field) {
		reset($this->columns[$field]);
		list($table, $column) = each($this->columns[$field]);
		return $entity_alias . '__' . $table . '.' . $column;
	}

	public function query_field_alias($entity_alias, $field) {
		return $entity_alias . ':' . $field;
	}

	public function fields_validate($fields) {
		return array_diff($fields, $this->fields);
	}

	// Get pk string representations of each row
	// returned array is indexed the same way as $rows; no pk data ? no entry in returned array
	protected function pk_encode($rows, $fields) {
		// No rows ? Nothing to do :
		if (count($rows) === 0)	return array();

		// Build fields => columns mapping :
		$columns = array_flip($fields);

		// Array of columns holding pk information :
		foreach ($this->pk as $f) $pkcols[] = $columns[$f];

		// Loop on rows and build pk strings :
		foreach($rows as $key => $row) {
			// Gather pk data :
			$null = true;
			foreach($pkcols as $i => $col) {
				$arr[$i] = $row[$col];
				if ($row[$col] !== null)
					$null = false;
			}
			if ($null) continue;

			// Encode pk into string :
			$pks[$key] = json_encode($arr);
		}

		return $pks;
	}

	// Returns the array of objects represented as data in $rows. Objects already existing
	// in the identity map are reused and completed with new data. Otherwise new objects
	// are created and added to the identity map. Returned array is indexed like $rows.
	public function get_objects($rows, $fields) {
		// No rows ? Do nothing :
		if (count($rows) === 0) return array();

		// Get pk string representations of each row :
		$pks = $this->pk_encode($rows, $fields);

		// Build distinct pk => index mapping :
		$indexes = array_flip($pks);

		// Add new objects to identity map :
		$pattern = $this->get_pattern();
		foreach(array_diff_key($indexes, $this->map) as $pk => $index)
			$this->map[$pk] = clone $pattern;

		// Set values :
		foreach ($indexes as $pk => $index)
			$this->object_set($this->map[$pk], $rows[$index], $fields);

		// Create array of objects indexed in the same way rows are :
		$objects = array();
		foreach ($rows as $index => $row)
			$objects[$index] = $this->map[$pks[$index]];

		return $objects;
	}

	public function object_create($array) {
		$object = $this->object_new();
		$this->object_set($object, $array);
		return $object;
	}

	// Creates and returns pattern object (you may redefine this if you
	// need to set properties common to all model objects if this entity) :
	protected function create_pattern() {
		$proxy_class = $this->proxy_class_name();
		if ( ! class_exists($proxy_class))
			$this->proxy_class_create();
		return new $proxy_class;
	}

	// Lazy-loads pattern object :
	final protected function get_pattern() {
		if ( ! isset($this->pattern))
			$this->pattern = $this->create_pattern();
		return $this->pattern;
	}

	// Sets properties for an array of objects. The array of values
	// is expected to have the same keys as the array of objects. The columns
	// => fields mapping is $fields.
	public function object_set($objects, $values, $fields = null) {
		// Turn parameters into arrays if they are not already :
		if ( ! is_array($objects)) {
			$objects	= array($objects);
			$values		= array($values);
		}

		// No objects ? Do nothing :
		if (count($objects) === 0) return;

		// Columns => fields mapping not given ? Assume columns == fields.
		if ( ! isset($fields)) {
			$arr	= array_keys(reset($values));
			$fields	= array_combine($arr, $arr);
		}

		// Compute columns => properties and columns => types mappings :
		foreach($fields as $col => $field) {
			$properties[$col]	= $this->properties[$field];
			$types[$col]		= $this->types[$field];
		}

		// Set properties :
		call_user_func(array($this->proxy_class_name(), 'glue_set'), $objects, $values, $properties, $types);
	}

	// Gets properties for an array of objects. The returned array of values
	// will have the same keys as the array of objects. The
	// mapping is $columns.
	public function object_get($objects, $columns = null) {
		// Fields => columns mapping not given ? Assume mapping = fields => fields
		if ( ! isset($columns))
			$columns = array_combine($this->fields, $this->fields);

		// Compute fields => properties mapping :
		foreach($columns as $field => $col)
			$newcolumns[$this->properties[$field]] = $col;

		// Get properties :
		if (is_array($objects) || $objects instanceof Glue_Set)
			return call_user_func(array($this->proxy_class_name(), 'glue_get'), $objects, $newcolumns);
		else {
			$values = call_user_func(array($this->proxy_class_name(), 'glue_get'), array($objects), $newcolumns);
			return reset($values);
		}
	}

	// Gets dirty fields and their values for an array of objects. The returned
	// array of values will have the same keys as the array of objects.
	protected function object_get_dirty($objects) {
		return call_user_func(array($this->proxy_class_name(), 'glue_get_dirty'), $objects, $this->properties);
	}

	// Gets dirty fields and their values for an array of objects. The returned
	// array of values will have the same keys as the array of objects.
	protected function object_set_clean($objects, $fields) {
		// Turn fields into properties :
		$props = array();
		foreach ($fields as $f)
			$props[] = $this->properties[$f];

		// Copy state :
		return call_user_func(array($this->proxy_class_name(), 'glue_set_clean'), $objects, $props);
	}

	// Returns an associative array with pk field names and values.
	public function object_pk($objects) {
		return $this->object_get($objects, array_combine($this->pk, $this->pk));
	}

	// Deletes all the database representations of the given objects.
	public function object_delete($set) {
		// No objects ? Do nothing :
		if (count($set) === 0) return;

		// Get pk values :
		$pkvals = $this->object_pk($set);

		// Delete rows, table by table :
		foreach($this->tables as $table) {
			$query = DB::delete($table);
			if ( ! isset($this->pk[1])) {	// single pk
				$pkcol = $this->columns[$this->pk[0]][$table];
				$query->where($pkcol, 'IN', array_map('reset', $pkvals));
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

	// Insert into the database all objects of the given set.
	public function object_insert($set) {
		// Set is empty ? Do nothing :
		if (count($set) === 0) return;

		// Get objects as array :
		$objects = $set->as_array();

		// This entity has an autoincrementing pk ? Initialize it with null values :
		if ($this->autoincrement) {
			$arr = array_fill(0, count($objects), array($this->pk[0] => null));
			$this->object_set($objects, $arr);
		}

		// Get populated fields and their values :
		$populated = $this->object_get($objects);

		// Group objects that have same populated fields :
		$groups = array();
		foreach($populated as $key => $values) {
			$populated_fields = implode(',', array_keys($values));
			$groups[$populated_fields][$key] = $objects[$key];
		}

		// Loop on each group :
		foreach($groups as $populated_fields => $group) {
			// Get fields to insert :
			$fields = explode(',', $populated_fields);

			// Insert rows, table by table :
			foreach($this->tables as $table) {
				// Build fields => columns mapping :
				$columns = array();
				foreach($fields as $f) {
					$data = $this->columns[$f];
					if (isset($data[$table]))
						$columns[$f] = $data[$table];
				}

				// Build query :
				$query = DB::insert($table, $columns);

				// Add values :
				foreach($group as $key => $obj)
					$query->values(array_intersect_key($populated[$key], $columns));

				// Exec query :
				$result = $query->execute($this->db);

				// Set auto-increment values :
				if ($this->autoincrement && $table === $this->tables[0]) {
					$pk		= $this->pk[0];
					$i		= $result[0];
					$arr	= array();
					foreach($group as $key => $obj) {
						$populated[$key][$pk] = $i;
						$arr[$key][$pk] = $i;
						$i++;
					}
					$this->object_set($group, $arr);
				}
			}

			// Mark properties as synched with db :
			$this->object_set_clean($group, $fields);
		}
	}

	// Updates the database representations of all objects of the given set.
	function object_update($set) {
		// Set is empty ? Do nothing :
		if (count($set) === 0) return;

		// Get objects as array :
		$objects = $set->as_array();

		// Get pk values :
		$pks = $this->object_pk($objects);

		// Get dirty fields and their values :
		$dirty = $this->object_get_dirty($objects);

		// Group objects that have same dirty fields :
		$groups = array();
		foreach($dirty as $key => $values) {
			$dirty_fields = implode(',', array_keys($values));
			$groups[$dirty_fields][$key] = $objects[$key];
		}

		// Loop on each group :
		foreach($groups as $dirty_fields => $group) {
			// Get fields to update :
			$fields = explode(',', $dirty_fields);

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
				foreach($group as $key => $obj) {
					// Set pk values :
					foreach($pks[$key] as $f => $val)
						$query->param(':__'.$f, $val);

					// Set fields values :
					foreach($data as $f => $column)
						$query->param(':__'.$f, $dirty[$key][$f]);

					// Execute query :
					$query->execute($this->db);
				}
			}

			// Mark properties as synched with db :
			$this->object_set_clean($group, $fields);
		}
	}

	// Return relationship $name of this entity.
	public function relationship($name) {
		return glue::relationship($this->name, $name);
	}

	// Proxy class name :
	public function proxy_class_name() {
		if ( ! isset($this->proxy_class_name))
			$this->proxy_class_name = 'Glue_Proxy_' . strtr($this->name, '_', '0') . '_' . strtr($this->model, '_', '0');
		return $this->proxy_class_name;
	}

	// Create proxy class file on disk :
	protected function proxy_class_create() {
		// Get proxy class name :
		$class	= $this->proxy_class_name();

		// Compute directory and file :
		$arr	= explode('_', strtolower($class));
		$file	= array_pop($arr) . '.php';
		$dir	= MODPATH."glue/classes/".implode('/', $arr);

		// Create directory :
		if ( ! mkdir($dir, 777, true))
			throw new Kohana_Exception("Impossible to create directory " . $dir);

		// Create file :
		$path = $dir.'/'.$file;
		file_put_contents($path, View::factory('glue_proxy')
				->set('proxy_class',	$class)
				->set('model_class',	$this->model)
				->set('entity',			$this->name)
				->render()
		);
	}

	public function proxy_load_var($object, $var) {
		if ($field = array_search($var, $this->properties))
			$this->proxy_load_field($object, $field);
		else
			$this->proxy_load_relationship($object, $var);
	}

	protected function proxy_load_field($object, $field) {
		// Build query :
		$query = glue::select($this->name, $set);
		foreach($this->object_pk($object) as $f => $val)
			$query->where($f, '=', $val);
		$query->fields($field);

		// Execute query :
		$query->execute();
	}

	protected function proxy_load_relationship($object, $relationship) {
		// Build query :
		$query = glue::select($this->name, $set);
		foreach($this->object_pk($object) as $f => $val) {
			$query->where($f, '=', $val);
			$query->fields($f);
		}
		$query->with($set, $relationship);

		// Execute query :
		$query->execute();
	}

	public function clear() {
		$this->map = array();
	}

	// Getters :
	public function name()			{ return $this->name;			}
	public function tables()		{ return $this->tables;			}
	public function fields()		{ return $this->fields;			}
	public function properties()	{ return $this->properties;		}
	public function columns()		{ return $this->columns;		}
	public function types()			{ return $this->types;			}
	public function pk()			{ return $this->pk;				}
	public function fk()			{ return $this->fk;				}
	public function db()			{ return $this->db;				}
	public function model()			{ return $this->model;			}
	public function autoincrement()	{ return $this->autoincrement;	}

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

	// Lazy loads an entity mapper, stores it in cache, and returns it :
	static public function get($name) {
		$name = strtolower($name);
		if( ! isset(self::$entities[$name]))
			self::$entities[$name] = self::build($name);
		return self::$entities[$name];
	}

	// Returns all entity mappers instanciated so far :
	static public function get_all() {
		return self::$entities;
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


