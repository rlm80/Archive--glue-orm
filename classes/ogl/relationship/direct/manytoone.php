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

	public function load_relationships($result, $src_alias, $trg_alias)	{
		$src_key	= $src_alias.':__object';
		$trg_key	= $trg_alias.':__object';
		$property	= $this->property();
		foreach($result as $row) {
			if (isset($row[$src_key]) && isset($row[$trg_key]))
				$row[$src_key]->$property = $row[$trg_key];
		}
	}

	public function cardinality() {
		return self::SINGLE;
	}
}