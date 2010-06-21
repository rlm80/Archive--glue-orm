/*
	This view is used as a template for proxy classes. Proxy classes extend model
	classes	to add typical Active Record features to model objects and to install
	setters/getters that Glue can use to access protected properties of model classes.

	It's best to keep proxy classes as lightweight as possible and keep the bulk
	of the code in the mappers because :
	- proxy classes cannot be extended by the user,
	- proxy classes code is eval'd, making it hard to debug and hidden from opcode cache engines,
	- the more we put here the closer we get to Active Record and the very same problems we wanted to avoid with this project.
*/

class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	static protected $glue_entity_name	= <?php var_export($entity_name) ?>;	// Entity name
	static protected $glue_properties	= <?php var_export($properties)	?>;		// Fields => properties mapping
	
	// Entity mapper :
	public function glue_entity() { return glue::entity(self::$glue_entity_name); }
	
	// Active Record like functions :
	public function delete() { return $this->glue_entity()->delete($this); }
	public function insert() { return $this->glue_entity()->insert($this); }
	public function update() { return $this->glue_entity()->update($this); }

	// Instance variable inspection functions :
	public function glue_vars()				 { return array_keys(get_object_vars($this));			}
	public function glue_isset($var)		 { return isset($this->$var);							}
	public function glue_isnull($var)		 { return is_null($this->$var);							}
	public function glue_unset($var)		 { unset($this->$var);									}

	// Mass field setter :
	public static function glue_set($field, $objects, $values) {
		$var = self::$glue_properties[$field];
		if (is_array($objects)) {
			for($i = count($objects) - 1; $i >= 0; $i --)
				$objects[$i]->$var = $values[$i];
		}
		else
			$objects->$var = $values;
	}

	// Mass field getter :
	public static function glue_get($field, $objects) {
		$var = self::$glue_properties[$field];
		if (is_array($objects)) {
			foreach($objects as $obj)
				$values[] = $obj->$var;
		}
		else
			$values = $objects->$var;
		return $values;
	}

	// Lazy loading of properties and relationships :
	public function __get($var) {
		if ($this->glue_entity()->proxy_is_lazy($var)) {
			$this->glue_entity()->proxy_lazy_load($this, $var);
			return $object->$var;
		}
		return parent::__get($var);
	}
}