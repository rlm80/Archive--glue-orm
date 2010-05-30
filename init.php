<?php

/**
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!!!!!!!! IN PRODUCTION COMMENT THESE ROUTES !!!!!!!!!!!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 */

Route::set('ogl_sandbox', 'ogl/sandbox')
	->defaults(array(
		'controller' => 'OGL',
		'action'     => 'sandbox',
	));

Route::set('ogl_entity', 'ogl/entity/<entity>')
	->defaults(array(
		'controller' => 'OGL',
		'action'     => 'entity',
	));

Route::set('ogl_relationship', 'ogl/relationship/<entity>/<relationship>')
	->defaults(array(
		'controller' => 'OGL',
		'action'     => 'relationship',
	));

Route::set('ogl_media', 'ogl/media/<file>', array('file' => '.+'))
	->defaults(array(
		'controller' => 'OGL',
		'action'     => 'media',
	));