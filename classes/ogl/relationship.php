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

		// Fill in missing properties with default values based on $from and $name :
		if ( ! isset($this->to))		$this->to		= $this->default_to();
		if ( ! isset($this->property))	$this->property	= $this->default_property();
		if ( ! isset($this->reverse))	$this->reverse	= $this->default_reverse();
		if ( ! isset($this->multiple))	$this->multiple	= $this->default_multiple();
		if ( ! isset($this->mapping))	$this->mapping	= $this->default_mapping();

		// Fill in missing entity names in mapping :
		$new = array();
		foreach($mapping as $src => $trg) {
			if (strpos('.', $src) === FALSE) $src = $this->from . '.' . $src;
			if (strpos('.', $trg) === FALSE) $trg = $this->to   . '.' . $trg;
			$new[$src] = $trg;
		}
		$this->mapping = $new;

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
				foreach($from->default_fk as $src => $trg) $mapping[$src] = $pivot.'.'.$trg;
				foreach($to->default_fk   as $trg => $src) $mapping[$pivot.'.'.$src] = $trg;
				break;
			case 'S': $mapping = $from->default_fk; break;
			case '1': $pk = array_values($from->pk); $mapping = array_combine($pk, $pk); break;
			default : $mapping = array_flip($to->default_fk);
		}
		return $mapping;
	}

	public function add_joins($query, $src_alias, $trg_alias) {

		self::join($query, $src_alias, $this->from, $trg_alias, $this->to, $this->fk);

		self::join($query, $src_alias,   $this->from,  $pivot_alias, $this->pivot, $this->fk1);
		self::join($query, $pivot_alias, $this->pivot, $trg_alias,   $this->to,    $this->fk2);
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

	static protected function join($query, $src_alias, $src_entity, $trg_alias, $trg_entity, $fk, $join_type = 'INNER') {
		$trg_table	= $trg_entity->table;
		$trg_fields	= $trg_entity->fields;
		$src_fields	= $src_entity->fields;
		$query->join(array($trg_table, $trg_alias), $join_type);
		foreach($fk as $src_field => $trg_field)
			$query->on($trg_alias.'.'.$trg_fields[$trg_field]['column'], '=', $src_alias.'.'.$src_fields[$src_field]['column']);
	}
}