<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Queries are objects that specify an object graph retrieval (or deletion) operation.
 *
 * They encapsulate all the parts required to define such an operation :
 * - a command tree,
 * - sets of objects that will hold the results,
 * - parameters.
 *
 * Queries provide a fluent interface to conveniently assemble all of these parts.
 *
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 */

abstract class Glue_Query {
	// Sets :
	protected $sets = array();
	protected $set_counters = array();
	protected $root_set;

	// Parameters :
	protected $params = array();
	protected $bound_params = array();
	protected $param_id = 0;

	// Command :
	protected $root_command;
	protected $active_command;

	// Constructor, creates a load command :
	public function __construct($entity_name, &$set = null, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		// Create target set :
		$entity	= glue::entity($entity_name);
		$set	= $this->create_set($entity);

		// Create command :
		$command = new Glue_Command_Load($entity, $set);

		// Set current command as root command :
		$this->root_command = $command;

		// Set current set as root set :
		$this->root_set = $set;

		// Switch active command to current command :
		$this->active_command = $command;

		// Add modifiers :
		$this->add_modifiers($conditions, $order_by, $limit, $offset);
	}

	// Creates a with command :
	public function with($src_set, $relationship_name, &$trg_set = null, $conditions = null, $order_by = null, $limit = null, $offset = null) {
		// Check source set existence in current query :
		$hash = spl_object_hash($src_set);
		if ( ! isset($this->sets[$hash]))
			throw new Kohana_Exception("Unknown set given as source of with command.");

		// Create trg_set :
		$relationship	= $src_set->entity()->relationship($relationship_name);
		$trg_entity 	= $relationship->to();
		$trg_set		= $this->create_set($trg_entity);

		// Create command :
		$command = new Glue_Command_With($relationship, $src_set, $trg_set);

		// Switch active command to current command :
		$this->active_command = $command;

		// Add modifiers :
		$this->add_modifiers($conditions, $order_by, $limit, $offset);

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
		$set = new Glue_Set_Node($name . '_' . $this->set_counters[$name]);

		// Register set :
		$this->sets[spl_object_hash($set)] = $set;

		return $set;
	}

	// Executes the query and returns the first element of the root set,
	// or null if the root set is empty.
	public function execute() {
		// Execute command tree :
		$this->root_command->execute($this->get_params());

		// Returns first element of root set, or null if the set is empty :
		if (count($this->root_set) > 0)
			return $this->root_set[0];
		else
			return null;
	}

	// Returns a view that tells how the query will be executed.
	public function debug() {
		return $this->root_command->debug();
	}

	// Set the value of a parameter.
	public function param($name, $value) {
		if ( ! isset($this->params[$name])) throw new Kohana_Exception("Undefined parameter '".$name."'");
		$this->params[$name]->value = $value;
		return $this;
	}

	// Registers a parameter and returns its symbolic representation in the query.
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

	// Avoids code duplication between with and constructor.
	protected function add_modifiers($conditions = null, $order_by = null, $limit = null, $offset = null) {
		// Target entity of active command :
		$entity = $this->active_command->trg_entity();

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

		// Add order by if any :
		if (isset($order_by))
			$this->order_by($order_by);

		// Add  limit if any :
		if (isset($limit))
			$this->limit($limit);

		// Add offset if any :
		if (isset($offset))
			$this->offset($offset);
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

	public function sort($sort) {
		$this->active_command->sort($sort);
		return $this;
	}
}
