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
	protected $name;
	protected $entity;
	public $pks	= array();
	public $commands = array();
	public $root_command;

	public function  __construct($name, $entity) {
		$this->name		= $name;
		$this->entity	= $entity;
	}

	public function init_query($query) {
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
		$cpk = count($this->entity->pk());
		if ($cpk === 1) {
			// Use only one query :
			$pkvals = array_map('array_pop', array_map(array('OGL_Entity', 'pk_decode'), $this->pks));
			$result = $query->param(':_pks', $pkvals)->execute()->as_array();
		}
		else {
			// Use one query for each object in src_set and aggregate results :
			$pk		= $this->entity->pk();
			$pkvals = array_map(array('OGL_Entity', 'pk_decode'), $this->pks);
			$result = array();
			foreach($pkvals as $pkval) {
				for($i = 0; $i < $cpk; $i++)
					$query->param( ':_'.$pk[$i], $pkval[$i]);
				$rows = $query->execute()->as_array();
				if (count($rows) >= 1)
					array_merge($result, $rows);
			}
		}
		return $result;
	}
}