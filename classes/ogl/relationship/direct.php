<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship_Direct extends OGL_Relationship {
	protected $fk;

	protected function __construct($from, $name) {
		parent::__construct($from, $name);
		if ( ! isset($this->fk)) $this->fk = $this->default_fk();
	}

	public function add_joins($query, $src_alias, $trg_alias) {
		self::join($query, $src_alias, $this->from, $trg_alias, $this->to, $this->fk);
	}
	
	protected function default_fk() {
		switch (substr($this->name, -1)) {
			case 'S': $fk = $this->from->default_fk; break;
			case '1': $pk = array_values($this->from->pk); $fk = array_combine($pk, $pk); break;
			default : $fk = array_flip($this->to->default_fk);
		}
		return $fk;
	}
}