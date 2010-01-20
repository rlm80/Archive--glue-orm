<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * IDEE : introduire variable $reverse, avec pour valeur par défaut...un truc basé sur to().
 *
 * relationships doivent bien être des classes plutôt que des propriétés des entité chargées
 * via init(), car ainsi seul le code des relations qui sont effectivement utilisée est compilé
 */
/*
 * Relationships link entities to each others. They are objects responsible for
 * handling the conversion between database foreing keys, and model objects
 * references. They know about pivot tables involved in relationships and the way
 * primary key columns in one table map to foreign key columns in another.
 *
 * Relationships are always requested from OGL by name :
 * - by referring to them in an OGL query (ogl::load('users u')->with(u.postS p)),
 * - ...
 *
 * Relationship objects behave as singletons. When an relationship object is required by name,
 * OGL will instanciate :
 * a) the class OGL_Relationship_<entity name>_<relationship name> if it exists,
 * b) one of the default class OGL_Relationship_OneToMany, OGL_Relationship_ManyToOne,
 * OGL_Relationship_OneToOne or OGL_Relationship_ManyToMany, depending on the relationship
 * name, if no such class is found.
 *
 * Conventions used by the default OGL_Relationships classes :
 * - ...
 *
 * If you wish to define your own ... you need to create your
 * own OGL_Relationship_<entity name>_<relationship name> relationship class.
 */

abstract class OGL_Relationship {
	// Relationships cache :
	static protected $relationships = array();

	// Cardinality values :
	const SINGLE	= 1; // Relationship associates at most one target entity instance to each source entity instance
	const MULTIPLE	= 2; // Relationship may associate more than one target entity instance to each source entity instance

	// Properties that may NOT be set in children classes :
	public $from;
	public $name;

	// Properties that may be set in children classes :
	public $to;
	public $property;
	public $reverse;
	public $cardinality;

	protected function __construct($from, $name) {
		// Set properties :
		$this->from = OGL_entity::get($from);
		$this->name = $name;

		// Init properties (order matters !!!) :
		if ( ! isset($this->to)) 		$this->to		= $this->default_to();
		$this->to = OGL_Entity::get($this->to);
		if ( ! isset($this->property))	$this->property	= $this->default_property();
		if ( ! isset($this->reverse))	$this->reverse	= $this->default_reverse();
		$this->reverse = OGL_Relationship::get($this->to->name, $this->reverse);
		if ( ! isset($this->cardinality))	$this->cardinality	= $this->default_cardinality();
	}

	protected function default_to() {
		switch (substr($this->name, -1)) {
			case 'Z': $to = substr($this->name, 0, -1); break;
			case 'S': $to = substr($this->name, 0, -1); break;
			case '1': $to = substr($this->name, 0, -1); break;
			default : $to = $this->name;
		}
		return $to;
	}

	protected function default_reverse() {
		switch (substr($this->name, -1)) {
			case 'Z': $reverse = $this->from->name.'Z';	break;
			case 'S': $reverse = $this->from->name;		break;
			case '1': $reverse = $this->from->name.'1';	break;
			default : $reverse = $this->from->name.'S';
		}
		return $reverse;
	}

	protected function default_property() {
		return $this->name;
	}

	protected function default_cardinality() {
		switch (substr($this->name, -1)) {
			case 'Z': $cardinality = self::MULTIPLE;	break;
			case 'S': $cardinality = self::MULTIPLE;		break;
			case '1': $cardinality = self::SINGLE;	break;
			default : $cardinality = self::SINGLE;
		}
		return $cardinality;
	}

	abstract public function add_joins($query, $src_alias, $trg_alias);

	public function load_relationships($result, $src_alias, $trg_alias)	{
		$src_key	= $src_alias.':__object';
		$trg_key	= $trg_alias.':__object';
		$property	= $this->property;
		foreach($result as $row) {
			if (isset($row[$src_key])) {
				$src = $row[$src_key];
				if ($this->cardinality === self::MULTIPLE && ! isset($src->$property))
					$src->$property = array();
				if (isset($row[$trg_key])) {
					$trg = $row[$trg_key];
					if ($this->cardinality === self::MULTIPLE)
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
		if ( ! class_exists($class)) {
			if (substr($name, -1) == 'Z')
				$class = 'OGL_Relationship_Indirect';
			else
				$class = 'OGL_Relationship_Direct';
		}
		$relationship = new $class($entity_name, $name);
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