<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 */

/*
 * Since several queries may be constructed at the same time, it doesn't work
 * to have the data related to those queries represented as static variables, or
 * properties of the Glue instance because the queries may "cross-polinate". We
 * need query objects to encapsulates all the execution environnement of each query.
 */

class Glue_Query {	
	// Set cache :
	protected $sets = array();

	// Query parameters
	protected $params = array();
	protected $bound_params = array();

	// Root command :
	protected $root;

	// Last command :
	protected $active_command;

	// Param id counter :
	protected $param_id = 0;

	// Sets counters :
	protected $set_counters = array();

	// Constructor, creates a load command :
	public function __construct($entity_name, &$set) {
		$entity = glue::entity($entity_name);
		$this->root = new Glue_Command_Load($entity);
		$set = $this->create_set($entity, $this->root);

		// Switch active command to current command :
		$this->active_command = $this->root;
	}

	// Creates a with command :
	public function with($src_set, $relationship, &$trg_set = null) {
		// Get parent command of $src_set :
		$parent_command = $this->get_set_parent($src_set);

		// Get target entity :
		$trg_entity = $parent_command->trg_entity();
		
		// Create trg_set and command :
		$relationship = $trg_entity->relationship($relationship);
		$command = new Glue_Command_With($relationship, $src_set);
		$trg_set = $this->create_set($trg_entity, $command);
		$parent_command->add_child($command);
		
		// Switch active command to current command :
		$this->active_command = $command;

		// Return query for chainability :
		return $this;
	}

	// Creates a new set, adds it to cache, and add it to its parent command :
	protected function create_set($entity, $parent_command) {
		// Get entity name :
		$name = $entity->name();

		// Increment set counter :
		if ( ! isset($this->set_counters[$name]))
			$this->set_counters[$name] = 0;
		else
			$this->set_counters[$name] ++;

		// Create set :
		$set = new Glue_Set($name . '_' . $this->set_counters[$name]);

		// Register set :
		$this->sets[spl_object_hash($set)] = $parent_command;

		// Add set to command tree :
		$parent_command->set_trg_set($set);

		return $set;
	}

	protected function get_set_parent($set) {
		// Check set existence :
		$hash = spl_object_hash($set);
		if ( ! isset($this->sets[$hash]))
			throw new Kohana_Exception("Unknown set given as source of with command.");

		// Return parent command :
		return $this->sets[$hash];
	}

	// Init execution cascade :
	public function execute() {
		$this->root->execute($this->get_params());
	}

	// Init debugging cascade :
	public function debug() {
		return $this->root->debug();
	}

	// Set the value of a parameter in the query.
	public function param($name, $value) {
		if ( ! isset($this->params[$name])) throw new Kohana_Exception("Undefined parameter '".$name."'");
		$this->params[$name]->value = $value;
		return $this;
	}

	// Registers a parameter and returns its symbolic representation in the query :
	protected function register_param($param) {
		$param->symbol = ':param' . ($this->param_id ++);
		if ($param instanceof Glue_Param_Set)
			$this->params[$param->name] = $param;
		else
			$this->bound_params[] = $param;
		return $param->symbol;
	}

	// Get symbol/values parameter array :
	protected function get_params() {
		$parameters = array();
		foreach (array_merge($this->bound_params, $this->params) as $p)
			$parameters[$p->symbol] = $p->value();
		return $parameters;
	}

	// Forward calls to active command :
	public function where($field, $op, $expr) {
		// If $expr is a parameter, replace it with its symbolic representation in the query :
		if ($expr instanceof Glue_Param) {
			$symbol	= $this->register_param($expr);
			$expr	= DB::expr($symbol);
		}

		// Forward call :
		$this->active_command->where($field, $op, $expr);
		
		return $this;
	}

	public function root() {
		$this->active_command->root();
		return $this;
	}

	public function slave()	{
		$this->active_command->slave();
		return $this;
	}

	public function order_by($sort) {
		$this->active_command->order_by($sort);
		return $this;
	}

	public function limit($limit) {
		$this->active_command->limit($limit);
		return $this;
	}
	
	public function offset($offset) {
		$this->active_command->offset($offset);
		return $this;
	}
}
