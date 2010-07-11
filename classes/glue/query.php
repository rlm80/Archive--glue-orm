<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 */

/*
 * Since several queries may be constructed at the same time, it doesn't work
 * to have the data related to those queries represented as static variables, or
 * properties of the Glue instance because the queries may "cross-pollinate". We
 * need query objects to encapsulates all the execution environnement of each query.
 */

abstract class Glue_Query {
	// Set cache :
	protected $sets = array();
	
	// Sets counters :
	protected $set_counters = array();	

	// Query parameters
	protected $params = array();
	protected $bound_params = array();

	// Root command :
	protected $root;

	// Last command :
	protected $active_command;

	// Param id counter :
	protected $param_id = 0;

	// Constructor, creates a load command :
	public function __construct($entity_name, &$set, $conditions) {
		// Create target set :
		$entity	= glue::entity($entity_name);
		$set	= $this->create_set($entity);
		
		// Create command :
		$command = new Glue_Command_Load($entity, $set);		
		
		// Register set :
		$this->register_set($set, $this->root);
		
		// Set current command as root :
		$this->root = $command;
		
		// Switch active command to current command :
		$this->active_command = $command;
		
		// Add conditions if any :
		if ( ! empty($conditions)) {
			// PK given ?
			if ( ! is_array($conditions)) {
				$pk = $entity->pk();
				if (count($pk) > 1)
					throw new Kohana_Exception("Scalar value used for multiple columns pk.");
				else
					$conditions = array($pk[0] => $conditions);
			}
			
			// Add conditions :			
			foreach($conditions as $field => $value) {
				if (is_array($value))
					$this->where($field, 'IN', $value);
				else
					$this->where($field, '=', $value);
			}
		}
	}

	// Creates a with command :
	public function with($src_set, $relationship_name, &$trg_set = null) {
		// Check source set existence in current query :
		$hash = spl_object_hash($src_set);
		if ( ! isset($this->sets[$hash]))
			throw new Kohana_Exception("Unknown set given as source of with command.");
		
		// Get parent command of $src_set :
		$parent_command = $this->sets[$hash]['parent'];

		// Create trg_set :
		$relationship	= $src_set->entity()->relationship($relationship_name);
		$entity 		= $relationship->to();
		$trg_set		= $this->create_set($entity);
		
		// Create command :
		$command = new Glue_Command_With($relationship, $src_set, $trg_set, $parent_command);
		
		// Register set :
		$this->register_set($trg_set, $command);
		
		// Switch active command to current command :
		$this->active_command = $command;

		// Return query for chainability :
		return $this;
	}

	// Creates a new set with a unique name that reflects the entity it belongs to :
	protected function create_set($entity) {
		// Get entity name :
		$name = $entity->name();

		// Increment set counter :
		if ( ! isset($this->set_counters[$name]))
			$this->set_counters[$name] = 0;
		else
			$this->set_counters[$name] ++;

		// Create set :
		$set = new Glue_Set_Node($entity, $name . '_' . $this->set_counters[$name]);

		return $set;
	}
	
	protected function register_set($set, $parent_command) {
		$this->sets[spl_object_hash($set)] = array(
				'parent'	=> $parent_command,
				'set'		=> $set
			);
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
