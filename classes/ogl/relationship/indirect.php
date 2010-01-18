<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class OGL_Relationship_Indirect extends OGL_Relationship {
	protected $fk1;
	protected $pivot;
	protected $fk2;

	protected function __construct($from, $name) {
		parent::__construct($from, $name);
		if ( ! isset($this->fk1))		$this->fk1		= $this->default_fk1();
		if ( ! isset($this->fk2))		$this->fk2		= $this->default_fk2();
		if ( ! isset($this->pivot))		$this->pivot	= $this->default_pivot();
		$this->pivot = OGL_Entity::get($this->pivot, array_merge(array_values($this->fk1), array_keys($this->fk2)), $this->pivot);
	}

	abstract protected function default_fk1();
	abstract protected function default_pivot();
	abstract protected function default_fk2();

	public function add_joins($query, $src_alias, $trg_alias) {
		$pivot_alias = self::pivot_alias($src_alias, $trg_alias);
		self::join($query, $src_alias,   $this->from,  $pivot_alias, $this->pivot, $this->fk1);
		self::join($query, $pivot_alias, $this->pivot, $trg_alias,   $this->to,    $this->fk2);
	}

	public static function pivot_alias($src_alias, $trg_alias) {
		if ($src_alias < $trg_alias)
			return '__'.$src_alias.'_'.$trg_alias;
		return '__'.$trg_alias.'_'.$src_alias;
	}
}