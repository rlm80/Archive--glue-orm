<?php defined('SYSPATH') OR die('No direct access allowed.');

/*
 * Commands are the smallest work units in a query. They are meant to load a
 * target set of objects according to their relationships with a source set
 * of objects.
 *
 * Commands form a tree - commands with source set A being children of the
 * (unique) command with target set A.
 */

abstract class OGL_Command {
	// Query builder calls :
	protected $calls = array();

	// Sets :
	protected $src_set;
	protected $trg_set;

	// Chain :
	protected $chain;
	protected $roots;

	// Query :
	protected $query;

	// Trg fields
	protected $trg_fields;

	// Constructor :
	public function  __construct($src_set, $trg_set, $trg_fields) {
		$this->trg_fields	= $trg_fields;
		$this->src_set		= $src_set;
		$this->trg_set		= $trg_set;
		$this->src_set->commands[]		= $this;
		$this->trg_set->root_command	= $this;
	}

	public function execute() {
		// Execute command subtree with current command as root :
		$this->execute_self();

		// Cascade execution to next root commands :
		foreach($this->get_roots() as $root)
			$root->execute();
	}

	protected function execute_self() {
		// Get query :
		$query = $this->get_query();

		// Execute query(ies) :
		$result = $this->src_set->exec_query($query);

		// Load objects from result set :
		foreach($this->get_chain() as $command)
			$command->load_objects($result);

		// Load relationships from result set :
		foreach($this->get_chain() as $command)
			$command->load_relationships($result);
	}

	protected function load_objects(&$result) {
		$this->trg_set->objects = $this->trg_set->entity->get_objects($result, $this->trg_set->name);
	}

	protected function get_children() {
		return $this->trg_set->commands[];
	}
	
	protected function get_parent() {
		return $this->src_set->root_command;
	}

	protected function get_chain() {
		if ( ! isset($this->chain))
			list($this->chain, $this->roots) = $this->build_chain();
		return $this->chain;
	}

	protected function get_roots() {
		if ( ! isset($this->roots))
			list($this->chain, $this->roots) = $this->build_chain();
		return $this->roots;
	}

	// Returns command chain that starts with this command and all roots that form its boundaries in
	// the command tree.
	protected function build_chain() {
		$chain = array($this);
		$roots = array();
		foreach($this->get_children() as $command) {
			if ($command->is_root())
				$roots[] = $command;
			else {
				list($new_chain, $new_roots) = $command->build_chain();
				$chain = array_merge($chain, $new_chain);
				$roots = array_merge($roots, $new_roots);
			}
		}
		return array($chain, $roots);
	}

	protected function get_query() {
		if ( ! isset($this->query))
			$this->query = $this->build_query();
		return $this->query;
	}

	protected function build_query() {
		$query = DB::select();
		$this->src_set->init_query($query);
		foreach($this->get_chain() as $command)
			$command->query_contrib($query);
		$this->query = $query->compile(Database::instance());
	}

	// Decides whether or not the DB query builder call is valid for current command type.
	protected function is_valid_call($method, $args) {
		static $allowed = array('param','parameters');
		return ! (array_search($method, $allowed) === FALSE);
	}

	// Stores a DB query builder call on the call stack :
	public function store_call($method, $args) {
		if ($this->is_valid_call($method, $args))
			$this->calls[] = array($method, $args);
		else
			throw new Kohana_Exception("Call to method '".$method."' is invalid in this context.");
	}

	// Applies all DB builder calls to given query :
	protected function apply_calls($query) {
		foreach($this->calls as $call)
			call_user_func_array(array($query, $call[0]), $call[1]);
	}

	abstract protected function load_relationships($result);
	abstract protected function query_contrib($query);
	abstract protected function is_root();
}



