<?php echo "<?php defined('SYSPATH') OR die('No direct access allowed.');" ?>

/*
	This is an auto-generated proxy class for the entity "<?php echo $entity ?>" when
	the class "<?php echo $model_class ?>" is used as a model class to represent
	its instances.
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
			
		// Call parent constructor if any :
		if (method_exists(get_parent_class($this), '__construct')) {
			$args = func_get_args();
			call_user_func_array(array($this, 'parent::__construct'), $args);
		}
	}
	
	// Entity name of current instance :
	public function glue_entity() {
		return self::$glue_entity;
	}

	// Sets property value.
	public function glue_set_property($property, $value) {
		if (glue::isdef($value)) 
			$this->$property = $value;
		else
			unset($this->$property);
	}
	
	// Gets property value.
	public function glue_get_property($property) {
		if (property_exists($this, $property))
			return $this->$prop;
		else
			return glue::undef();
	}
	
	// Sets field value.
	public function glue_set($field, $value) {
		glue::entity(self::$glue_entity)->field_set($this, $field, $value, false);
	}
	
	// Gets field value.
	public function glue_get($field) {
		return glue::entity(self::$glue_entity)->field_get($this, $field);
	}		
	
	// Saves current value of properties.
	public function glue_save($properties) {
		foreach($properties as $prop)
			$this->glue_db_state[$prop] = $this->$prop;
	}		
	
	// Gets saved value of property.
	public function glue_get_saved($property) {
		if (array_key_exists($property, $this->glue_db_state))
			return $this->glue_db_state[$property];
		else
			return glue::undef();
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