<?php

/**
 * Add ogl routes.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!!!!!!!! IN PRODUCTION COMMENT THESE ROUTES !!!!!!!!!!!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 */
Route::set('ogl_sandbox', 'ogl/sandbox/')
	->defaults(array(
		'controller' => 'ogl',
		'action'     => 'sandbox',
	));

Route::set('ogl_media', 'ogl/media/<file>', array('file' => '.+'))
	->defaults(array(
		'controller' => 'ogl',
		'action'     => 'media',
	));