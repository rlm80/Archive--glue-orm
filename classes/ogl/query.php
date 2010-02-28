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
	public function __construct($entity_name, &$set) {
		$entity = OGL_Entity::get($entity_name);
		$set = $this->create_set($entity);
		$this->root = new OGL_Command_Load($entity, $set);
		$this->active_command = $this->root;
	}

	// Creates a with command :
	public function with($src_set, $relationship, &$trg_set) {
		// Check src_set existence among sets of current query :
		if ( ! in_array($src_set, $this->sets))
			throw new Kohana_Exception("Unknown set given as source of with command.");
		
		// Create trg_set and command :
		$relationship	= $src_set->entity->relationship($relationship);
		$trg_set		= $this->create_set($relationship->to());
		$command		= new OGL_Command_With($relationship, $src_set, $trg_set);
		$this->active_command	= $command;

		// Return query for chainability :
		return $this;
	}

	// Sets active command as root :
	public function root() {
		$this->active_command->root();
		return $this;
	}

	// Sets active command as slave :
	public function slave() {
		$this->active_command->slave();
		return $this;
	}

	// Creates a new set, adds it to cache, and returns it :
	protected $set_number = 0;
	protected function create_set($entity) {
		$name = $entity->name() . ($this->set_number ++);
		$set = new OGL_Set($name, $entity);
		$this->sets[] = $set;
		return $set;
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
			$result[] = $set->objects;
		return $result;
	}
}