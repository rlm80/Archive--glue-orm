<?php

/**
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!!!!!!!! IN PRODUCTION COMMENT THESE ROUTES !!!!!!!!!!!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 */

Route::set('ogl_entity', 'ogl/entity/<entity>/')
	->defaults(array(
		'controller' => 'ogl',
		'action'     => 'entity',
	));

Route::set('ogl_relationship', 'ogl/relationship/<relationship>/')
	->defaults(array(
		'controller' => 'ogl',
		'action'     => 'entity',
	));

Route::set('ogl_media', 'ogl/media/<file>', array('file' => '.+'))
	->defaults(array(
		'controller' => 'ogl',
		'action'     => 'media',
	));