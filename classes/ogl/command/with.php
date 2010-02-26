<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With extends OGL_Command {
	protected $relationship;
	protected $src_set;

	public function  __construct($relationship, $src_set, $trg_set, $fields) {
		parent::__construct($fields, $trg_set);
		$this->relationship			= $relationship;
		$this->src_set				= $src_set;
		$this->src_set->commands[]	= $this;
	}

	protected function is_valid_call($method, $args) {
		static $allowed = array('limit','offset','order_by','where','where_close','where_open','and_where','and_where_close','and_where_open','or_where','or_where_close','or_where_open');
		if ( ! $this->is_root())
			return false;
		else
			return ! (array_search($method, $allowed) === FALSE);
	}

	protected function load_result($result) {
		$this->src_set->entity->object_load($result, $this->src_set->name.':');
		parent::load_result($result);
	}

	protected function load_result_self($result) {
		// Load objects :
		parent::load_result_self($result);

		// Load relationships :
		$direct		= $this->relationship;
		$reverse	= $this->relationship->reverse();
		$src_key = $this->src_set->name.':__object';
		$trg_key = $this->trg_set->name.':__object';
		foreach($result as $row) {
			$src_obj = isset($row[$src_key]) ? $row[$src_key] : null;
			$trg_obj = isset($row[$trg_key]) ? $row[$trg_key] : null;
			$direct->link($src_obj, $trg_obj);
			$reverse->link($trg_obj, $src_obj);
		}
	}

	protected function query_exec()	{
		$query = $this->query_get();
		return $this->src_set->query_exec($query);
	}

	protected function query_init() {
		$query = parent::query_init();
		$this->src_set->query_init($query);
		return $query;
	}

	protected function query_contrib($query) {
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;
		$this->relationship->join($query, $src_alias, $trg_alias);
		$this->relationship->to()->query_fields($query, $trg_alias, $this->fields);
		$this->apply_calls($query);
	}

	protected function is_root() {
		switch ($this->root) {
			case OGL::AUTO :	$is_root = $this->relationship->multiple(); break;
			case OGL::ROOT :	$is_root = true;	break;
			case OGL::SLAVE :	$is_root = false;	break;
			default : throw new Kohana_Exception("Invalid value for root property in a command.");
		}
		return $is_root;
	}
}