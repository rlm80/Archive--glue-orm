<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * A common feature of all OGL queries is the need for defining and retrieving sets
 * of entity instances. For example the query :
 * ogl::load('users u')->with('u.postS p')->execute()
 * defines two sets :
 * - 'u' : set of users,
 * - 'p' : set of all posts related to users in u by the relationship 'postS'.
 */

class OGL_Set implements Iterator, Countable {
	public $name;
	public $entity;
	protected $sort;
	protected $objects = array();
	public $commands = array();
	public $root_command;

	public function  __construct($name, $entity) {
		$this->name		= $name;
		$this->entity	= $entity;
	}

	public function sort($sort) {
		$this->sort = $sort;
		$this->do_sort();
	}

	public function delete() {
		$this->entity->delete($this->objects);
	}
	
	protected function do_sort() {
		if (isset($this->sort))
			$this->entity->sort($this->objects, $this->sort);
	}

	public function set_objects($objects) {
		$this->objects = $objects;
		$this->do_sort();
	}

	public function as_array() {
		return $this->objects;
	}

	// Iterator, Countable :
	public function rewind()	{reset($this->objects);				}
    public function current()	{return current($this->objects);	}
    public function key()		{return key($this->objects);		}
    public function next()		{return next($this->objects);		}
    public function valid()		{return $this->current() !== false;	}
    public function count()		{return count($this->objects);		}
}