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

	// Query parameters
	protected $params = array();
	protected $bound_params = array();

	// Root command :
	protected $root;

	// Target command of db builder calls :
	protected $active_command;

	// Param id counter :
	protected $param_id = 0;

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

	// Creates a new set, adds it to cache, and returns it :
	protected $set_number = 0;
	protected function create_set($entity) {
		$name = $entity->name() . ($this->set_number ++);
		$set = new OGL_Set($name, $entity);
		$this->sets[] = $set;
		return $set;
	}

	// Init execution cascade :
	public function execute() {
		$this->root->execute($this->get_params());
	}

	// Set the value of a parameter in the query.
	public function param($name, $value) {
		$this->params[$name]->value = $value;
		return $this;
	}

	// Registers a parameter and returns its symbolic representation in the query :
	protected function register_param($param) {
		$param->symbol = ':param' . ($this->param_id ++);
		if ($param instanceof OGL_Param_Set)
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
		if ($expr instanceof OGL_Param) {
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

	public function order_by($field, $asc = OGL::ASC) {
		$this->active_command->order_by($field, $asc);
		return $this;
	}

	public function select() {
		$args = func_get_args();
		call_user_func_array(array($this->active_command, 'select'), $args);
		return $this;
	}

	public function not_select() {
		$args = func_get_args();
		call_user_func_array(array($this->active_command, 'not_select'), $args);
		return $this;
	}
}