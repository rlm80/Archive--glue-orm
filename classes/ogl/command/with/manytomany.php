<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_ManyToMany extends OGL_Command_With {
	protected $pivot_fields;

	public function  __construct($relationship, $src_set, $trg_set, $trg_fields, $pivot_fields) {
		parent::__construct($relationship, $src_set, $trg_set, $trg_fields);
		$this->pivot_fields	= $pivot_fields;
	}

	protected function is_root() {
		return true;
	}

	protected function query_contrib($query) {
		parent::query_contrib($query);
		$pivot_alias = OGL_Relationship_ManyToMany::pivot_alias($this->src_set->name, $this->trg_set->name);
		$this->relationship->pivot()->add_fields($query, $this->pivot_fields, $pivot_alias);
	}

	protected function load_objects(&$result) {
		parent::load_objects($result);
		$this->relationship->pivot()->get_objects($result, OGL_Relationship_ManyToMany::pivot_alias($this->src_set->name, $this->trg_set->name));
	}

	protected function load_relationships(&$result) {
		foreach($result as $row) {
			$src = $row[$this->src_set->name.':__object'];
			$piv = $row[OGL_Relationship_ManyToMany::pivot_alias($this->src_set->name, $this->trg_set->name).':__object'];
			$trg = $row[$this->trg_set->name.':__object'];
			$this->relationship->relate(array($src, $piv, $trg));
			$this->relationship->reverse()->relate(array($trg, $piv, $src));
		}
	}
}