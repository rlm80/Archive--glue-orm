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

	public function load_relationships($result, $src_alias, $trg_alias)	{
		$src_key	= $src_alias.':__object';
		$trg_key	= $trg_alias.':__object';
		$property	= $this->property();
		foreach($result as $row) {
			if (isset($row[$src_key]) && isset($row[$trg_key]))
				$row[$src_key]->$property = $row[$trg_key];
		}
	}

	public function create_command($src_set, $trg_set, $trg_fields, $pivot_fields) {
		return new OGL_Command_With_OneToOne($this, $src_set, $trg_set, $trg_fields);
	}
}