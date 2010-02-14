<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_Load extends OGL_Command {
	protected $entity;

	public function  __construct($entity, $trg_set, $trg_fields) {
		parent::__construct($trg_fields, $trg_set);
		$this->entity = $entity;
	}

	protected function is_valid_call($method, $args) {
		static $allowed = array('limit','offset','order_by','where','where_close','where_open','and_where','and_where_close','and_where_open','or_where','or_where_close','or_where_open');
		return ! (array_search($method, $allowed) === FALSE);
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
		$this->entity->query_fields($query, $this->trg_fields, $this->trg_set->name);
		$this->apply_calls($query);
	}
	
	protected function load_relationships($result) {}
}