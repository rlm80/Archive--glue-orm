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
		if ( ! isset($this->property))	$this->property	= $this->default_property();
		if ( ! isset($this->reverse))	$this->reverse	= $this->default_reverse();
		if ( ! isset($this->multiple))	$this->multiple	= $this->default_multiple();
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
	}

	// Getters :
	public function to()		{	return OGL_Entity::get($this->to);		}
	public function from()		{	return OGL_Entity::get($this->from);	}
	public function reverse()	{	return OGL_Relationship::get($this->to, $this->reverse); }
	public function multiple()	{	return $this->multiple;	}

	protected function default_to() {
		switch (substr($this->name, -1)) {
			case 'Z': case 'S': case '1': $to = substr($this->name, 0, -1); break;
			default : $to = $this->name;
		}
		return $to;
	}

	protected function default_reverse() {
		switch (substr($this->name, -1)) {
			case 'Z': $reverse = $this->from.'Z';	break;
			case 'S': $reverse = $this->from;		break;
			case '1': $reverse = $this->from.'1';	break;
			default : $reverse = $this->from.'S';
		}
		return $reverse;
	}

	protected function default_property() {
		return $this->name;
	}

	protected function default_multiple() {
		switch (substr($this->name, -1)) {
			case 'Z': case 'S': $multiple = true; break;
			default : $multiple = false;
		}
		return $multiple;
	}

	protected function default_mapping() {
		$mapping = array();
		switch (substr($this->name, -1)) {
			case 'Z':
				$pivot = ($this->from < $this->to) ? $this->from.'2'.$this->to : $this->to.'2'.$this->from;
				foreach($this->from()->fk() as $src => $trg) $mapping[$src] = $pivot.'.'.$trg;
				foreach($this->to()->fk()   as $trg => $src) $mapping[$pivot.'.'.$src] = $trg;
				break;
			case 'S':
				$mapping = $this->from()->fk();
				break;
			case '1':
				$pk = array_values($this->from()->pk());
				$mapping = array_combine($pk, $pk);
				break;
			default :
				$mapping = array_flip($this->to()->fk());
		}
		return $mapping;
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
			$trg_entity->query_join($query, $trg_alias, $conds, $type);
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