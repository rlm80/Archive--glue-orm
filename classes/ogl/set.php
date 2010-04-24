<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * A common feature of all OGL queries is the need for defining and retrieving sets
 * of entity instances. For example the query :
 * ogl::load('users u')->with('u.postS p')->execute()
 * defines two sets :
 * - 'u' : set of users,
 * - 'p' : set of all posts related to users in u by the relationship 'postS'.
 */

class OGL_Set implements Iterator, Countable, ArrayAccess {
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
		$this->entity->object_delete($this->objects);
	}

	public function update() {
		$args = func_get_args();
		if ( ! isset($args[0]))
			$fields = array();
		else {
			if ( ! is_array($args[0]))
				$fields = $args;
			else
				$fields = $args[0];
		}
		$this->entity->update($this->objects, $fields);
	}
	
	protected function do_sort() {
		if (isset($this->sort))
			$this->entity->sort($this->objects, $this->sort);
	}

	public function set_objects($objects) {
		$this->objects = $objects;
		$this->do_sort();
	}

	public function entity() {
		return $this->entity;
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
	public function offsetSet($offset, $value)	{$this->objects[$offset] = $value;}
    public function offsetExists($offset)		{return isset($this->objects[$offset]);}
    public function offsetUnset($offset)		{unset($this->objects[$offset]);}
    public function offsetGet($offset)			{return isset($this->objects[$offset]) ? $this->objects[$offset] : null;}
}