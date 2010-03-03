<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With extends OGL_Command {
	protected $relationship;
	protected $src_set;

	// Root or slave command ?
	protected $root;

	// Constants :
	const ROOT	= 1;
	const SLAVE	= 2;
	const AUTO	= 3;

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

	protected function query_exec()	{
		$query = $this->query_get();
		return $this->src_set->query_exec($query);
	}

	protected function query_contrib_from($query) {
		parent::query_contrib_from($query);
		if ($this->is_root()) {
			$this->src_set->entity->query_from($query, $this->src_set->name);
		}
	}
	protected function query_contrib_where($query) {
		parent::query_contrib_where($query);
		if ($this->is_root()) {
			$entity	= $this->src_set->entity;
			$pk		= $entity->pk();
			$alias	= $this->src_set->name;
			if (count($pk) === 1)
				$entity->query_where($query, $alias, $pk[0], 'IN', new Database_Expression(':_pks'));
			else
				foreach($pk as $f)
					$entity->query_where($query, $alias, $f, '=', new Database_Expression(':_'.$f));
		}
	}

	protected function query_contrib_joins($query) {
		parent::query_contrib_joins($query);
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;
		$this->relationship->join($query, $src_alias, $trg_alias);
	}

	protected function query_contrib_fields($query) {
		parent::query_contrib_fields($query);
		if ($this->is_root()) {
			$src_entity = $this->src_set->entity;
			$src_entity->query_fields($query, $this->src_set->name, $src_entity->pk());
		}
	}

	public function slave() {
		$this->root = self::SLAVE;
	}

	public function root() {
		$this->root = self::ROOT;
	}

	protected function is_root() {
		switch ($this->root) {
			case self::AUTO :	$is_root = $this->relationship->multiple(); break;
			case self::ROOT :	$is_root = true;	break;
			case self::SLAVE :	$is_root = false;	break;
			default : throw new Kohana_Exception("Invalid value for root property in a command.");
		}
		return $is_root;
	}
}