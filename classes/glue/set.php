<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Sets maintain a numerically indexed (from 0 to max without holes), sorted,
 * distinct list of objects.
 *
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Set implements Iterator, Countable, ArrayAccess {
	protected $name;					// Optional name of this set
	protected $criteria;				// Current sort criteria (e.g. 'price DESC, date ASC')
	protected $sort;					// Parsed sort criteria	used internally
	protected $objects	= array();		// Numerically indexed objects
	protected $hashes	= array();		// Mapping object hashes => indexes

	// Constructor. Consider using glue::set() instead, as it is chainable.
	public function  __construct($name = null) {
		$this->name	= $name;
	}

	// Sets current sort criteria and sorts the objects.
	public function sort($criteria = null, $must_sort = true) {
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
			if ($must_sort) $this->dosort();
		}

		// Return $this for chainability :
		return $this;
	}

	// Gets current sort criteria.
	public function get_sort() {
		return $this->criteria;
	}

	// Remove current sort criteria and return current sort criteria before reset.
	public function unsort() {
		$criteria = $this->criteria;
		$this->sort();
		return $criteria;
	}

	// Replaces current set of objects with the ones passed as parameter(s). Unicity,
	// ordering and proper indexing (0 to max without holes) are enforced automatically.
	public function set() {
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
		$this->dosort();

		// Return $this for chainability :
		return $this;
	}

	// Adds objects passed as parameter(s) to current list of objects. Unicity,
	// ordering and proper indexing (0 to max without holes) are enforced automatically.
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
		$this->dosort();

		// Return $this for chainability :
		return $this;
	}

	// Removes objects passed as parameter(s) from current list of objects. Unicity,
	// ordering and proper indexing (0 to max without holes) are enforced automatically.
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

	// Whether or not current set contains object $object.
	public function has($object) {
		return isset($this->hashes[spl_object_hash($object)]);
	}

	// Returns set content as a numerically indexed array, preserving the current ordering of objects :
	public function as_array() {
		$array = array();
		foreach($this->objects as $index => $data)
			$array[] = $data['object'];
		return $array;
	}

	// Returns an array indexed by entity names. Each item is an array containing
	// all objects in the set belonging to the entity in the key, in the order
	// they are encountered in the set.
	public function as_array_by_entity() {
		foreach($this->objects as $data) {
			$obj = $data['object'];
			$entities[$obj->glue_entity()->name()][] = $obj;
		}
		return $entities;
	}

	// Getter for name :
	public function name() {
		return $this->name;
	}

	// Deletes objects in the set from the database.
	public function delete() {
		// Classify objects by entity :
		$entities = $this->as_array_by_entity();

		// Delete objects :
		foreach($entities as $entity_name => $objects)
			glue::entity($entity_name)->object_delete($objects);
		
		// Return $this for chainability :
		return $this;
	}

	// Updates database for all objects in the set.
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

	// Sorts the objects according to current sort criteria if there is any.
	protected function dosort() {
		if (isset($this->sort)) {
			// Sort :
			usort($this->objects, array($this, 'compare'));

			// Rebuild hashes => indexes mapping :
			$this->rebuild_hashes();
		}
	}

	// Compares two objects according to current sort criteria.
	protected function compare($a, $b) {
        foreach($this->sort as $field => $order) {
			// Get values of $field :
			$vala = method_exists($a, 'glue_get') ? $a['object']->glue_get($field) : $a['object']->$field;
			$valb = method_exists($b, 'glue_get') ? $b['object']->glue_get($field) : $b['object']->$field;

			// Compare field value in $a and $b :
			if ($vala < $valb)
				$cmp = -1;
			elseif ($vala > $valb)
				$cmp = +1;
			else
				$cmp = 0;

			// Reverses comparaison result according to $order if necessary :
			$cmp *= $order;

			// As soon as we find a difference between $a and $b it's over :
            if ($cmp !== 0) return $cmp;
        }

        return 0;
    }

	// Rebuild hashes => index mapping from $objects array :
	protected function rebuild_hashes() {
		$this->hashes = array();
		foreach($this->objects as $index => $data)
			$this->hashes[$data['hash']] = $index;
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

	public function debug() {
		$parts = explode('_', $this->name);
		array_pop($parts);
		$entity_name = implode('_', $parts);
		return View::factory('glue_set')
			->set('name', $this->name)
			->set('entity', $entity_name);
	}

	// Iterator, Countable :
	public function rewind() {
		reset($this->objects);
	}
    public function current() {
		$data = current($this->objects);
		return $data['object'];
	}
    public function key() {
		return key($this->objects);
	}
    public function next() {
		$data = next($this->objects);
		return $data['object'];
	}
    public function valid() {
		return $this->current() !== false;
	}
    public function count() {
		return count($this->objects);
	}
    public function offsetExists($offset) {
		return isset($this->objects[$offset]);
	}
    public function offsetGet($offset) {
		return isset($this->objects[$offset]) ? $this->objects[$offset]['object'] : null;
	}
	public function offsetSet($offset, $value) {
		throw new Kohana_Exception("You may only use add, remove and reset to modify the content of a set.");
	}
	public function offsetUnset($offset) {
		throw new Kohana_Exception("You may only use add, remove and reset to modify the content of a set.");
	}

	// Chainable constructor :
	static public function factory() {
		// Get hash => object mapping of objects :
		$args = func_get_args();
		$hashes = self::reduce($args);

		// Create set :
		$set = new self;
		$set->set($hashes);

		return $set;
	}
}
