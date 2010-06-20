/*
	This view is used as a template for proxy classes. Proxy classes extend model classes
	to decorate them with all that Glue needs, and to add typical Active Record
	features to model objects.
*/
class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	// Entity name :
	static protected $glue_entity_name	= <?php var_export($entity_name) ?>; // Entity name
	
	// Useful knowledge from the mapper copied here for convenience :
	static protected $glue_fields		= <?php var_export($fields)		?>; // Fields
	static protected $glue_properties	= <?php var_export($properties)	?>; // Fields => properties mapping
	static protected $glue_lazy_props	= <?php var_export($lazy_props)	?>; // Properties to be lazy loaded (null means all of them)
	static protected $glue_pk			= <?php	var_export(array_combine($pk, $pk)) ?>; // PK fields => PK fields mapping
	
	// Entity mapper :
	public function glue_entity() { return glue::entity(self::$glue_entity_name); }

	// Glue external access to internal properties :
	public function glue_set($field, $value) { $this->{self::$glue_properties[$field]} = $value; }
	public function glue_get($field)		 { return $this->{self::$glue_properties[$field]};	 }
	public function glue_pk()				 { return array_map(array($this, 'glue_get'), self::$glue_pk); }

	// Active Record like functions :
	public function delete() { return $this->glue_entity()->delete($this); }
	public function insert() { return $this->glue_entity()->insert($this); }
	public function update() { return $this->glue_entity()->update($this); }

	// Unset lazy loaded variables so that __get is called on first access :
	public function glue_unset() {
		// List of variables to be unset :
		if ( ! isset(self::$glue_lazy_props))
			$vars = array_keys(get_object_vars($this));
		else
			$vars = self::$glue_lazy_props;

		// Unset variables :
		foreach($vars as $var)
			if (isset($this->$var) && is_null($this->$var))
				unset($this->$var);
	}

	// Lazy loading of properties and relationships :
	public function __get($var) {
		if ( ! isset(self::$glue_lazy_props) || in_array($var, self::$glue_lazy_props)) {
			// Build query :
			$query = glue::qselect(self::$glue_entity_name, $set);
			foreach($this->glue_pk() as $f => $val)
				$query->where($f, '=', $val);

			// Add lazy loading bit :
			if (in_array($var, $this->glue_fields)) // $var is a property
				$query->fields($var);
			else									// $var is a relationship
				$query->with($set, inflector::singular($var));

			// Execute query :
			$query->execute();

			// Return $var :
			return $this->$var;
		}
		return parent::__get($var);
	}
}