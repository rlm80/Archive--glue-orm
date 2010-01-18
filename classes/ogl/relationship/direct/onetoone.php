<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_OneToOne extends OGL_Relationship_Direct {
	protected function default_to() {
		if (substr($this->name, -1) == '1')
			$to = substr($this->name, 0, -1);
		else
			$to = $this->name;
		return $to;
	}

	protected function default_fk() {
		$pk = array_values($this->from->pk);
		return array_combine($pk, $pk);
	}

	protected function default_reverse() {
		return $this->from->name.'1';
	}

	public function cardinality() {
		return self::SINGLE;
	}
}