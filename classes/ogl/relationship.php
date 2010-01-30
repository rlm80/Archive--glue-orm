<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class OGL_Relationship {
	// Relationships cache :
	static protected $relationships = array();

	// Properties that may NOT be set in children classes :
	public $from;
	public $name;

	// Properties that may be set in children classes :
	public $to;
	public $property;
	public $reverse;
	public $multiple;
	public $mapping;

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
		$this->adapt_mapping();

		// Turn properties into objects where appropriate :
		$this->reverse	= OGL_Relationship::get($this->to, $this->reverse);
		$this->from		= OGL_Entity::get($this->from);
		$this->to		= OGL_Entity::get($this->to);
	}

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
		$from	 = OGL_entity::get($this->from);
		$to		 = OGL_Entity::get($this->to);
		switch (substr($this->name, -1)) {
			case 'Z':
				$pivot = ($this->from < $this->to) ? $this->from.'2'.$this->to : $this->to.'2'.$this->from;
				foreach($from->fk as $src => $trg) $mapping[$src] = $pivot.'.'.$trg;
				foreach($to->fk   as $trg => $src) $mapping[$pivot.'.'.$src] = $trg;
				break;
			case 'S':
				$mapping = $from->fk;
				break;
			case '1':
				$pk = array_values($from->pk);
				$mapping = array_combine($pk, $pk);
				break;
			default :
				$mapping = array_flip($to->fk);
		}
		return $mapping;
	}

	public function add_joins($query, $src_alias, $trg_alias) {
		$prefix = $src_alias.'__'.$trg_alias;
		foreach($this->mapping as $trg_entity => $trg_fields) {
			// Get entity object, fields and table :
			$trg_entity = OGL_Entity::get($trg_entity);
			$trg_fields	= $trg_entity->fields;
			$trg_alias	= $prefix.'__'.$trg_entity->name;

			// Add table to from :
			$query->join(array($trg_entity->table, $trg_alias), 'LEFT');

			// Add required column mappings to on :
			foreach($trg_fields as $trg_field => $arr) {
				list($src_entity, $src_field) = $arr;
				
				// Get entity object, fields and table :
				$src_entity = OGL_Entity::get($src_entity);
				$src_fields	= $src_entity->fields;
				$src_alias	= $prefix.'__'.$src_entity->name;

				// Add columns mapping to on :
				$query->on($trg_alias.'.'.$trg_fields[$trg_field]['column'], '=', $src_alias.'.'.$src_fields[$src_field]['column']);
			}
		}
	}

	public function load_relationships($result, $src_alias, $trg_alias)	{
		$src_key	= $src_alias.':__object';
		$trg_key	= $trg_alias.':__object';
		$property	= $this->property;
		foreach($result as $row) {
			if (isset($row[$src_key])) {
				$src = $row[$src_key];
				if ($this->multiple && ! isset($src->$property))
					$src->$property = array();
				if (isset($row[$trg_key])) {
					$trg = $row[$trg_key];
					if ($this->multiple)
						$src->$property[spl_object_hash($trg)] = $trg;
					else
						$src->$property = $trg;
				}
			}
		}
	}
	
	private function adapt_mapping() {
		// Build new mapping array :
		$new = array();
		foreach($this->mapping as $src => $trg) {
			// Add missing entity prefixes :
			if (strpos('.', $src) === FALSE) $src = $this->from . '.' . $src;
			if (strpos('.', $trg) === FALSE) $trg = $this->to   . '.' . $trg;

			// Add data to new mapping array :
			list($trg_entity, $trg_field) = explode('.', $trg);
			$new[$trg_entity][$trg_field] = explode('.', $src);
		}
		$this->mapping = $new;
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