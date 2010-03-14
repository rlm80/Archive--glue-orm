<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Query_Load extends OGL_Query{
	public function __construct($entity_name, &$set) {
		parent::__construct($entity_name, $set);
	}

	public function sort($sort) {
		$this->active_command->sort($sort);
		return $this;
	}

	public function select() {
		$args = func_get_args();
		call_user_func_array(array($this->active_command, 'select'), $args);
		return $this;
	}

	public function not_select() {
		$args = func_get_args();
		call_user_func_array(array($this->active_command, 'not_select'), $args);
		return $this;
	}
}
