/*
	This view is used as a template for proxy classes that extend model classes
	and decorate them with all that Glue needs.
*/
class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	public $glue_entity = '<?php echo $entity_name ?>';

	// Unset all null variables so that __get is called on first access :
	public function glue_init() {
		foreach(get_object_vars($this) as $var => $value)
			if (is_null($value))
				unset($this->$var);
	}

	public function glue_set($property, $value) { $this->$property = $value; }
	public function glue_get($property)			{ return $this->$property;	 }

	public function __get($var) {
		glue::entity($this->glue_entity)->proxy_lazy_load($this, $var);
		return $this->$var;
	}

	public function delete() { return glue::entity($this->glue_entity)->delete($this); }
	public function insert() { return glue::entity($this->glue_entity)->insert($this); }
	public function update() { return glue::entity($this->glue_entity)->update($this); }
}