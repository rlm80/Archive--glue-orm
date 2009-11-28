<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_OneToMany extends OGL_Command_With {
	public function is_root() {
		return true;
	}

	public function query_result($result) {
		parent::query_result($result);
	}

	public function query_contrib($query) {
		parent::query_contrib($query);
	}
}