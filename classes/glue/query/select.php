<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Query_Select extends Glue_Query{
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
