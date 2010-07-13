<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Special sets to be used as nodes in the commands tree.
 *
 * @package	Glue
 * @author	Régis Lemaigre
 * @license	MIT
 */

class Glue_Set_Node extends Glue_Set {
	protected $name;		// Name of this set to be used as alias in queries.
	protected $parent;		// Command that has this set as its target.
	protected $children;	// Commands that have this set as their source.
	
	public function  __construct($parent, $name) {
		$this->parent	= $parent;
		$this->name		= $name;
	}
	
	public function name() {
		return $this->name;
	}
	
	public function entity() {
		return $this->parent->trg_entity();
	}
	
	public function children() {
		return $this->children;
	}
	
	public function parent() {
		return $this->parent;
	}	
	
	public function add_child($child) {
		$this->children[] = $child;
	}
	
	public function debug() {
		return View::factory('glue_set')
			->set('name',	$this->name)
			->set('entity',	$this->entity->name());
	}	
}