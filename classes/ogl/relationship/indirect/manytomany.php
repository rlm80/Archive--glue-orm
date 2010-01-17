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

	public function add_joins($query, $src_alias, $trg_alias) {
		$pivot_alias = self::pivot_alias($src_alias, $trg_alias);
		self::join($query, $src_alias,   $this->from(),  $pivot_alias, $this->pivot(), $this->fk());
		self::join($query, $pivot_alias, $this->pivot(), $trg_alias,   $this->to(),    $this->fk2());
	}

	public static function pivot_alias($src_alias, $trg_alias) {
		if ($src_alias < $trg_alias)
			return '__'.$src_alias.'_'.$trg_alias;
		return '__'.$trg_alias.'_'.$src_alias;
	}

	public function load_relationships($result, $src_alias, $trg_alias)	{
		$src_key	= $src_alias.':__object';
		$piv_key	= self::pivot_alias($src_alias, $trg_alias).':__object';
		$trg_key	= $trg_alias.':__object';
		$property	= $this->property();
		$property2	= $this->property2();
		foreach($result as $row) {
			if (isset($row[$src_key])) $src = $row[$src_key]; else unset($src);
			if (isset($row[$piv_key])) $piv = $row[$piv_key]; else unset($piv);
			if (isset($row[$trg_key])) $trg = $row[$trg_key]; else unset($trg);
			if (isset($src) && ! isset($src->$property)) $src->$property = array();
			if (isset($src) && isset($piv) && isset($trg)) {
				$src_arr =& $src->$property;
				$src_arr[spl_object_hash($piv)] = $piv;
				$piv->$property2 = $trg;
			}
		}
	}

	public function create_command($src_set, $trg_set, $trg_fields, $pivot_fields) {
		return new OGL_Command_With_ManyToMany($this, $src_set, $trg_set, $trg_fields, $pivot_fields);
	}
}