<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Entity mapper class.
 * 
 * A mapper is an object that knows about the way an entity is stored in the database
 * (which tables and columns) and about the way it is represented in the application
 * (which model class and properties). It centralizes all the code that needs
 * this knowledge. Especially :
 * - updating, deleting and inserting objects into the database,
 * - creating objects with data coming from the database.
 * 
 * An identity map is used to ensure that there can never be any duplicate objects, that is,
 * that no entity instance is ever represented as more than one object in the application.
 * 
 * @package	Glue-ORM
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Entity {
	/**
	 * Entity mappers identity map.
	 * @var array
	 */
	static protected $mappers = array();
	
	/******************************************************************************/
	/* Constants																  */
	/******************************************************************************/	
	
	/**
	 * Mode == overwrite all fields of the object with supplied data.
	 * @var integer
	 */
	const OVERWRITE	= 1;
	
	/**
	 * Mode == add missing fields to the object but don't touch existing ones.
	 * @var integer
	 */
	const COMPLETE	= 2;

	/******************************************************************************/
	/* Properties that may NOT be set in children classes						  */
	/******************************************************************************/
	
	/**
	 * Name of this entity.
	 * @var string
	 */
	protected $name;
	
	/**
	 * Model objects identity map.
	 * @var array
	 */
	protected $map = array();	
	
	/**
	 * Proxy class instance that's going to be cloned to build new model objects.
	 * @var object
	 */
	protected $pattern;
	
	/**
	 * Name of the proxy class.
	 * @var string
	 */
	protected $proxy_class_name;	

	/******************************************************************************/
	/* Properties that may be set by user in children classes					  */
	/******************************************************************************/

	/**
	 * Name of the model class.
	 * @var string
	 */
	protected $model;
	
	/**
	 * Fields of this entity.
	 * @var array
	 */
	protected $fields;
	
	/**
	 * Fields => model class properties mapping.
	 * @var array
	 */
	protected $properties;
	
	/**
	 * Fields => PHP types mapping.
	 * @var array
	 */
	protected $types;
	
	/**
	 * Primary key fields.
	 * @var array
	 */
	protected $pk;
	
	/**
	 * Primary key fields => foreign key fields mapping.
	 * @var array
	 */
	protected $fk;
	
	/**
	 * Database identifier.
	 * @var string
	 */
	protected $db;
	
	/**
	 * Tables.
	 * @var array
	 */	
	protected $tables;
	
	/**
	 * Fields => tables/columns mapping.
	 * @var array
	 */	
	protected $columns;
	
	/**
	 * Is the pk autoincrementing ?
	 * @var boolean
	 */
	protected $autoincrement;	
	
	/******************************************************************************/
	/* Constructor																  */
	/******************************************************************************/
	
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

	/******************************************************************************/
	/* Initializing functions													  */
	/******************************************************************************/
	
	/**
	 * Returns default database identifier.
	 * 
	 * @return string 
	 */	
	protected function default_db() {
		return 'default';
	}	
	
	/**
	 * Returns default model class name.
	 * 
	 * @return string 
	 */	
	protected function default_model() {
		$model = Kohana::config('glue')->model_prefix . ucfirst($this->name);
		return class_exists($model) ? $model : 'stdClass'; // no upper case !
	}
	
	/**
	 * Returns default tables.
	 * 
	 * @return array 
	 */	
	protected function default_tables() {
		return array(inflector::plural($this->name));
	}

	/**
	 * Returns default fields.
	 * 
	 * @return array 
	 */		
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

	/**
	 * Returns default fields => model class properties mapping.
	 * 
	 * @return array 
	 */	
	protected function default_properties() {
		return array_combine($this->fields, $this->fields);
	}
	
	/**
	 * Returns default fields => tables/columns mapping.
	 * 
	 * @return array 
	 */
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

	/**
	 * Returns default fields => PHP types mapping.
	 * 
	 * @return array 
	 */
	protected function default_types() {
		$types = array();

		$data = $this->introspect();
		foreach($this->columns as $f => $arr) {
			foreach ($arr as $table => $column) break;
			$types[$f] = $data[$table][$column]['type'];
		}

		return $types;
	}
	
	/**
	 * Returns default primary key fields.
	 * 
	 * @return array 
	 */
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
	
	/**
	 * Returns default primary key fields => foreign key fields mapping.
	 * 
	 * @return array 
	 */	
	protected function default_fk() {
		$fk = array();
		foreach($this->pk as $f) $fk[$f] = $this->name.'_'.$f;
		return $fk;
	}	
	
	/**
	 * Returns whether or not the pk is autoincrementing.
	 * 
	 * @return boolean 
	 */		
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

	/******************************************************************************/
	/* Pattern related functions												  */
	/******************************************************************************/	

	/**
	 * Creates the proxy class instance that's going to be cloned to build new model
	 * objects. You may redefine this if your model class constructor accepts
	 * parameters (in which case you may pass them to proxy_new()) or if there is a need
	 * for some additional setup after calling the constructor.
	 * 
	 * @return object 
	 */
	protected function pattern_create() {
		return $this->proxy_new();
	}

	/**
	 * Lazy-loads and returns pattern object. Don't redefine this, redefine
	 * pattern_create instead.
	 * 
	 * @return object 
	 */
	final protected function pattern_get() {
		if ( ! isset($this->pattern))
			$this->pattern = $this->pattern_create();
		return $this->pattern;
	}
	
	/******************************************************************************/
	/* Fields related functions													  */
	/******************************************************************************/	
	
	/**
	 * Formats a value coming from the data source for storage into an object property.
	 * 
	 * @param string	$field
	 * @param mixed		$value
	 * 
	 * @return mixed
	 */
	protected function field_format($field, $value) {
		if (isset($value) && glue::isdef($value))	// settype(null, 'integer') => 0 !!!
			settype($value, $this->types[$field]);
		return $value;
	}
	
	/**
	 * Formats a value coming from an object property for storage into the data
	 * source. This is not neccessarily the opposite of field_format. You should
	 * only have to redefine this if you've redefined field_format to do
	 * something really funky.
	 * 
	 * @param string	$field
	 * @param mixed		$value
	 * 
	 * @return mixed
	 */
	protected function field_unformat($field, $value) {
		return $value;
	}

	/**
	 * Sets the value of a model object field. The value is stored as-is, no type casting
	 * or conversion of any kind occurs. For this, see field_format() and field_unformat().
	 * 
	 * @param mixed		$object
	 * @param string	$field
	 * @param mixed		$value
	 * 
	 * @return mixed
	 */
	public function field_set($object, $field, $value) {
		$object->glue_set_property($this->properties[$field], $value);
	}

	/**
	 * Returns the value of a model object field. The value is returned as-is,
	 * no type casting or conversion of any kind occurs. For this, see field_format()
	 * and field_unformat().
	 * 
	 * If the property doesn't exist at all in the object, this function will return
	 * glue::undef(). You may test for this by running the returned value through
	 * glue::isdef($value).
	 * 
	 * @param mixed		$object
	 * @param string	$field
	 * 
	 * @return mixed
	 */
	public function field_get($object, $field) {
		return $object->glue_get_property($this->properties[$field]);
	}
	
	/**
	 * Returns the alias that is supposed to be used for the field $field in the select
	 * clause, when the alias used for this entity is $entity_alias.
	 * 
	 * @param string $entity_alias
	 * @param string $field
	 * 
	 * @return string
	 */
	protected function field_alias($entity_alias, $field) {
		return $entity_alias . '::' . $field;
	}
	
	/**
	 * Extracts the value of a field from a data source row. Returns glue::undef()
	 * if the field is absent from the row.
	 * 
	 * @param array		$row
	 * @param string	$entity_alias
	 * @param string	$field
	 * 
	 * @return mixed
	 */
	protected function field_unalias($row, $entity_alias, $field) {
		$field_alias = $this->field_alias($entity_alias, $field);
		if (array_key_exists($field_alias, $row))
			return $row[$field_alias];
		else
			return glue::undef();
	}
	
	/******************************************************************************/
	/* Objects related functions												  */
	/******************************************************************************/	
	
	/**
	 * Creates and returns a new model object, loaded with given data. The
	 * data is expected to be in a format suitable for storage into
	 * the object properties. See field_format() and field_unformat().
	 * 
	 * @param array $data	Fields => formatted values mapping.
	 * 
	 * @return mixed
	 */
	public function object_new($formatted_data) {
		$object = clone $this->pattern_get();
		foreach ($formatted_data as $field => $value)
			$this->field_set($object, $field, $value);
		return $object;
	}
	
	/**
	 * Returns the primary key representation as it would be stored in the
	 * identity map. The data is expected to be in a format coming straight from the
	 * data source. Returns null if the primary key fields are not set in the
	 * data array.
	 * 
	 * @param array $raw_data	Fields => data source values mapping.
	 * 
	 * @return string
	 */
	protected function object_pk($raw_data) {
		foreach ($this->pk as $f)  {
			if (isset($raw_data[$f]) && glue::isdef($raw_data[$f]))
				$arr[] = $raw_data[$f];
			else
				return null;
		}
		return json_encode($arr);
	}
	
	/**
	 * Creates a new model object and adds it to identity map. The data is
	 * expected in a format coming straight from the data source.
	 * 
	 * @param string	$pk			Encoded primary key.
	 * @param array		$raw_data	Fields => data source values mapping.
	 * 
	 * @return string
	 */
	protected function object_create($pk, $raw_data) {
		foreach ($raw_data as $field => $value)
			$formatted_data[$field] = $this->field_format($field, $value);
		$this->map[$pk] = $this->object_new($formatted_data);
	}
	
	/**
	 * Updates an object already existing in the identity map with fresh
	 * values coming from the data source.
	 * 
	 * @param string	$pk			Encoded primary key. 
	 * @param array 	$raw_data	Fields => data source values mapping.
	 * @param integer 	$mode		Overwrite all fields with supplied data or
	 * 								simply add missing fields.
	 * 
	 * @return string
	 */
	protected function object_upgrade($pk, $raw_data, $mode) {
		// Get object from identity map :
		$object = $this->map[$pk];
		
		// Upgrade object :
		foreach ($raw_data as $field => $value) {
			// Skip pk fields :
			if (in_array($field, $this->pk, true)) continue;
			
			// Update / add fields :
			if ($mode === self::OVERWRITE || (self::COMPLETE && ! glue::isdef($this->field_get($object, $field)))) {
				$formatted_value = $this->field_format($field, $value);
				$this->field_set($object, $field, $formatted_value);
			}
		}
	}	
	
	/**
	 * Returns the object represented in $data. If the object already exists in
	 * the identity map, it is updated with new data and returned. Otherwise
	 * it is created and added to identity map.
	 * 
	 * @param array		$row			Columns aliases => data source values mapping.
	 * @param string	$entity_alias	Entity alias that was used to identify this entity.
	 * @param integer	$mode			Overwrite all fields with supplied data or
	 * 									simply add missing fields.	
	 * 
	 * @return object
	 */
	public function object_get($row, $entity_alias, $mode = self::COMPLETE) {
		// Extract data from row : 
		foreach($this->fields as $field)
			$raw_data[$field] = $this->field_unalias($row, $entity_alias, $field);			
		
		// Compute encoded pk :
		$pk = $this->object_pk($raw_data);
		if ( ! isset($pk)) return null;

		// Look up pk in id map :
		if (isset($this->map[$pk]))
			$this->object_upgrade($pk, $raw_data, $mode);
		else
			$this->object_create($pk, $raw_data);

		// Return object :
		return $this->map[$pk];
	}

	/******************************************************************************/
	/* Proxy class related functions											  */
	/******************************************************************************/
	
	/**
	 * Returns the name of the proxy class.
	 * 
	 * @return string
	 */
	protected function proxy_class_name() {
		if ( ! isset($this->proxy_class_name))
			$this->proxy_class_name = 'Glue_Proxy_' . strtr($this->name, '_', '0') . '_' . strtr($this->model, '_', '0');
		return $this->proxy_class_name;
	}
	
	/**
	 * Creates proxy class file on disk.
	 */
	protected function proxy_class_create() {
		// Get proxy class name :
		$class = $this->proxy_class_name();

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
	
	/**
	 * Creates and returns a new instance of the proxy class. Any parameters passed to
	 * this function are forwarded to the proxy class constructor, and ultimately to the
	 * model class constructor.
	 * 
	 * @return object
	 */
	protected function proxy_new() {
		// Make sure proxy class exists :
		$proxy_class = $this->proxy_class_name();
		if ( ! class_exists($proxy_class))
			$this->proxy_class_create();
		
		// Call proxy class constructor with given parameters :
		$args	= func_get_args();
		$refl	= new ReflectionClass($proxy_class);
		$proxy	= $refl->newInstanceArgs($args);
		 
        return $proxy; 
	}

	/**
	 * Lazy loads an object property.
	 * 
	 * @param object $object
	 * @param string $var
	 */
	public function proxy_load_var($object, $var) {
		if ($field = array_search($var, $this->properties))
			$this->proxy_load_field($object, $field);
		else
			$this->proxy_load_relationship($object, $var);
	}
	
	/**
	 * Lazy loads an object field.
	 * 
	 * @param object $object
	 * @param string $field
	 */
	protected function proxy_load_field($object, $field) {
		// Build query :
		$query = glue::select($this->name, $set);
		foreach($this->object_pk($object) as $f => $val)
			$query->where($f, '=', $val);
		$query->fields($field);

		// Execute query :
		$query->execute();
	}	

	/**
	 * Lazy loads an object relationship.
	 * 
	 * @param object $object
	 * @param string $relationship
	 */
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
	
	/******************************************************************************/
	/* Sets related functions													  */
	/******************************************************************************/	
	
	// Deletes all the database representations of the given objects.
	public function delete($set) {
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
	public function insert($set) {
		// Set is empty ? Do nothing :
		if (count($set) === 0) return;

		// Get objects as array :
		$objects = $set->as_array();

		// This entity has an autoincrementing pk ? Initialize it with null values :
		if ($this->autoincrement) {
			$arr = array_fill(0, count($objects), array($this->pk[0] => null));
			$this->field_set($objects, $arr);
		}

		// Get populated fields and their values :
		$populated = $this->field_get($objects);

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
					$this->field_set($group, $arr);
				}
			}

			// Mark properties as synched with db :
			$this->object_set_clean($group, $fields);
		}
	}

	// Updates the database representations of all objects of the given set.
	public function update($set) {
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

	/******************************************************************************/
	/* Query related functions													  */
	/******************************************************************************/	

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

	/* TODO ajouter object_dirty basé sur object_compare ? */	
	
	/******************************************************************************/
	/* Getters																	  */
	/******************************************************************************/
	
	public function name()			{ return $this->name;		}
	public function fields()		{ return $this->fields;		}
	public function properties()	{ return $this->properties;	}
	public function types()			{ return $this->types;		}
	public function pk()			{ return $this->pk;			}
	public function fk()			{ return $this->fk;			}
	public function model()			{ return $this->model;		}

	/******************************************************************************/
	/* Miscellaneous functions											 		  */
	/******************************************************************************/	
	
	/**
	 * Returns the relationship mapper for the relationship $name of this entity.
	 * 
	 * @param string $name
	 * 
	 * @return Glue_Relationship 
	 */
	public function relationship($name) {
		return glue::relationship($this->name, $name);
	}

	/**
	 * Clears the identity map of this entity mapper.
	 */
	public function clear() {
		$this->map = array();
	}	
	
	/**
	 * Returns a view representing this entity mapper for debugging.
	 */
	abstract public function debug();

	/******************************************************************************/
	/* Static functions															  */
	/******************************************************************************/
	
	/**
	 * Lazy loads an entity mapper, stores it in cache, and returns it.
	 * 
	 * @param string $name	Entity name.
	 * 
	 * @return Glue_Entity
	 */
	static public function get($name) {
		$name = strtolower($name);
		if( ! isset(self::$entities[$name]))
			self::$entities[$name] = self::build($name);
		return self::$entities[$name];
	}

	/**
	 * Clears the identity maps of all entity mappers loaded so far.
	 * 
	 * @return Glue_Entity
	 */
	static public function clear_all() {
		foreach(self::$entities as $mapper)
			$mapper->clear();
	}

	/**
	 * Instanciate and returns an entity mapper. Chooses the right entity class to use,
	 * based on the name of the entity and the available classes.
	 *  
	 * @param string $name	Entity name.
	 * 
	 * @return Glue_Entity
	 */
	static protected function build($name) {
		$class = 'Glue_Entity_'.ucfirst($name);
		if (class_exists($class))
			$entity = new $class($name);
		else
			$entity	= new self($name);
		return $entity;
	}
}


