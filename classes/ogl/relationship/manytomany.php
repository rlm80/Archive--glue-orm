<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_ManyToMany extends OGL_Relationship {
	protected $pivot;
	protected $fk2;
	
	public function to() {
		if ( ! isset($this->to)) {
			if (substr($this->name(), -1) == 'Z')
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

	public function fk2() {
		if ( ! isset($this->fk2))
			$this->fk2 = array_flip($this->to()->default_fk());
		return $this->fk2;
	}

	public function pivot() {
		if ( ! isset($this->pivot)) {
			if ($this->from()->name() < $this->to()->name())
				$this->pivot = $this->from()->name().'_'.$this->to()->name();
			else
				$this->pivot = $this->to()->name().'_'.$this->from()->name();
		}
		return OGL_Entity::get($this->pivot, array_merge(array_values($this->from_fk()), array_values($this->to_fk())));
	}

	public function reverse() {
		if ( ! isset($this->reverse))
			$this->reverse = $this->from()->name().'Z';
		return OGL_Relationship::get($this->to()->name(), $this->reverse);
	}

	public function create_command($src_set, $trg_set, $trg_fields, $pivot_fields) {
		return new OGL_Command_With_ManyToMany($this, $src_set, $trg_set, $trg_fields, $pivot_fields);
	}
}