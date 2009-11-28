<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_ManyToOne extends OGL_Relationship {
	public function to() {
		if ( ! isset($this->to))
			$this->to = $this->name();
		return OGL_Entity::get($this->to);
	}

	public function fk() {
		if ( ! isset($this->fk))
			$this->fk = array_flip($this->to()->default_fk());
		return $this->fk;
	}

	public function reverse() {
		if ( ! isset($this->reverse))
			$this->reverse = $this->from()->name().'S';
		return OGL_Relationship::get($this->to()->name(), $this->reverse);
	}

	public function add_joins($query, $src_alias, $trg_alias) {
		$src_fields = $this->from()->fields();
		$trg_fields = $this->to()->fields();
		$query->join(array($this->to()->table(), $trg_alias), 'INNER');
		foreach($this->fk() as $trg_field => $src_field)
			$query->on($trg_alias.'.'.$trg_fields[$trg_field]['column'], '=', $src_alias.'.'.$src_fields[$src_field]['column']);
	}

	public function create_command($src_set, $trg_set, $trg_fields, $pivot_fields) {
		return new OGL_Command_With_ManyToOne($this, $src_set, $trg_set, $trg_fields);
	}
}