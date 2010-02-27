<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_Load extends OGL_Command {
	protected $entity;

	public function  __construct($entity, $trg_set) {
		parent::__construct($trg_set);
		$this->entity = $entity;
	}

	protected function is_root() {
		return true;
	}

	protected function query_exec()	{
		$query = $this->query_get();
		return $query->execute()->as_array();
	}

	// Add table, requested fields, db builder calls : (TODO simplifier ça)
	protected function query_contrib($query) {
		$this->entity->query_from($query, $this->trg_set->name);
		$this->entity->query_fields($query, $this->trg_set->name, $this->fields);
		$this->apply_calls($query);
	}
	
	protected function load_relationships($result) {}
}