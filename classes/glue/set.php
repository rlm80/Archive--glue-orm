<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Maintains a numerically indexed (from 0 to max without holes), iteratable,
 * sorted, distinct list of objects.
 *
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Set implements Iterator, Countable, ArrayAccess {
	protected $name;
	protected $sort;
	protected $criteria;
	protected $objects	= array();
	protected $hashes	= array();

	public function  __construct($name = null) {
		$this->name	= $name;
	}

	// Sets current sort criteria and sorts the objects.
	public function set_sort($criteria = null) {
		// Set current sort criteria :
		$this->criteria	= $criteria;

		// Set sort :
		if (empty($this->criteria)) {
			// Empty criteria ? Remove sort :
			$this->sort = null;
		}
		else {
			// Parse criteria and set current sort :
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
			$this->sort();
		}

		// Return $this for chainability :
		return $this;
	}

	// Gets current sort criteria.
	public function get_sort() {
		return $this->criteria;
	}

	// Reset sort and return current sort before reset :
	public function reset_sort() {
		$criteria = $this->criteria;
		$this->set_sort();
		return $criteria;
	}

	// Sorts the objects according to current sort criteria if there is any.
	protected function sort() {
		if (isset($this->sort)) {
			// Sort :
			usort($this->objects, array($this, 'compare'));

			// Rebuild hashes => indexes mapping :
			$this->rebuild_hashes();
		}

		// Return $this for chainability :
		return $this;
	}

	// Compares two objects according to current sort criteria.
	protected function compare($a, $b) {
        foreach($this->sort as $field => $order) {
			// Get values of $field :
			$vala = $a['object']->glue_get($field);
			$valb = $b['object']->glue_get($field);

			// Compare field for $a with $b :
			if ($vala < $valb)
				$cmp = -1;
			elseif ($vala > $valb)
				$cmp = +1;
			else
				$cmp = 0;

			// Change sign according to $order :
			$cmp *= $order;

			// As soon as we find a difference between $a and $b it's over :
            if ($cmp !== 0) return $cmp;
        }
		
        return 0;
    }

	// Reset objects :
	public function reset() {
		// Reset :
		$this->objects	= array();
		$this->hashes	= array();

		// Load objects :
		$args = func_get_args();
		if (count($args) > 0) {
			// Add objects
			$hashes = self::reduce($args);
			foreach($hashes as $hash => $object)
				$this->objects[] = array('hash' => $hash, 'object' => $object);

			// Rebuild hashes => indexes mapping :
			$this->rebuild_hashes();
		}

		// Apply sort :
		$this->sort();

		// Return $this for chainability :
		return $this;
	}

	public function add() {
		// Get hash => object mapping of objects to be added :
		$args = func_get_args();
		$hashes = self::reduce($args);

		// Remove objects already in the set :
		$hashes = array_diff_key($hashes, $this->hashes);

		// Add objects to set :
		$index = count($this->objects);
		foreach($hashes as $hash => $object) {
			$this->objects[] = array('hash' => $hash, 'object' => $object);
			$this->hashes[$hash] = $index;
			$index ++;
		}

		// Apply sort :
		$this->sort();

		// Return $this for chainability :
		return $this;
	}

	public function remove() {
		// Get hash => object mapping of objects to be removed :
		$args = func_get_args();
		$hashes = self::reduce($args);

		// Remove objects :
		$indexes = array_flip(array_intersect_key($this->hashes, $hashes));
		$this->objects = array_diff_key($this->objects, $indexes);

		// Reindex objects :
		$this->objects = array_values($this->objects);

		// Rebuild hashes => indexes mapping :
		$this->rebuild_hashes();

		// Return $this for chainability :
		return $this;
	}

	// Whether or not current set contains object $object :
	public function contains($object) {
		return isset($this->hashes[spl_object_hash($object)]);
	}

	// Rebuild hashes => index mapping from $objects array :
	protected function rebuild_hashes() {
		$this->hashes = array();
		foreach($this->objects as $index => $data)
			$this->hashes[$data['hash']] = $index;
	}

	// Returns set content as a numerically indexed array, preserving the current ordering of objects :
	public function as_array() {
		$array = array();
		foreach($this->objects as $index => $data)
			$array[] = $data['object'];
		return $array;
	}

	// Getter for name :
	public function name() {
		return $this->name;
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
	public function offsetSet($offset, $value)	{throw new Kohana_Exception("You may only use add, remove and reset to modify the content of a set.");}
    public function offsetExists($offset)		{return isset($this->objects[$offset]);}
    public function offsetUnset($offset)		{unset($this->objects[$offset]);}
    public function offsetGet($offset)			{return isset($this->objects[$offset]) ? $this->objects[$offset] : null;}
}
