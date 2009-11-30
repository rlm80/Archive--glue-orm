<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_ManyToOne extends OGL_Command_With {
	protected function is_root() {
		return false;
	}
}