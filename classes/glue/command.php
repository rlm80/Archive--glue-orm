<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 *
 * Each query is a tree, with sets of objects being nodes, and commands being branches :
 *
 * --root_command1--> root_set  --command2--> set2
 * 					  		   '--command3--> set3 --command4--> set4
 * 												  '--command5--> set5
 * 												  '--command6--> set6
 *
 * Each command defines the way to load a new set of objects (the target set) on the basis
 * of a (conceptually) previously loaded set of objects (the source set), a relationship
 * and optional conditions.
 *
 * @package    Glue
 * @author     Régis Lemaigre
 * @license    MIT
 *
 */

abstract class Glue_Command {
	// Target set :
	protected $trg_set;

	// Modifiers :
	protected $order_by = array();
	protected $where = array();
	protected $limit;
	protected $offset;
	protected $fields;

	// Cache chain, roots and query :
	protected $chain;
	protected $roots;
	protected $query;

	// Constructor :
	public function  __construct($trg_set) {
		$this->trg_set = $trg_set;
		$this->trg_set->set_parent($this);
	}

	protected function children() {
		return $this->trg_set->children();
	}

	abstract public function trg_entity();

	protected function trg_alias() {
		return $this->trg_set->name();
	}

	public function execute($parameters) {
		// Execute command subtree with current command as root :
		$this->execute_self($parameters);

		// Cascade execution to next root commands :
		foreach($this->get_roots() as $root)
			$root->execute($parameters);
	}

	protected function debug_self() {
		return View::factory('glue_command')->set('trg_set', $this->trg_set->debug());
	}

	protected function execute_self($parameters) {
		// Execute query :
		$result = $this->query_exec($parameters);

		// Load objects and relationships from result set :
		$this->load_result($result);
	}

	protected function load_result(&$result) {
		foreach($this->get_chain() as $command)
			$command->load_result_self($result);
	}

	protected function load_result_self(&$result) {
		$objects = $this->trg_entity()->get_objects($result, $this->trg_alias()); // TODO change this to make use of get_object() instead
		$this->trg_set->set($objects); // TODO make sure order is not altered
	}

	protected function get_chain() {
		if ( ! isset($this->chain))
			list($this->chain, $this->roots) = $this->find_chain($this);
		return $this->chain;
	}

	protected function get_roots() {
		if ( ! isset($this->roots))
			list($this->chain, $this->roots) = $this->find_chain($this);
		return $this->roots;
	}

	protected function find_chain($root) {
		$chain = array($this);
		$roots = array();
		foreach($this->children() as $command) {
			if ($root->is_relative_root($command))
				$roots[] = $command;
			else {
				list($new_chain, $new_roots) = $command->find_chain($root);
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
		foreach($this->get_chain() as $command) {
			$is_root = ($command === $this) ? true : false;
			$command->query_contrib($query, $is_root);
		}
		return DB::query(Database::SELECT, $query->compile(Database::instance()));
	}

	protected function query_contrib($query, $is_root) {
		// Entities and aliases :
		$trg_entity	= $this->trg_entity();
		$trg_alias	= $this->trg_alias();

		// trg fields :
		if ( ! isset($this->fields))
			$this->fields = $trg_entity->fields();
		else
			$this->fields = array_merge($this->fields, array_diff($trg_entity->pk(), $this->fields));
		$trg_entity->query_select($query, $trg_alias, $this->fields);

		// Order by :
		foreach($this->order_by as $field => $order)
			$trg_entity->query_order_by($query, $trg_alias, $field, $order);

		// Limit :
		if (isset($this->limit))
			$query->limit($this->limit);

		// Offset :
		if (isset($this->offset))
			$query->offset($this->offset);
	}

	protected function is_relative_root($command) {
		// Cardinality > 1 ?
		$relationship_type = $command->relationship->type();
		$multiple = (
			$relationship_type === Glue_Relationship::MANY_TO_MANY ||
			$relationship_type === Glue_Relationship::ONE_TO_MANY
		);

		// Limit or offset required in command ? No choice, command must be a root.
		if (isset($command->limit) || isset($command->offset))
			return true;

		// Limit or offset required in current command and relative command with multiple relationship ? No choice, command must be a root.
		if ((isset($this->limit) || isset($this->offset)) && $multiple)
			return true;

		// Otherwise let the user decide :
		switch ($command->root) {
			case Glue_Command_With::AUTO :	$is_root = $multiple; break;
			case Glue_Command_With::ROOT :	$is_root = true;	break;
			case Glue_Command_With::SLAVE :	$is_root = false;	break;
			default : throw new Kohana_Exception("Invalid value for root property in a command.");
		}
		return $is_root;
	}

	abstract protected function query_exec($parameters);

	public function order_by($clause) {
		// Parse order by clause and set current order by :
		$this->order_by = array();
		$clause = preg_replace('/\s+/', ' ', $clause);
		$clause = explode(',', $clause);
		foreach($clause as $c) {
			$parts	= explode(' ', $c);
			$field	= $parts[0];
			$order	= ((! isset($parts[1])) || strtolower(substr($parts[1], 0, 1)) === 'a') ? 'ASC' : 'DESC';
			$this->order_by[$field] = $order;
		}
	}

	public function fields() {
		// If fields recieved as a list of strings, turn it into an array :
		$args = func_get_args();
		$fields = is_array($args[0]) ? $args[0] : $args;

		// Set fields :
		$this->fields = $fields;
	}

	public function not_fields() {
		// If fields recieved as a list of strings, turn it to an array :
		$args = func_get_args();
		$fields = is_array($args[0]) ? $args[0] : $args;

		// Set fields :
		$this->fields = array_diff($this->trg_entity()->fields(), $fields);
	}

	public function where($field, $op, $expr) {
		$this->where[] = array('field' => $field, 'op' => $op, 'expr' => $expr);
	}

	public function sort($sort) {
		$this->trg_set->sort($sort);
	}

	public function limit($limit) {
		$this->limit = $limit;
	}

	public function offset($offset) {
		$this->offset = $offset;
	}

	public function debug() {
		// Prepare view with SQL :
		$view = View::factory('glue_command_root');
		$query = $this->query_get();
		$view->set('sql', $query);

		// Display all commands executed in SQL :
		$views = array();
		foreach($this->get_chain() as $command)
			$views[] = $command->debug_self();
		$view->set('commands', $views);

		// Cascade debug to next root commands :
		$views = array();
		foreach($this->get_roots() as $root)
			$views[] = $root->debug();
		$view->set('roots', $views);

		return $view;
	}
}



