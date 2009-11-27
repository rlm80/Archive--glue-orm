<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class OGL_Command_With extends OGL_Command {
	protected $relationship;

	public function  __construct($relationship, $src_set, $trg_set, $trg_fields) {
		parent::__construct($src_set, $trg_set, $trg_fields);
		$this->relationship	= $relationship;
	}

	protected function is_valid_call($method, $args) {
		static $allowed = array('limit','offset','order_by','where','where_close','where_open','and_where','and_where_close','and_where_open','or_where','or_where_close','or_where_open');
		if ( ! $this->is_root())
			return false;
		else
			return ! (array_search($method, $allowed) === FALSE);
	}
}