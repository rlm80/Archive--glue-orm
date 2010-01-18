<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_ManyToOne extends OGL_Relationship_Direct {
	protected function default_to() {
		return $this->name;
	}

	protected function default_fk() {
		return array_flip($this->to->default_fk);
	}

	protected function default_reverse() {
		return $this->from->name.'S';
	}

	public function cardinality() {
		return self::SINGLE;
	}
}