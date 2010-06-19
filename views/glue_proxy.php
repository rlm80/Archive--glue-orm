/*
	This view is used as a template class for proxy classes.
*/
class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	protected $glue_mapper;

	public function glue_init($mapper) {
		// Set mapper :
		$this->glue_mapper = $mapper;

		// Unset all null variables so that __get is called on first access :
		foreach(get_object_vars($this) as $var => $value)
			if (is_null($value))
				unset($this->$var);
	}

	public function glue_set($property, $value) { $this->$property = $value; }
	public function glue_get($property)			{ return $this->$property;	 }

	public function __get($var) {
		$this->glue_mapper->object_lazy_load($this, $var);
		return $this->$var;
	}

	public function delete() { return $this->glue_mapper->delete($this); }
	public function insert() { return $this->glue_mapper->insert($this); }
	public function update() { return $this->glue_mapper->update($this); }
}