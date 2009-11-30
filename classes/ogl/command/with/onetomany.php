<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_OneToMany extends OGL_Command_With {
	protected function is_root() {
		return true;
	}
}