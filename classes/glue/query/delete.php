<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Query_Delete extends Glue_Query {
	public function __construct($entity_name, &$trg_set = null, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		parent::__construct($entity_name, $trg_set, $conditions, $order_by, $limit, $offset);

		// Load only pk :
		$this->active_command->fields($trg_set->entity()->pk());

		return $this;
	}

	public function with($src_set, $relationship_name, &$trg_set = null, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		parent::with($src_set, $relationship_name, $trg_set, $conditions, $order_by, $limit, $offset);

		// Load only pk :
		$this->active_command->fields($trg_set->entity()->pk());

		return $this;
	}

	public function exec() {
		if (parent::exec()) {
			// Delete set contents :
			foreach($this->sets as $hash => $data)
				$data['set']->delete();
		}
	}
}
