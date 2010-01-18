<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_OneToMany extends OGL_Relationship_Direct {
	protected function default_to() {
		if (substr($this->name, -1) == 'S')
			$to = substr($this->name, 0, -1);
		else
			$to = inflector::singular($this->name);
		return $to;
	}

	protected function default_fk() {
		return $this->from->default_fk;
	}

	protected function default_reverse() {
		return $this->from->name;
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

	public function cardinality() {
		return self::MULTIPLE;
	}
}