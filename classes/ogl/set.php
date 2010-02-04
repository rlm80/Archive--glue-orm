<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * A common feature of all OGL queries is the need for defining and retrieving sets
 * of entity instances. For example the query :
 * ogl::load('users u')->with('u.postS p')->execute()
 * defines two sets :
 * - 'u' : set of users,
 * - 'p' : set of all posts related to users in u by the relationship 'postS'.
 */

class OGL_Set {
	public $name;
	public $entity;
	public $objects = array();
	public $commands = array();
	public $root_command;

	public function  __construct($name, $entity) {
		$this->name		= $name;
		$this->entity	= $entity;
	}

	public function init_query($query) {
		$this->entity->init($query, $alias);
	}

	public function exec_query($query) {
		$cpk = count($this->entity->pk);
		if ($cpk === 1) {
			// Use only one query :
			$pkvals = array_map('array_pop', $this->get_pkvals());
			$result = $query->param(':_pks', $pkvals)->execute()->as_array();
		}
		else {
			// Use one query for each object in src_set and aggregate results :
			$result = array();
			foreach(get_pkvals() as $pkval) {
				foreach($pkval as $f => $val)
					$query->param( ':_'.$f, $val);
				$rows = $query->execute()->as_array();
				if (count($rows) >= 1)
					array_merge($result, $rows);
			}
		}
		return $result;
	}

	protected function get_pkvals() {
		return array_map(array($this->entity, 'get_pk'), $this->objects);
	}
}