<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_Load_With_ManyToMany extends OGL_Command_Load_With {
	protected $pivot_fields;

	public function  __construct($relationship, $src_set, $trg_set, $trg_fields, $pivot_fields) {
		parent::__construct($relationship, $src_set, $trg_set, $trg_fields);
		$this->pivot_fields	= $pivot_fields;
	}

	public function is_root() {
		return true;
	}

	public function execute_self() {
	}

	public function query_contrib($query) {
		// Gather data :
		$rel		= $this->relationship;
		$from_fk	= $rel->from_fk();
		$to_fk		= $rel->to_fk();
		$src_fields = $rel->from()->fields();
		$piv_fields = $rel->pivot()->fields();
		$trg_fields = $rel->to()->fields();
		$src_table	= $rel->from()->table();
		$piv_table	= $rel->pivot()->table();
		$trg_table	= $rel->to()->table();
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;
		$piv_alias	= self::pivot_alias($src_alias, $trg_alias);

		// init query with src table :
		$query->from(array($src_table, $src_alias));

		// join pivot table :
		$query->join(array($piv_table, $piv_alias), 'INNER');
		foreach($from_fk as $src_field => $piv_field)
			$query->on($src_alias.'.'.$src_fields[$src_field]['column'],'=',$piv_alias.'.'.$piv_fields[$piv_field]['column']);

		// join trg table :
		$query->join(array($trg_table, $trg_alias), 'INNER');
		foreach($to_fk as $trg_field => $piv_field)
			$query->on($piv_alias.'.'.$piv_fields[$piv_field]['column'],'=',$trg_alias.'.'.$trg_fields[$trg_field]['column']);

		// Add requested pivot fields :
		foreach ($piv_fields as $name => $field)
			if(isset($this->pivot_fields[$name]))
				$query->select(array($piv_alias.'.'.$field['column'], $piv_alias.':'.$name));

		// Add requested trg fields :
		foreach ($trg_fields as $name => $field)
			if(isset($this->trg_fields[$name]))
				$query->select(array($trg_alias.'.'.$field['column'], $trg_alias.':'.$name));

		// Apply db builder calls :
		$this->apply_calls($query);
	}

	public function load($row) {
	}

	protected static function pivot_alias($src_alias, $trg_alias) {
		return $src_alias.'__'.$trg_alias;
	}
}