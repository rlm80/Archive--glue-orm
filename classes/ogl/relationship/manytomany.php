<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_ManyToMany extends OGL_Relationship {
	protected $pivot;
	protected $property2;
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
			$this->fk = $this->from()->default_fk;
		return $this->fk;
	}

	public function fk2() {
		if ( ! isset($this->fk2))
			$this->fk2 = array_flip($this->to()->default_fk);
		return $this->fk2;
	}

	public function property2() {
		if ( ! isset($this->property2))
			$this->property2 = $this->to()->name;
		return $this->property2;
	}

	public function pivot() {
		if ( ! isset($this->pivot)) {
			if ($this->from()->name < $this->to()->name)
				$this->pivot = $this->from()->name.'_'.$this->to()->name;
			else
				$this->pivot = $this->to()->name.'_'.$this->from()->name;
		}
		return OGL_Entity::get($this->pivot, array_merge(array_values($this->fk()), array_keys($this->fk2())), $this->pivot);
	}

	public function reverse() {
		if ( ! isset($this->reverse))
			$this->reverse = $this->from()->name.'Z';
		return OGL_Relationship::get($this->to()->name, $this->reverse);
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