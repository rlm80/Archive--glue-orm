/*
	This view is used as a template to generate proxy classes code.

	It's best to keep proxy classes as lightweight as possible and keep the bulk
	of the code in the mappers because
	- proxy classes cannot be extended by the user,
	- proxy classes code is eval'd, making it hard to debug and hidden from opcode cache engines,
	- the more we put here the closer we get to Active Record and the very same problems
	  we wanted to avoid by using Data Mapper.

	There are two kind of functions here :
	- instance functions : implement active record features,
	- static functions : mapper code that must execute in the scope of the model
	  class to gain access to protected properties,
	- magic functions : __get to implement lazy loading of fields and properties.
*/

class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	// Static properties :
	static public $glue_entity		= <?php var_export($entity)		?>;	// Entity name
	static public $glue_properties	= <?php var_export($properties)	?>;	// Fields => properties mapping
	static public $glue_types		= <?php var_export($types)		?>;	// Fields => property types mapping
	static public $glue_lazy_props	= <?php var_export($lazy_props)	?>;	// Lazy-loadable properties

	// Entity mapper :
	static public function glue_entity() { return glue::entity(self::$glue_entity); }

	// Mass field setter :
	static public function glue_set($objects, $fields, $values) {
		// Turn parameters into arrays if they are not already :
		if ( ! is_array($objects))	$objects	= array($objects);
		if ( ! is_array($fields))	$fields		= array($fields);
		if ( ! is_array($values))	$values		= array($values);

		// Get properties :
		foreach($fields as $f) $props[self::$glue_properties[$f]] = self::$glue_types[$f];

		// Set values :
		reset($objects);
		reset($values);
		while ($obj = next($objects)) {
			$vals = next($values);
			reset($props);
			reset($vals);
			while (list($prop, $type)) = each($props))
				$obj->$prop = settype(next($vals), $type);
		}
	}

	// Mass field getter :
	static public function glue_get($objects, $fields) {
		// Turn parameters into arrays if they are not already :
		if ( ! is_array($objects))	$objects	= array($objects);
		if ( ! is_array($fields))	$fields		= array($fields);

		// Get properties :
		foreach($fields as $f) $props[] = self::$glue_properties[$f];

		// Get values :
		foreach($objects as $obj) {
			$vals = array();
			foreach($props as $prop) $vals[] = $obj->$prop;
			$values[] = $vals;
		}

		return $values;
	}

	// Mass lazy-loadable properties unsetter :
	static public function glue_unset($objects) {
		// Turn parameters into arrays if they are not already :
		if ( ! is_array($objects)) $objects = array($objects);
			
		// Loop on objects :
		foreach($objects as $obj) {
			// Variables to unset :
			if (isset(self::$lazy_props))
				$vars = array_intersect_keys(array_keys(get_object_vars($obj)), self::$lazy_props);
			else
				$vars = array_keys(get_object_vars($obj));

			// Unset variables :
			foreach($vars as $var)
				if (is_null($obj->$var))
					unset($obj->$var);
		}
	}

	// Active Record features :
	public function delete() { return self::glue_entity()->delete($this); }
	public function insert() { return self::glue_entity()->insert($this); }
	public function update() { return self::glue_entity()->update($this); }

	// Lazy loading of properties and relationships :
	public function __get($var) {
		if ( ! isset(self::$lazy_props) || in_array($var, self::$lazy_props)) {
			$this->glue_entity()->proxy_lazy_load($this, $var);
			return $this->$var;
		}
		return parent::__get($var);
	}
}