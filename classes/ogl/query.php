<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * Since several OGL queries may be constructed at the same time, it doesn't work
 * to have the data related to those queries represented as static variables, or
 * properties of the OGL instance because the queries may "cross-polinate". We
 * need query objects to encapsulates all the execution environnement of each query.
 */

class OGL_Query {	
	// Set cache :
	protected $sets = array();

	// Root command :
	protected $root;

	// Target command of db builder calls :
	protected $active_command;

	// Constructor, creates a load command :
	public function __construct($expr, $fields = null) {
		static $pat = '/\s*([a-zA-Z][a-zA-Z0-9_]*)\s+([a-zA-Z][a-zA-Z0-9_]*)\s*/';
		if (preg_match($pat, $expr, $matches) > 0) {
			$entity		= OGL_Entity::get(inflector::singular($matches[1]));
			$trg_set	= $this->create_set($matches[2], $entity);
		}
		else
			throw new Kohana_Exception("Expression '".$expr."' is not valid.");
		$this->root				= new OGL_Command_Load($entity, $trg_set, $fields);
		$this->active_command	= $this->root;
	}

	// Creates a with command :
	public function with($expr, $trg_fields = null, $pivot_fields = null) {
		static $pat = '/\s*([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z][a-zA-Z0-9_]*)\s+([a-zA-Z][a-zA-Z0-9_]*)\s*/';
		if (preg_match($pat, $expr, $matches) > 0) {
			$src_set		= $this->get_set($matches[1]);
			$relationship	= $src_set->entity()->relationship($matches[2]);
			$trg_set		= $this->create_set($matches[3], $relationship->to());
			$command		= $relationship->create_command($src_set, $trg_set, $trg_fields, $pivot_fields);
		}
		else
			throw new Kohana_Exception("Expression '".$expr."' is not valid.");
		$this->active_command	= $command;

		return $this;
	}

	// Creates a new set, add it to cache, and returns it :
	protected function create_set($name, $entity) {
		if(isset($this->sets[$name]))
			throw new Kohana_Exception("Cannot redefine set '".$name."'.");
		else
			$this->sets[$name] = new OGL_Set($name, $entity);
		return $this->sets[$name];
	}

	// Gets an existing set from cache :
	protected function get_set($name) {
		if( ! isset($this->sets[$name]))
			throw new Kohana_Exception("Set '".$name."' is not defined yet.");
		else
			return $this->sets[$name];
	}

	// Store all unknown function calls in active command :
	public function __call($method, $args) {
		$this->active_command->store_call($method, $args);
		return $this;
	}

	// Executes load query :
	public function execute() {
		// Init execution cascade :
		$this->root->execute();

		// Return result array :
		$result = array();
		foreach($this->sets as $set)
			$result[$set->name] = $set->objects;
		return $result;
	}
}