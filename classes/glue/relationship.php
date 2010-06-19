<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Relationship {
	// Constants :
	const MANY_TO_MANY	= 1;
	const MANY_TO_ONE	= 2;
	const ONE_TO_MANY	= 3;
	const ONE_TO_ONE	= 4;

	// Relationships cache :
	static protected $relationships = array();

	// Properties that may NOT be set in children classes :
	protected $from;
	protected $name;
	protected $joins;

	// Properties that may be set in children classes :
	protected $to;
	protected $mapping;
	protected $type;
	protected $reverse;
	protected $property;	

	protected function __construct($from, $name) {
		// Set properties :
		$this->from = $from;
		$this->name = $name;

		// Fill in any missing properties with default values based on $from and $name :
		if ( ! isset($this->to))		$this->to		= $this->default_to();
		if ( ! isset($this->mapping))	$this->mapping	= $this->default_mapping();

		// Add missing entity prefixes :
		$new = array();
		foreach($this->mapping as $src => $trg) {
			if (strpos($src, '.') === FALSE) $src = $this->from . '.' . $src;
			if (strpos($trg, '.') === FALSE) $trg = $this->to   . '.' . $trg;
			$new[$src] = $trg;
		}
		$this->mapping = $new;

		// Turn mapping into something easier to work with :
		foreach($this->mapping as $src => $trg) {
			list($trg_entity, $trg_field) = explode('.', $trg);
			list($src_entity, $src_field) = explode('.', $src);
			$this->joins[$trg_entity][$src_entity][$trg_field] = $src_field;
		}

		// Keep filling in missing properties with default values :
		if ( ! isset($this->type))		$this->type		= $this->default_type();
		if ( ! isset($this->reverse))	$this->reverse	= $this->default_reverse();
		if ( ! isset($this->property))	$this->property	= $this->default_property();
	}

	// Getters :
	public function reverse()	{	return Glue_Relationship::get($this->to, $this->reverse); }
	public function to()		{	return Glue_Entity::get($this->to);		}
	public function from()		{	return Glue_Entity::get($this->from);	}
	public function type()		{	return $this->type;						}
	public function name()		{	return $this->name;						}

	protected function default_to() {
		return $this->name;
	}

	protected function default_mapping() {
		// Get entity mappers :
		$from	= $this->from();
		$to		= $this->to();

		// Fk of source entity exists in target entity ? => one to many
		$fk		= $from->fk();
		$errors	= $to->fields_validate(array_values($fk));
		if (count($errors) === 0)
			return $fk;

		// Fk of target entity exists in source entity ? => many to one
		$fk		= $to->fk();
		$errors	= $from->fields_validate(array_values($fk));
		if (count($errors) === 0)
			return array_flip($fk);

		// Associative entity ? => many to many
		try {
			$pivot		= ($this->from < $this->to) ? $this->from.'2'.$this->to : $this->to.'2'.$this->from;
			$pivot		= glue::entity($pivot); // Will raise exception if no such entity.
			$from_fk	= $from->fk();
			$to_fk		= $to->fk();
			$errors		= $pivot->fields_validate(array_merge(array_values($from_fk), array_values($to_fk)));
			if (count($errors) === 0) {
				foreach($from_fk as $src => $trg) $mapping[$src] = $pivot->name().'.'.$trg;
				foreach($to_fk   as $trg => $src) $mapping[$pivot->name().'.'.$src] = $trg;
				return $mapping;
			}
		} catch(Exception $e) { /* means there is no such associative entity */ }

		// Matching pk ? => one to one
		$from_pk	= $from->pk();
		$to_pk		= $to->pk();
		if (count($from_pk) === count($to_pk) && count(array_diff($from_pk, $to_pk)) === 0)
			return array_combine($from_pk, $from_pk);

		// Otherwise error.
		throw new Kohana_Exception("Impossible to guess field mapping from entity '".$this->from."' to entity'".$this->to."'");
	}

	protected function default_type() {
		// Guess src and trg cardinality from pk :
		$src_multiple = false;
		$trg_multiple = false;
		foreach($this->joins as $trg_entity => $arr1) {
			foreach($arr1 as $src_entity => $arr2) {
				// Check trg cardinality :
				if ( ! $trg_multiple) {
					$fields	= array_keys($arr2);
					$pk		= glue::entity($trg_entity)->pk();
					if (count($pk) !== count($fields) || count(array_diff($fields, $pk)) !== 0)
						$trg_multiple = true;
				}
			
				// Check src cardinality :
				if ( ! $src_multiple) {
					$fields	= array_values($arr2);
					$pk		= glue::entity($src_entity)->pk();
					if (count($pk) !== count($fields) || count(array_diff($fields, $pk)) !== 0)
						$src_multiple = true;
				}
			}
		}

		// Compute relationship type :
		if ($src_multiple) {
			if ($trg_multiple)
				$type = self::MANY_TO_MANY;
			else
				$type = self::MANY_TO_ONE;
		}
		else {
			if ($trg_multiple)
				$type = self::ONE_TO_MANY;
			else
				$type = self::ONE_TO_ONE;
		}

		return $type;
	}

	protected function default_reverse() {
		return $this->from;
	}

	protected function default_property() {
		if ($this->type === self::MANY_TO_MANY || $this->type === self::ONE_TO_MANY)
			return Inflector::plural($this->name);
		return $this->name;
	}

	public function join($query, $from_alias, $to_alias, $type = 'LEFT') {
		$prefix = $from_alias.'__'.$to_alias.'__';

		// Loop on target entities :
		foreach($this->joins as $trg_entity_name => $data1) {
			// Get entity object and build alias :
			$trg_entity = Glue_Entity::get($trg_entity_name);
			$trg_alias	= ($trg_entity_name === $this->to) ? $to_alias : $prefix.$trg_entity_name;

			// Loop on source entities :
			$conds = array();
			foreach($data1 as $src_entity_name => $data2) {
				// Get entity object and build alias :
				$src_entity = Glue_Entity::get($src_entity_name);
				$src_alias	= ($src_entity_name === $this->from) ? $from_alias : $prefix.$src_entity_name;

				// Loop on source entity fields :
				foreach($data2 as $trg_field => $src_field)
					$conds[] = array($trg_field, '=', $src_entity->query_field_expr($src_alias, $src_field));
			}

			// Join target entity :
			$trg_entity->query_join($query, $trg_alias, $type);
			foreach($conds as $cond) {
				list($field, $op, $expr) = $cond;
				$query->on($trg_entity->query_field_expr($trg_alias, $field), $op, $expr);
			}
		}
	}

	public function link($src, $trg) {
		if (isset($src)) {
			$multiple = ($this->type === self::MANY_TO_MANY || $this->type === self::ONE_TO_MANY);
			$property = $this->property;
			if ($multiple && ! isset($src->$property))
				$src->$property = array();
			if (isset($trg)) {
				if ($multiple) {
					$p =& $src->$property;
					$p[spl_object_hash($trg)] = $trg;
				}
				else
					$src->$property = $trg;
			}
		}
	}

	// Debug :
	public function debug() {
		return View::factory('glue_relationship')
			->set('name',			$this->name)
			->set('from',			$this->from)
			->set('to',				$this->to)
			->set('type',			$this->type)
			->set('mapping',		$this->mapping)
			->set('property',		$this->property)
			->set('reverse',		$this->reverse);
	}

	// Lazy loads a relationship object, stores it in cache, and returns it :
	static public function get($entity_name, $name) {
		$entity_name	= strtolower($entity_name);
		$name			= strtolower($name);
		if( ! isset(self::$relationships[$entity_name]))
			self::$relationships[$entity_name] = array();
		if( ! isset(self::$relationships[$entity_name][$name]))
			self::$relationships[$entity_name][$name] = self::build($entity_name, $name);
		return self::$relationships[$entity_name][$name];
	}

	// Chooses the right relationship class to use, based on the name of the entity,
	// the name and the available classes.
	static protected function build($entity_name, $name) {
		$class = 'Glue_Relationship_'.ucfirst($entity_name).'_'.ucfirst($name);
		if (class_exists($class))
			$relationship = new $class($entity_name, $name);
		else
			$relationship = new self($entity_name, $name);
		return $relationship;
	}
}