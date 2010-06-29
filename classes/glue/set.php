<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Set implements Iterator, Countable, ArrayAccess {
	protected $name;
	protected $sort;
	protected $objects = array();

	public function  __construct($name = null) {
		$this->name	= $name;
	}

	public function name() {
		return $this->name;
	}

	// Sorts the objects according to current sort criteria.
	public function dosort() {
		if (isset($this->sort))
			usort($this->objects, array($this, 'cmp'));

		return $this;
	}

	// Sets current sort criteria and sorts the objects.
	public function sort($criteria) {
		// Parse sort clause and set current sort :
		$this->sort = array();
		$criteria = preg_replace('/\s+/', ' ', $criteria);
		$criteria = explode(',', $criteria);
		foreach($criteria as $c) {
			$parts	= explode(' ', trim($c));
			$field	= $parts[0];
			$order	= ((! isset($parts[1])) || strtolower(substr($parts[1], 0, 1)) === 'a') ? +1 : -1;
			$this->sort[$field] = $order;
		}

		// Sort objects :
		$this->dosort();

		return $this;
	}

	// Compares two objects according to current sort criteria.
	protected function cmp($a, $b) {
        foreach($this->sort as $field => $order) {
			// Get values of $field :
			$vala = $a->glue_get($field);
			$valb = $b->glue_get($field);

			// Compare field for $a with $b :
			if ($vala < $valb)
				$cmp = -1;
			elseif ($vala > $valb)
				$cmp = +1;
			else
				$cmp = 0;

			// Change sign according to $order :
			$cmp *= $order;

			// $a <> $b ? It's over.
            if ($cmp !== 0) return $cmp;
        }
		
        return 0;
    }

	public function delete() {
		$this->entity->object_delete($this->objects);
		
		return $this;
	}

	public function update() {
		$args = func_get_args();
		if ( ! isset($args[0]))
			$fields = $this->entity->fields_all();
		else {
			if ( ! is_array($args[0]))
				$fields = $args;
			else
				$fields = $args[0];
		}
		$this->entity->update($this->objects, $fields);

		return $this;
	}

	public function set_objects($objects) {
		$this->objects = $objects;
		$this->dosort();

		return $this;
	}

	public function as_array() {
		return $this->objects;
	}

	public function debug() {
		$parts = explode('_', $this->name);
		array_pop($parts);
		$entity_name = implode('_', $parts);
		return View::factory('glue_set')
			->set('name', $this->name)
			->set('entity', $entity_name);
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
