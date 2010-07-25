<?php echo "<?php defined('SYSPATH') OR die('No direct access allowed.');" ?>

/*
	This is an auto-generated proxy class for the entity "<?php echo $entity ?>" when
	the class "<?php echo $model_class ?>" is used as a model class to represent
	its instances.

	There are two kind of functions here :
	- static functions : mapper code that must execute in the scope of the model
	  class to gain access to protected properties,
	- instance functions : implement active record features,
	- magic functions : __get to implement lazy loading of fields and properties.
*/

class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	// Best knowledge we currently have about the state of the object's data in the DB :
	public $glue_db_state = array();

	// Entity name :
	static public $glue_entity = '<?php echo $entity ?>';

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

	// Sets property values for an array of objects. The array of values
	// is expected to have the same keys as the array of objects. The columns
	// => properties mapping is $properties. The columns => types mapping is
	// $types.
	static public function glue_mass_set($objects, $values, $properties, $types = array()) {
		foreach($objects as $key => $obj) {
			$vals = $values[$key];
			foreach($properties as $col => $prop) {
				$obj->$prop = $vals[$col];
				if (isset($types[$col]))
					settype($obj->$prop, $types[$col]);
			}
		}
	}
	
	// Gets property values for an array of objects. The returned array of values
	// will have the same keys as the array of objects. The properties => columns
	// mapping is $columns.
	static public function glue_mass_get($objects, $columns) {
		$values = array();
		foreach($objects as $key => $obj)
			foreach($columns as $prop => $col)			
				$values[$key][$col] = $obj->$prop;
		return $values;
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