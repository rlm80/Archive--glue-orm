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
	protected $is_root;
	protected $name;
	protected $entity;
	public $objects	= array();
	protected $commands	= array();

	public function  __construct($name, $entity, $is_root = false) {
		$this->name		= $name;
		$this->entity	= $entity;
		$this->is_root	= $is_root;
	}

	public function entity() {
		return OGL_Entity::get($this->entity);
	}

	public function add_command($command) {
		$this->commands[] = $command;
	}

	// Returns command chain formed by all command chains starting with a child
	// command of this set.
	public function build_chain() {
		$chain = array();
		$roots = array();
		foreach($this->commands as $command) {
			list($new_chain, $new_roots) = $command->build_chain();
			$chain = array_merge($chain, $new_chain);
			$roots = array_merge($roots, $new_roots);
		}
		return array($chain, $roots);
	}

	public function init_query($query) {
		if($this->is_root)
			return;

		// Get data :
		$alias	= $this->name;
		$fields	= $this->entity->fields();
		$pk		= $this->entity->pk();

		// Add table :
		$query->from(array($this->entity->table(), $alias));

		// Add conditions to restrict it to rows belonging to current set :
		if (count($pk) === 1)
			$query->where($alias.'.'.$fields[$pk[0]]['column'], 'IN', new Database_Expression(':_pks'));
		else
			foreach($pk as $f)
				$query->where($alias.'.'.$fields[$f]['column'], '=', new Database_Expression(':_'.$f));
	}

	public function exec_query($query) {
		if($this->is_root)
			$result = $query->execute()->as_array();
		else {
			if (count($this->entity->pk()) === 1) {
				// Use only one query :
				$pks = array_map('array_pop', $this->get_pks());
				$result = $query->param(':_pks', $pks)->execute()->as_array();
			}
			else {
				// Use one query for each object in src_set and aggregate results :
				$result = array();
				foreach($this->get_pks() as $pkvals) {
					foreach($pkvals as $f => $val)
						$query->param( ':_'.$f, $val);
					$rows = $query->execute()->as_array();
					if (count($rows) >= 1)
						array_merge($result, $rows);
				}
			}
		}
		return $result;
	}

	protected function get_pks() {
		return array_map(array($this->src_set->entity, 'pk_decode'), array_keys($this->src_set->objects));
	}
}

/*
 protected $root_command;
	public function set_root_command($command) {
		$this->root_command = $command;
	}
 */