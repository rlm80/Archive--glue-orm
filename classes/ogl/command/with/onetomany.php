<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_OneToMany extends OGL_Command_With {
	public function is_root() {
		return true;
	}

	public function query_result($result) {
		parent::query_result($result);
	}

	public function query_contrib($query) {
		parent::query_contrib($query);
	}
	
	public function query_contrib($query) {
		// Gather data :
		$rel		= $this->relationship;
		$fk			= $rel->fk();
		$src_fields = $rel->from()->fields();
		$trg_fields = $rel->to()->fields();
		$src_table	= $rel->from()->table();
		$trg_table	= $rel->to()->table();
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;

		// join trg table :
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
}