/*
	This view is used as a template to generate proxy classes code.

	It's best to keep proxy classes as lightweight as possible and keep the bulk
	of the code in the mappers because
	- unlike mappers, proxy classes cannot be extended by the user,
	- one proxy class by entity needs to be compiled,
	- the more we put here the closer we get to Active Record and the very same problems
	  we wanted to avoid by using Data Mapper.

	There are two kind of functions here :
	- instance functions : implement active record features,
	- static functions : mapper code that must execute in the scope of the model
	  class to gain access to protected properties,
	- magic functions : __get to implement lazy loading of fields and properties.
*/

class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	// Best knowledge we have about the state of the object's data in the DB :
	public $glue_db_state = array();

	// Entity name :
	static public $glue_entity = <?php var_export($entity) ?>;

	// Constructor :
	public function __construct() {
		// Unset null variables so that __get is called on first access :
		foreach(get_object_vars($this) as $var => $value)
			if (is_null($value))
				unset($this->$var);
			
		// Call parent constructor :
		if (method_exists(get_parent_class($this), '__construct'))
			parent::__construct();
	}
	
	// Entity name of current instance :
	public function glue_entity() {
		return self::$glue_entity;
	}

	// Set values coming from the database :
	static public function glue_db_set($objects, $values, $mapping) {
		$mapper		= glue::entity(self::$glue_entity);
		$properties	= $mapper->properties();
		$types		= $mapper->types();
		foreach($objects as $key => $obj) {
			if (isset($values[$key])) {
				$vals = $values[$key];
				$obj_vars = get_object_vars($obj);
				foreach($mapping as $col => $field) {
					$prop = $properties[$field];
					if ( ! isset($obj_vars[$prop])) {
						$val = $vals[$col];
						settype($val, $types[$field]);
						$obj->glue_db_state[$field] = $obj->$prop = $val;
					}
				}
			}
		}
	}

	// Get pk :
	static public function glue_pk($objects) {
		$pks = array();
		$mapper		= glue::entity(self::$glue_entity);
		$properties	= $mapper->properties();
		$pk			= $mapper->pk();
		foreach($pk as $f) {
			$prop = $properties[$f];
			foreach($objects as $key => $obj) {
				$pks[$key][$f] = $obj->$prop;
			}
		}
		return $pks;
	}

	// Getter :
	public function glue_get($field, $is_field = true) {
		$properties	= glue::entity(self::$glue_entity)->properties();
		$prop = $is_field ? $properties[$field] : $field;
		return $this->$prop;
	}

	// Setter :
	public function glue_set($field, $value, $is_field = true) {
		$properties	= glue::entity(self::$glue_entity)->properties();
		$prop = $is_field ? $properties[$field] : $field;
		$this->$prop = $value;
	}

	// Active Record features :
	public function delete() { return glue::set($this)->delete(); }
	public function insert() { return glue::set($this)->insert(); }
	public function update() { return glue::set($this)->update(); }

	// Lazy loading of properties and relationships :
	public function __get($var) {
		// __get called even though $var already initialized ?
		$obj_vars = get_object_vars($this);
		if (isset($obj_vars[$var]))
			trigger_error("Cannot access protected property ".get_parent_class($this)."::".'$'."$var", E_USER_ERROR);

		// Lazy loading of $var :
		$mapper = glue::entity(self::$glue_entity);
		$properties	= $mapper->properties();
		if ($field = array_search($var, $properties))
			$mapper->proxy_load_field($this, $field);
		else
			$mapper->proxy_load_relationship($this, $var);

		return $this->$var;
	}
}