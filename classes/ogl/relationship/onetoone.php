<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_OneToOne extends OGL_Relationship {
	public function to() {
		if ( ! isset($this->to)) {
			if (substr($this->name(), -1) == '1')
				$this->to = substr($this->name(), 0, -1);
			else
				$this->to = $this->name();
		}
		return OGL_Entity::get($this->to);
	}

	public function fk() {
		if ( ! isset($this->fk)) {
			$pk = array_keys($this->from()->pk());
			$this->fk = array_combine($pk, $pk);
		}
		return $this->fk;
	}

	public function reverse() {
		if ( ! isset($this->reverse))
			$this->reverse = $this->from()->name().'1';
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
		return new OGL_Command_With_OneToOne($this, $src_set, $trg_set, $trg_fields);
	}
}