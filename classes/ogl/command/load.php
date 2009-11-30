<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_Load extends OGL_Command {
	protected $entity;

	public function  __construct($entity, $trg_set, $trg_fields) {
		parent::__construct(new OGL_Set_Root(), $trg_set, $trg_fields);
		$this->entity = $entity;
	}

	protected function is_valid_call($method, $args) {
		static $allowed = array('limit','offset','order_by','where','where_close','where_open','and_where','and_where_close','and_where_open','or_where','or_where_close','or_where_open');
		return ! (array_search($method, $allowed) === FALSE);
	}

	protected function is_root() {
		return true;
	}

	// Add table, requested fields, db builder calls :
	protected function query_contrib($query) {
		$query->from(array($this->entity->table(), $this->trg_set->name));
		$this->entity->add_fields($query, $this->trg_fields, $this->trg_set->name);
		$this->apply_calls($query);
	}
	
	protected function load_relationships(&$result) {}
}