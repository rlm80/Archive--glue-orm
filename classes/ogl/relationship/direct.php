<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class OGL_Relationship_Direct extends OGL_Relationship {
	protected $fk;

	protected function __construct($from, $name) {
		parent::__construct($from, $name);
		if ( ! isset($this->fk))
			$this->fk = $this->default_fk();
	}
	
	abstract protected function default_fk();
}