<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_Load_With_OneToOne extends OGL_Command_Load_With {
	public function is_root() {
		return false;
	}

	public function execute_self() {
		throw new Kohana_Exception("This function should never be called because only root commands can be executed.");
	}
	
	public function load($row) {

	}

	public function query_contrib($query) {
		// Gather data :
		$rel		= $this->relationship;
		$fk			= $rel->fk();
		$src_fields = $rel->from()->fields();
		$trg_fields = $rel->to()->fields();
		$trg_table	= $rel->to()->table();
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;

		// Join trg table :
		$query->join(array($trg_table, $trg_alias), 'INNER');
		foreach($fk as $src_field => $trg_field)
			$query->on($src_alias.'.'.$src_fields[$src_field]['column'],'=',$trg_alias.'.'.$trg_fields[$trg_field]['column']);

		// Add requested fields :
		foreach ($trg_fields as $name => $field)
			if(isset($this->trg_fields[$name]))
				$query->select(array($trg_alias.'.'.$field['column'], $trg_alias.':'.$name));

		// Apply db builder calls :
		$this->apply_calls($query);
	}
	public function execute_query($query) {
	}
}