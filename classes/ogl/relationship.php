<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Relationship {
	// Relationships cache :
	static protected $relationships = array();

	// Properties that may NOT be set in children classes :
	protected $from;
	protected $name;

	// Properties that may be set in children classes :
	protected $to;
	protected $property;
	protected $reverse;
	protected $multiple;
	protected $mapping;

	protected function __construct($from, $name) {
		// Set properties :
		$this->from = $from;
		$this->name = $name;

		// Fill in any missing properties with default values based on $from and $name :
		if ( ! isset($this->to))		$this->to		= $this->default_to();
		if ( ! isset($this->reverse))	$this->reverse	= $this->default_reverse();
		if ( ! isset($this->mapping))	$this->mapping	= $this->default_mapping();

		// Turn mapping into something easier to work with :
		$new = array();
		foreach($this->mapping as $src => $trg) {
			// Add missing entity prefixes :
			if (strpos($src, '.') === FALSE) $src = $this->from . '.' . $src;
			if (strpos($trg, '.') === FALSE) $trg = $this->to   . '.' . $trg;

			// Add data to new mapping array :
			list($trg_entity, $trg_field) = explode('.', $trg);
			list($src_entity, $src_field) = explode('.', $src);
			$new[$trg_entity][$src_entity][$trg_field] = $src_field;
		}
		$this->mapping = $new;

		// Keep filling in missing properties with default values :
		if ( ! isset($this->multiple))	$this->multiple	= $this->default_multiple();
		if ( ! isset($this->property))	$this->property	= $this->default_property();
	}

	// Getters :
	public function reverse()	{	return OGL_Relationship::get($this->to, $this->reverse); }
	public function to()		{	return OGL_Entity::get($this->to);		}
	public function from()		{	return OGL_Entity::get($this->from);	}
	public function multiple()	{	return $this->multiple;					}

	protected function default_to() {
		return Inflector::singular($this->name);
	}

	protected function default_reverse() {
		return $this->from;
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
			$pivot		= OGL::entity($pivot); // Will raise exception if no such entity.
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

	protected function default_multiple() {
		foreach($this->mapping as $trg_entity => $arr1) {
			// Get trg fields :
			$trg_fields = array();
			foreach($arr1 as $src_entity => $arr2)
				$trg_fields = array_merge($trg_fields, array_keys($arr2));

			// Make sure they match pk of trg entity :
			$pk = OGL::entity($trg_entity)->pk();
			if (count($pk) !== count($trg_fields) || count(array_diff($trg_fields, $pk)) !== 0)
				return true;
		}

		return false;
	}

	protected function default_property() {
		return $this->name;
	}

	public function join($query, $from_alias, $to_alias, $type = 'LEFT') {
		$prefix = $from_alias.'__'.$to_alias.'__';

		// Loop on target entities :
		foreach($this->mapping as $trg_entity_name => $data1) {
			// Get entity object and build alias :
			$trg_entity = OGL_Entity::get($trg_entity_name);
			$trg_alias	= ($trg_entity_name === $this->to) ? $to_alias : $prefix.$trg_entity_name;

			// Loop on source entities :
			$conds = array();
			foreach($data1 as $src_entity_name => $data2) {
				// Get entity object and build alias :
				$src_entity = OGL_Entity::get($src_entity_name);
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
			$property = $this->property;
			if ($this->multiple && ! isset($src->$property))
				$src->$property = array();
			if (isset($trg)) {
				if ($this->multiple) {
					$p =& $src->$property;
					$p[spl_object_hash($trg)] = $trg;
				}
				else
					$src->$property = $trg;
			}
		}
	}

	// Lazy loads a relationship object, stores it in cache, and returns it :
	static public function get($entity_name, $name) {
		if( ! isset(self::$relationships[$entity_name]))
			self::$relationships[$entity_name] = array();
		if( ! isset(self::$relationships[$entity_name][$name]))
			self::$relationships[$entity_name][$name] = self::create($entity_name, $name);
		return self::$relationships[$entity_name][$name];
	}

	// Chooses the right relationship class to use, based on the name of the entity,
	// the name and the available classes.
	static protected function create($entity_name, $name) {
		$class = 'OGL_Relationship_'.ucfirst($entity_name).'_'.ucfirst($name);
		if (class_exists($class))
			$relationship = new $class($entity_name, $name);
		else
			$relationship = new self($entity_name, $name);
		return $relationship;
	}
}