<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Query_Select extends OGL_Query{
	public function __construct($entity_name, &$set) {
		parent::__construct($entity_name, $set);
	}

	public function sort($sort) {
		$this->active_command->sort($sort);
		return $this;
	}

	public function fields() {
		$args = func_get_args();
		call_user_func_array(array($this->active_command, 'fields'), $args);
		return $this;
	}

	public function not_fields() {
		$args = func_get_args();
		call_user_func_array(array($this->active_command, 'not_fields'), $args);
		return $this;
	}
}
