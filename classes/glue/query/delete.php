<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Query_Delete extends Glue_Query {
	public function __construct($entity_name, &$set, $conditions = null) {
		parent::__construct($entity_name, $set, $conditions);

		// Load only pk :
		$this->active_command->fields($set->entity()->pk());

		return $this;
	}

	public function with($src_set, $relationship, &$trg_set = null) {
		parent::with($src_set, $relationship, $trg_set);

		// Load only pk :
		$this->active_command->fields($trg_set->entity()->pk());

		return $this;
	}

	public function execute() {
		parent::execute();

		// Delete set contents :
		foreach($this->sets as $hash => $data)
			$data['set']->delete();
	}
}
