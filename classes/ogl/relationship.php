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

	// Properties that may NOT be set in children classes :
	private $from;
	private $name;

	// Properties that may be set in children classes :
	protected $to;
	protected $fk;
	protected $property;
	protected $reverse;

	// Class names of relationship ancestors :
	const ONE_TO_ONE	= 'OGL_Relationship_OneToOne';
	const MANY_TO_ONE	= 'OGL_Relationship_ManyToOne';
	const ONE_TO_MANY	= 'OGL_Relationship_OneToMany';
	const MANY_TO_MANY	= 'OGL_Relationship_ManyToMany';

	protected function __construct($from, $name) {
		$this->from = $from;
		$this->name = $name;
	}

	final public function name() {
		return $this->name;
	}

	final public function from() {
		return OGL_Entity::get($this->from);
	}

	public function property() {
		if ( ! isset($this->property))
			$this->property = $this->name();
		return $this->property;
	}

	public function add_joins($query, $src_alias, $trg_alias) {
		self::join($query, $src_alias, $this->from(), $trg_alias, $this->to(), $this->fk());
	}

	abstract public function to();
	abstract public function fk();
	abstract public function load_relationships($result, $src_alias, $trg_alias);

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
				case 'S': $class = self::ONE_TO_MANY;	break;
				case 'Z': $class = self::MANY_TO_MANY;	break;
				case '1': $class = self::ONE_TO_ONE;	break;
				default : $class = self::MANY_TO_ONE;
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