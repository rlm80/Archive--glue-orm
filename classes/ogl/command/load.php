<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_Load extends OGL_Command {
	protected $entity;

	public function  __construct($entity, $trg_set) {
		parent::__construct($trg_set);
		$this->entity = $entity;
	}

	protected function query_exec($parameters)	{
		$query = $this->query_get()->parameters($parameters);
		return $query->execute()->as_array();
	}
	
	protected function query_contrib($query, $is_root) {
		parent::query_contrib($query, $is_root);

		// Entities and aliases :
		$trg_entity = $this->trg_set->entity;
		$trg_alias	= $this->trg_set->name;

		// From :
		$trg_entity->query_from($query, $trg_alias);

		// Restrict result with where conditions (in WHERE clause !!!) :
		foreach($this->where as $w)
			$trg_entity->query_where($query, $trg_alias, $w['field'], $w['op'], $w['expr']);
	}
	
	protected function load_relationships($result) {}
}