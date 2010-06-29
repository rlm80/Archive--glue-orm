<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Sets maintain an sorted, distinct list of objects.
 *
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Set implements Iterator, Countable, ArrayAccess {
	protected $name;
	protected $sort;
	protected $objects	= array();
	protected $hashes	= array();

	public function  __construct($name = null) {
		$this->name	= $name;
	}

	// Getter for name :
	public function name() {
		return $this->name;
	}

	// Reset objects :
	public function reset() {
		// Load objects :
		$args = func_get_args();
		if (count($args) > 0) {
			$this->hashes = self::reduce($args);
			$this->objects = array_values($this->hashes);
		}
		else {
			$this->hashes = array();
			$this->objects = array();
		}

		// Apply sort :
		$this->dosort();

		// Return $this for chainability :
		return $this;
	}

	public function add() {
		// Add objects :
		$args = func_get_args();
		$hashes = self::reduce($args);
		//...

		// Return $this for chainability :
		return $this;
	}

	public function as_array() {
		return $this->objects;
	}

	// Sorts the objects according to current sort criteria.
	public function dosort() {
		// Do sort :
		if (isset($this->sort))
			usort($this->objects, array($this, 'cmp'));

		// Return $this for chainability :
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

		// Return $this for chainability :
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
		
		// Return $this for chainability :
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

		// Return $this for chainability :
		return $this;
	}

	public function debug() {
		$parts = explode('_', $this->name);
		array_pop($parts);
		$entity_name = implode('_', $parts);
		return View::factory('glue_set')
			->set('name', $this->name)
			->set('entity', $entity_name);
	}

	protected static function reduce($array) {
		$hashes = array();
		foreach ($array as $item) {
			if ($item instanceof Glue_Set)
				$hashes = array_merge($hashes, $item->hashes);
			elseif (is_object($item))
				$hashes[spl_object_hash($item)] = $item;
			elseif (is_array($item))
				$hashes = array_merge($hashes, self::reduce($item));
			else
				throw new Kohana_Exception("Unexpected item encountered while creating a set of objects.");
		}
		return $hashes;
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
