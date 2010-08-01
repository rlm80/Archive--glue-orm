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
	// Last values that went to, or came from, the database for the properties
	// or the current object (~= best knowledge we currently have about the state
	// of the object's data in the DB)
	protected $glue_db_state = array();

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
	static public function glue_set($objects, $values, $properties, $types) {
		foreach($objects as $key => $obj) {
			if (isset($values[$key])) {
				$vals = $values[$key];
				foreach($properties as $col => $prop) {
					$obj->$prop = $vals[$col];
					if (isset($types[$col]) && isset($obj->$prop) /* casting null to integer => 0 ! we don't want that */ )
						settype($obj->$prop, $types[$col]);
				}
			}
		}
	}
	
	// Gets property values for an array of objects. The returned array of values
	// will have the same keys as the array of objects. The properties => columns
	// mapping is $columns.
	static public function glue_get($objects, $columns) {
		$values = array();
		foreach($objects as $key => $obj) {
			$values[$key] = array();
			foreach($columns as $prop => $col)
				if (property_exists($obj, $prop))
					$values[$key][$col] = $obj->$prop;
		}
		return $values;
	}
	
	// For each values in $values, links object at column $colsrc to object
	// at columns $coltrg, using property $property. If $ismany, a Glue_Set is used
	// and the target object is added to it, otherwise the property is simply set
	// to target object (this function is used to link together related objects).
	static public function glue_link($values, $colsrc, $coltrg, $property, $ismany) {
		foreach($values as $vals) {
			if (isset($vals[$colsrc])) {
				$src = $vals[$colsrc];
				if ($ismany && ! isset($src->$property))
					$src->$property = glue::set();
				if (isset($vals[$coltrg])) {
					if ($ismany)
						$src->$property->add($vals[$coltrg]);
					else
						$src->$property = $vals[$coltrg];
				}
			}
		}
	}
	
	// Copy current property values to $glue_db_state.
	static public function glue_set_clean($objects, $properties) {
		foreach($objects as $obj)
			foreach($properties as $prop)
				$obj->glue_db_state[$prop] = $obj->$prop;
	}
	
	// For each object, returns all property values that differ from what
	// they are in the database (and thus need an update). Returned array
	// is indexed in the same way as $objects.
	static public function glue_get_dirty($objects, $properties) {
		$dirty = array();
		foreach($objects as $key => $obj) {
			$dirty[$key] = array(); 
			foreach($properties as $field => $prop) {
				if (property_exists($obj, $prop))
					if ( ! array_key_exists($prop, $obj->glue_db_state) || $obj->glue_db_state[$prop] !== $obj->$prop)
						$dirty[$key][$field] = $obj->$prop;
			}
		}
		return $dirty;
	}

	// Active Record features :
	public function delete() { return glue::set($this)->delete(); }
	public function insert() { return glue::set($this)->insert(); }
	public function update() { return glue::set($this)->update(); }

	// Lazy loading of properties and relationships :
	public function __get($var) {
		// __get called even though $var already exists ?
		if (property_exists($this, $var))
			trigger_error("Cannot access protected property ".get_parent_class($this)."::".'$'."$var", E_USER_ERROR);
			
		// __get in parent has an answer to this call ? Return that answer.
		if (method_exists(get_parent_class($this), '__get')) {
			$from_parent = parent::__get($var);
			if (isset($from_parent))
				return $from_parent;
		}		

		// Lazy loading of $var :
		glue::entity(self::$glue_entity)->proxy_load_var($this, $var);

		return $this->$var;
	}
}