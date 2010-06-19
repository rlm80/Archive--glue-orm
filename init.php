<?php

// Set up routes :
Route::set('glue_sandbox', 'glue/sandbox')
	->defaults(array(
		'controller' => 'Glue',
		'action'     => 'sandbox',
	));

Route::set('glue_entity', 'glue/entity/<entity>')
	->defaults(array(
		'controller' => 'Glue',
		'action'     => 'entity',
	));

Route::set('glue_relationship', 'glue/relationship/<entity>/<relationship>')
	->defaults(array(
		'controller' => 'Glue',
		'action'     => 'relationship',
	));

Route::set('glue_media', 'glue/media/<file>', array('file' => '.+'))
	->defaults(array(
		'controller' => 'Glue',
		'action'     => 'media',
	));
	
// Set up autoloading of proxy classes :
spl_autoload_register(array('Glue', 'auto_load'));