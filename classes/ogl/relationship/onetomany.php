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

	public function load_relationships($result, $src_alias, $trg_alias)	{
		$src_key	= $src_alias.':__object';
		$trg_key	= $trg_alias.':__object';
		$property	= $this->property();
		foreach($result as $row) {
			if (isset($row[$src_key]) && isset($row[$trg_key])) {
				$src = $row[$src_key];
				$trg = $row[$trg_key];
				$src->$property[spl_object_hash($trg)] = $trg;
			}
		}
	}

	public function create_command($src_set, $trg_set, $trg_fields, $pivot_fields) {
		return new OGL_Command_With_OneToMany($this, $src_set, $trg_set, $trg_fields);
	}
}