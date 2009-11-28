<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_OneToOne extends OGL_Command_With {
	public function is_root() {
		return false;
	}
	
	public function query_result($result) {

	}

	public function query_contrib($query) {
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;
		$this->relationship->add_joins($query, $src_alias, $trg_alias);
		$this->relationship->to()->add_fields($query, $this->trg_fields, $trg_alias);
		$this->apply_calls($query);
	}
}