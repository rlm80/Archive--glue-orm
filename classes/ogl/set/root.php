<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Set_Root extends OGL_Set {
	public function  __construct() {
		parent::__construct('__root', null);
	}

	public function exec_query($query) {
		return $query->execute()->as_array();
	}

	public function init_query($query) {}
	public function load_objects(&$result) {}
}