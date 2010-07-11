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
	protected $entity;		// Entity of this set.
	
	public function  __construct($entity, $name) {
		$this->entity	= $entity;
		$this->name		= $name;
	}
	
	public function name() {
		return $this->name;
	}
	
	public function entity() {
		return $this->entity;
	}
	
	public function debug() {
		return View::factory('glue_set')
			->set('name',	$this->name)
			->set('entity',	$this->entity->name());
	}	
}