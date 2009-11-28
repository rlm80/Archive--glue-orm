<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_OneToMany extends OGL_Relationship {
	public function to() {
		if ( ! isset($this->to)) {
			if (substr($this->name(), -1) == 'S')
				$this->to = substr($this->name(), 0, -1);
			else
				$this->to = inflector::singular($this->name());
		}
		return OGL_Entity::get($this->to);
	}

	public function fk() {
		if ( ! isset($this->fk))
			$this->fk = $this->from()->default_fk();
		return $this->fk;
	}

	public function reverse() {
		if ( ! isset($this->reverse))
			$this->reverse = $this->from()->name();
		return OGL_Relationship::get($this->to()->name(), $this->reverse);
	}

	public function add_joins($query, $src_alias, $trg_alias) {
		$src_fields = $this->from()->fields();
		$trg_fields = $this->to()->fields();
		$query->join(array($this->to()->table(), $trg_alias), 'INNER');
		foreach($this->fk() as $src_field => $trg_field)
			$query->on($src_alias.'.'.$src_fields[$src_field]['column'], '=', $trg_alias.'.'.$trg_fields[$trg_field]['column']);
	}

	public function create_command($src_set, $trg_set, $trg_fields, $pivot_fields) {
		return new OGL_Command_With_OneToMany($this, $src_set, $trg_set, $trg_fields);
	}
}