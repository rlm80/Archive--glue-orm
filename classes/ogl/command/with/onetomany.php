<?php defined('SYSPATH') OR die('No direct access allowed.');

class OGL_Command_With_OneToMany extends OGL_Command_Load_With {
	public function is_root() {
		return true;
	}

	public function execute_query($query) {
		$entity		= $this->src_set->entity;
		$src_alias	= $this->src_set->name;
		$src_table	= $rel->from()->table();
		$fields		= $entity->fields();
		$pkfields	= $entity->pk();

		// Init query with src table :
		$query->from(array($src_table, $src_alias));

		// Get pk values for objects in src set :
		$pks = $this->src_set->get_pks();
		
		// Left-most table pk : single or multiple columns ?
		if (count($pkfields) === 1) {
			// Use IN :
			$pks = array_map('array_pop', $pks);
			$query->where($src_alias.'.'.$fields[$pk[0]]['column'], 'IN', $pks);
			$result = $query->execute()->as_array();
		}
		else {
			// Use one query for each object in src_set and aggregate results :
			$result = array();
			foreach($pkfields as $f)
				$query->where($src_alias.'.'.$fields[$f]['column'], '=', ':_'.$f);
			foreach($pks as $pk) {
				foreach($pkfields as $f)
					$query->param( ':_'.$f, $pk[$f]);
				$rows = $query->execute()->as_array();
				if (count($rows) >= 1)
					array_merge($result, $rows);
			}
		}

		return $result;
	}

	public function query_result($result) {
	}
	
	public function query_contrib($query) {
		// Gather data :
		$rel		= $this->relationship;
		$fk			= $rel->fk();
		$src_fields = $rel->from()->fields();
		$trg_fields = $rel->to()->fields();
		$src_table	= $rel->from()->table();
		$trg_table	= $rel->to()->table();
		$src_alias	= $this->src_set->name;
		$trg_alias	= $this->trg_set->name;

		// join trg table :
		$query->join(array($trg_table, $trg_alias), 'INNER');
		foreach($fk as $src_field => $trg_field)
			$query->on($src_alias.'.'.$src_fields[$src_field]['column'],'=',$trg_alias.'.'.$trg_fields[$trg_field]['column']);

		// Add requested fields :
		foreach ($trg_fields as $name => $field)
			if(isset($this->trg_fields[$name]))
				$query->select(array($trg_alias.'.'.$field['column'], $trg_alias.':'.$name));

		// Apply db builder calls :
		$this->apply_calls($query);
	}
}