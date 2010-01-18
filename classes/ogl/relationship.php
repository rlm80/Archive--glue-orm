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
	}

	abstract protected function default_to();
	abstract protected function default_reverse();
	protected function default_property() {
		return $this->name;
	}

	abstract public function add_joins($query, $src_alias, $trg_alias);
	abstract public function load_relationships($result, $src_alias, $trg_alias);
	abstract public function cardinality();

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
			switch (substr($name, -1)) {
				case 'S': $class = 'OGL_Relationship_OneToMany';	break;
				case 'Z': $class = 'OGL_Relationship_ManyToMany';	break;
				case '1': $class = 'OGL_Relationship_OneToOne';		break;
				default : $class = 'OGL_Relationship_ManyToOne';
			}
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