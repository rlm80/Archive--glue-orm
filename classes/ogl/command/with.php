<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With extends OGL_Command {
	protected $relationship;
	protected $src_set;

	public function  __construct($relationship, $src_set, $trg_set, $trg_fields) {
		parent::__construct($trg_fields, $trg_set);
		$this->relationship				= $relationship;
		$this->src_set					= $src_set;
		$this->src_set->commands[]		= $this;
	}

	protected function is_valid_call($method, $args) {
		static $allowed = array('limit','offset','order_by','where','where_close','where_open','and_where','and_where_close','and_where_open','or_where','or_where_close','or_where_open');
		if ( ! $this->is_root())
			return false;
		else
			return ! (array_search($method, $allowed) === FALSE);
	}

	protected function load_result($result) {
		$this->src_set->entity->load_objects($result, $this->src_set->name);
		parent::load_result($result);
	}

	protected function load_result_self($result) {
		parent::load_result_self($result);
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;
		$this->relationship->load_relationships($result, $src_alias, $trg_alias);
		$this->relationship->reverse()->load_relationships($result, $trg_alias, $src_alias);
	}

	protected function query_exec()	{
		$query = $this->query_get();
		return $this->src_set->query_exec($query);
	}

	protected function query_init() {
		$query = parent::query_init();
		$this->relationship->from()->query_init($query, $this->src_set->name);
		return $query;
	}

	protected function query_contrib($query) {
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;
		$this->relationship->join($query, $src_alias, $trg_alias);
		$this->relationship->to()->add_fields($query, $this->trg_fields, $trg_alias);
		$this->apply_calls($query);
	}

	protected function is_root() {
		return $this->relationship->multiple;
	}
}