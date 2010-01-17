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
	}

	abstract protected function default_fk1();
	abstract protected function default_pivot();
	abstract protected function default_fk2();
}