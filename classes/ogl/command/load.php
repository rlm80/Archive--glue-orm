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
	
	protected function query_contrib_from($query) {
		parent::query_contrib_from($query);
		$this->entity->query_from($query, $this->trg_set->name);
	}

	protected function query_contrib_where($query) {
		parent::query_contrib_where($query);
		foreach($this->where as $w)
			$this->trg_set->entity->query_where($query, $this->trg_set->name, $w['field'], $w['op'], $w['expr']);
	}
	
	protected function load_relationships($result) {}
}