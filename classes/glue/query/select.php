<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Query_Select extends Glue_Query{
	public function __construct($entity_name, &$set, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		parent::__construct($entity_name, $set, $conditions);
		
		// Add order by, limit, offset if any :
		if (isset($order_by))	$this->order_by($order_by);
		if (isset($limit))		$this->limit($limit);
		if (isset($offset))		$this->offset($offset);		
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
