<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Sets maintain a numerically indexed (from 0 to max without holes), sorted,
 * distinct list of object references.

 * Note : the idea for this to be done efficiently is to always delay sorting
 *        and reindexing to the last possible time (that is, when the collection
 *        of objects is actually accessed) rather than doing it everytime the
 *        set is modified.
 *
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Set implements Iterator, Countable, ArrayAccess {
	protected $sort			= array();	// Current sort criteria
	protected $sort_stack	= array();	// History of past sort criteria
	protected $objects		= array();	// Objects currently in the set, indexed by hash.
	protected $sorted		= true;		// Whether or not $this->objects is sorted according to current sort criteria.
	protected $indexes		= array();	// Same content as $this->objects and ordered in the same way, but numerically
										// indexed from 0 to max.
	protected $indexed		= true;		// Whether or not $this->indexes is currently in sync with $this->objects
										// and correctly indexed.

	// Sets current sort criteria.
	public function sort($criteria = null) {
		// Compute sort :
		if ( ! isset($criteria) || trim($criteria) === '') // No criteria ? No sort :
			$sort = null;
		else {
			// Parse criteria  :
			$sort = array();
			$criteria = preg_replace('/\s+/', ' ', $criteria);
			$criteria = explode(',', $criteria);
			foreach($criteria as $c) {
				$parts	= explode(' ', trim($c));
				$field	= $parts[0];
				$order	= ((! isset($parts[1])) || strtolower(substr($parts[1], 0, 1)) === 'a') ? +1 : -1;
				$sort[$field] = $order;
			}
		}

		// Put old sort on stack :
		$this->sort_stack[] = $this->sort;

		// Set new sort :
		$this->set_sort($sort);

		// Return $this for chainability :
		return $this;
	}

	// Returns sort criteria to its previous value :
	public function popsort() {
		// Stack is empty ? Exception.
		if (empty($this->sort_stack))
			throw new Kohana_Exception("You called unsort() one time too many in a Glue_Set. There is no previous sort criteria to restore");

		// Set new sort :
		$this->set_sort(array_pop($this->sort_stack));
	}

	// Updates the $this->sort property and takes care of setting the right value for $this->sorted
	protected function set_sort($sort) {
		// Get old sort criteria :
		$prev_criteria = $this->criteria();

		// Set new sort :
		$this->sort = $sort;

		// Get new sort criteria :
		$cur_criteria = $this->criteria();

		// Is set currently sorted ?
		if ($cur_criteria = '' || count($this) === 0)
			// No sort or no object => set is sorted :
			$this->sorted = true;
		elseif (strlen($cur_criteria) > strlen($prev_criteria) || substr($prev_criteria, 0, strlen($cur_criteria) !== $cur_criteria))
			// Sort different or more refined than previous one => set may not be sorted anymore :
			$this->sorted = false;
		// Otherwise, current value of $this->sort is inherited from previous sort.
	}


	// Standardized current sort criteria.
	public function criteria() {
		// No sort ? Return empty string :
		if (empty($this->sort)) return '';

		// Convert current sort to string :
		foreach($this->sort as $field => $order)
			$arr[] = $field . ($order > 0 ? ' ASC' : ' DESC');
		return implode(', ', $arr);
	}

	// Replaces current set of objects with the ones passed as parameter(s).
	public function set() {
		$args = func_get_args();
		if (count($args) === 0) {
			// Reset :
			$this->objects	= array();
			$this->indexes	= array();

			// Signal sorted and indexed :
			$this->sorted	= true;
			$this->indexed	= true;
		}
		else {
			// Set objects :
			$this->objects = self::reduce($args);

			// Signal not sorted and not indexed :
			$this->sorted	= (! empty($this->sort)) ? false : true;
			$this->indexed	= false;
		}

		// Return $this for chainability :
		return $this;
	}

	// Adds objects passed as parameter(s) to current list of objects.
	public function add() {
		$args = func_get_args();
		if (count($args) > 0) {
			// Get hash => object mapping of objects to be added :
			$hashes = self::reduce($args);

			// Remove objects already in the set :
			$hashes = array_diff_key($hashes, $this->objects);

			// Add new objects to set :
			foreach($hashes as $hash => $object) {
				$this->objects[$hash] = $object;
				if ($this->indexed) $this->indexes[] = $object;
			}

			// Signal not sorted :
			$this->sorted = (! empty($this->sort)) ? false : true;
		}

		// Return $this for chainability :
		return $this;
	}

	// Removes objects passed as parameter(s) from current list of objects.
	public function remove() {
		$args = func_get_args();
		if (count($args) > 0) {
			// Get hash => object mapping of objects to be removed :
			$hashes = self::reduce($args);

			// Keep only objects that actually belong to the set :
			$hashes = array_intersect_key($hashes, $this->objects);

			// Remove objects :
			foreach($hashes as $hash => $object)
				unset($this->objects[$hash]);

			// Signal not indexed :
			$this->indexed = false;
		}

		// Return $this for chainability :
		return $this;
	}

	// Whether or not current set contains object $object.
	public function has($object) {
		return isset($this->objects[spl_object_hash($object)]);
	}

	// Returns set content as a numerically indexed array, preserving the current ordering of objects.
	public function as_array() {
		if ( ! $this->sorted)	$this->dosort();
		if ( ! $this->indexed)	$this->reindex();
		return $this->indexes;
	}

	// Returns an array indexed by entity names. Each item is a Glue_Set containing
	// all objects in the current set belonging to the entity in the key.
	public function entities() {
		// Group objects by entity :
		$entities = array();
		foreach($this->objects as $obj)
			$entities[$obj->glue_entity()][] = $obj;

		// Turn arrays into sets :
		$sets = array();
		foreach($entities as $entity => $array)
			$sets[$entity]	= glue::set($array);

		return $sets;
	}

	// Deletes objects in the set from the database.
	public function delete() {
		foreach($this->entities() as $entity_name => $set)
			glue::entity($entity_name)->object_delete($set);

		// Return $this for chainability :
		return $this;
	}

	// Updates database for all objects in the set.
	public function update() {
		foreach($this->entities() as $entity_name => $set)
			glue::entity($entity_name)->object_update($set);

		// Return $this for chainability :
		return $this;
	}

	// Updates database for all objects in the set.
	public function insert() {
		foreach($this->entities() as $entity_name => $set)
			glue::entity($entity_name)->object_insert($set);

		// Return $this for chainability :
		return $this;
	}

	// Sorts the objects according to current sort criteria if there is any.
	protected function dosort() {
		if ( ! empty($this->sort)) {
			uasort($this->objects, array($this, 'compare'));
			$this->indexed = false;
		}
		$this->sorted = true;
	}

	// Rebuild indexes array :
	protected function reindex() {
		$this->indexes = array_values($this->objects);
		$this->indexed = true;
	}

	// Compares two objects according to current sort criteria.
	protected function compare($a, $b) {
        foreach($this->sort as $field => $order) {
        	// Is $field a callback ?
        	if (strpos($field, '::') !== false)
        		$cmp = call_user_func($field, $a, $b);
        	else {
				// Get values of $field :
				$vala = method_exists($a, 'glue_get') ? $a->glue_get($field) : $a->$field;
				$valb = method_exists($b, 'glue_get') ? $b->glue_get($field) : $b->$field;
	
				// Compare field value in $a and $b :
				if ($vala < $valb)
					$cmp = -1;
				elseif ($vala > $valb)
					$cmp = +1;
				else
					$cmp = 0;
        	}

			// Reverses comparaison result according to $order if necessary :
			$cmp *= $order;

			// As soon as we find a difference between $a and $b it's over :
            if ($cmp !== 0) return $cmp;
        }

        return 0;
    }

	protected static function reduce($array) {
		$objects = array();
		foreach ($array as $item) {
			if ($item instanceof Glue_Set)
				$objects = array_merge($objects, $item->objects);
			elseif (is_object($item))
				$objects[spl_object_hash($item)] = $item;
			elseif (is_array($item))
				$objects = array_merge($objects, self::reduce($item));
			else
				throw new Kohana_Exception("Unexpected item encountered while creating a set of objects.");
		}
		return $objects;
	}

	// Iterator, Countable, ArrayAccess :
	public function rewind() {
		if ( ! $this->sorted) $this->dosort();
		reset($this->objects);
	}
    public function current() {
		if ( ! $this->sorted) $this->dosort();
		return current($this->objects);
	}
    public function key() {
		if (! $this->sorted) $this->dosort();
		return key($this->objects);
	}
    public function next() {
		if ( ! $this->sorted) $this->dosort();
		return next($this->objects);
	}
    public function valid() {
		return $this->current() !== false;
	}
    public function count() {
		return count($this->objects);
	}
    public function offsetExists($offset) {
		if ( ! $this->sorted)	$this->dosort();
		if ( ! $this->indexed)	$this->reindex();
		return isset($this->indexes[$offset]);
	}
    public function offsetGet($offset) {
		if ( ! $this->sorted)	$this->dosort();
		if ( ! $this->indexed)	$this->reindex();
		return isset($this->indexes[$offset]) ? $this->indexes[$offset] : null;
	}
	public function offsetSet($offset, $value) {
		if ( ! isset($offset)) $this->add($value);
		throw new Kohana_Exception("You may only use add, remove, set and the [] operator without parameter to modify the content of a set.");
	}
	public function offsetUnset($offset) {
		throw new Kohana_Exception("You may only use add, remove, set and the [] operator without parameter to modify the content of a set.");
	}
}
