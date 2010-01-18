<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_ManyToMany extends OGL_Relationship_Indirect {
	protected function default_to() {
		if (substr($this->name, -1) == 'Z')
			$to = substr($this->name, 0, -1);
		else
			$to = inflector::singular($this->name);
		return $to;
	}

	protected function default_fk1() {
		return $this->from->default_fk;
	}

	protected function default_fk2() {
		return array_flip($this->to->default_fk);
	}

	protected function default_pivot() {
		if ($this->from->name < $this->to->name)
			$pivot = $this->from->name.'_'.$this->to->name;
		else
			$pivot = $this->to->name.'_'.$this->from->name;
		return $pivot;
	}

	protected function default_reverse() {
		return $this->from->name.'Z';
	}

	public function cardinality() {
		return self::MULTIPLE;
	}
}