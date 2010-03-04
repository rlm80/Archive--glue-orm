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
	protected $order_by = array();
	protected $where = array();

	// Set :
	protected $trg_set;

	// Chain :
	protected $chain;
	protected $roots;

	// Query :
	protected $query;

	// Trg fields
	protected $fields;

	// Constructor :
	public function  __construct($trg_set) {
		$this->trg_set	= $trg_set;
		$this->trg_set->root_command = $this;
	}

	public function execute() {
		// Execute command subtree with current command as root :
		$this->execute_self();

		// Cascade execution to next root commands :
		foreach($this->get_roots() as $root)
			$root->execute();
	}

	protected function execute_self() {
		// Execute query :
		$result = $this->query_exec();

		// Load objects and relationships from result set :
		$this->load_result($result);
	}

	protected function load_result(&$result) {
		foreach($this->get_chain() as $command)
			$command->load_result_self($result);
	}

	protected function load_result_self(&$result) {
		$this->trg_set->objects = $this->trg_set->entity->object_load($result, $this->trg_set->name.':');
	}

	protected function get_children() {
		return $this->trg_set->commands;
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

	protected function query_get() {
		if ( ! isset($this->query))
			$this->query = $this->query_build();
		return $this->query;
	}

	protected function query_build() {
		$query = DB::select();
		foreach($this->get_chain() as $command)
			$command->query_contrib($query);
		return DB::query(Database::SELECT, $query->compile(Database::instance()));
	}

	protected function query_contrib($query) {
		$this->query_contrib_fields($query);
		$this->query_contrib_from($query);
		$this->query_contrib_joins($query);
		$this->query_contrib_where($query);
		$this->query_contrib_order_by($query);
	}

	protected function query_contrib_fields($query) {
		$trg_entity = $this->trg_set->entity;
		if ( ! isset($this->fields))
			$this->fields = $trg_entity->fields_all();
		else
			$this->fields = array_merge($this->fields, array_diff($trg_entity->pk(), $this->fields));
		$trg_entity->query_select($query, $this->trg_set->name, $this->fields);
	}

	protected function query_contrib_order_by($query) {
		foreach($this->order_by as $ob)
			$this->trg_set->entity->query_order_by($query, $this->trg_set->name, $ob['field'], $ob['asc']);
	}

	protected function query_contrib_from($query) { }
	protected function query_contrib_joins($query) { }
	protected function query_contrib_where($query) { }

	abstract protected function query_exec();

	public function order_by($field, $asc) {
		$this->order_by[] = array('field' => $field, 'asc' => $asc);
	}

	public function select() {
		// If fields recieved as a list of strings, turn it to an array :
		$args = func_get_args();
		$fields = is_array($args[0]) ? $args[0] : $args;

		// Set fields :
		$this->fields = $fields;
	}

	public function not_select() {
		// If fields recieved as a list of strings, turn it to an array :
		$args = func_get_args();
		$fields = is_array($args[0]) ? $args[0] : $args;

		// Set fields :
		$this->fields = $this->trg_set->entity->fields_opposite($fields);
	}

	abstract protected function is_root();
}



