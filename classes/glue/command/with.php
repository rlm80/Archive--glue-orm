<?php defined('SYSPATH') OR die('No direct access allowed.');

class Glue_Command_With extends Glue_Command {
	// Constants :
	const ROOT	= 1;
	const SLAVE	= 2;
	const AUTO	= 3;

	protected $relationship;
	protected $src_set;

	// Root or slave command ?
	protected $root;

	public function  __construct($relationship, $src_set, $trg_set) {
		parent::__construct($trg_set);
		$this->root					= self::AUTO;
		$this->relationship			= $relationship;
		$this->src_set				= $src_set;
		$this->src_set->commands[]	= $this;
	}

	protected function load_result(&$result) {
		$this->relationship->from()->object_load($result, $this->src_set->name.':');
		parent::load_result($result);
	}

	protected function load_result_self(&$result) {
		// Load objects :
		parent::load_result_self($result);

		// Load relationships :
		$direct		= $this->relationship;
		$reverse	= $this->relationship->reverse();
		$src_key = $this->src_set->name.':__object';
		$trg_key = $this->trg_set->name.':__object';
		foreach($result as $row) {
			$src_obj = isset($row[$src_key]) ? $row[$src_key] : null;
			$trg_obj = isset($row[$trg_key]) ? $row[$trg_key] : null;
			$direct->link($src_obj, $trg_obj);
			$reverse->link($trg_obj, $src_obj);
		}
	}

	protected function query_exec($parameters)	{
		// Get data :
		$query		= $this->query_get()->parameters($parameters);
		$entity		= $this->src_set->entity;
		$pk			= $entity->pk();
		$alias		= $this->src_set->name;
		$objects	= $this->src_set->as_array();

		// No objects ? No result :
		if (count($objects) === 0)
			return array();

		// Get pk values :
		$pkvals = array_map(array($entity, 'object_pk'), $objects);

		// Exec query :
		$result = array();
		if ($this->is_unitary()) {
			// Use one query for each object and aggregate results :
			foreach($pkvals as $pkval) {
				foreach($pkval as $f => $val)
					$query->param( ':__'.$f, $val);
				$rows = $query->execute()->as_array();
				if (count($rows) >= 1)
					$result = array_merge($result, $rows);
			}
		}
		else {
			// Use only one query :
			$result = $query->param(':__pks', array_map('array_pop', $pkvals))->execute()->as_array();
		}
		
		return $result;
	}

	protected function query_contrib($query, $is_root) {
		parent::query_contrib($query, $is_root);
		
		// Entities and aliases :
		$src_entity	= $this->src_set->entity;
		$trg_entity = $this->trg_set->entity;
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;

		// Root ? Must base query on src set :
		if ($is_root) {
			$pk = $src_entity->pk();
			$src_entity->query_select($query, $src_alias, $pk);
			$src_entity->query_from($query, $src_alias);
			if ($this->is_unitary())
				foreach($pk as $f)
					$src_entity->query_where($query, $src_alias, $f, '=', DB::expr(':__'.$f));
			else
				$src_entity->query_where($query, $src_alias, $pk[0], 'IN', DB::expr(':__pks'));
		}

		// Add joins to trg entity :
		$this->relationship->join($query, $src_alias, $trg_alias);

		// Restrict result with where conditions (in ON clause !!!) :
		foreach($this->where as $w) {
			$expr = is_object($w['expr']) ? $w['expr'] : DB::expr(Database::instance()->quote($w['expr']));
			$trg_entity->query_on($query, $trg_alias, $w['field'], $w['op'], $expr);
		}
	}

	public function slave() {
		$this->root = self::SLAVE;
	}

	public function root() {
		$this->root = self::ROOT;
	}

	protected function is_unitary() {
		$src_entity	= $this->src_set->entity;
		$pk	= $src_entity->pk();
		if (count($pk) > 1 || isset($this->limit) || isset($this->offset))
			return true;
		else
			return false;
	}

	public function debug() {
		// Get debug view from parent :
		$view = parent::debug();

		// Add title :
		$title = 'From set ' . $this->src_set->name . ', load set(s) ';
		foreach($this->get_chain() as $command)
			$sets[] = $command->trg_set->name;
		$title .= implode(', ', $sets);
		$view->set('title', $title);

		return $view;
	}

	protected function debug_self() {
		return parent::debug_self()
			->set('src_set', $this->src_set->debug())
			->set('src_entity', $this->src_set->entity()->name())
			->set('relationship', $this->relationship->name());
	}
}