<?php
	echo $name
	.' ( '
	.'<a href="'
	. url::site(Route::get('ogl_entity')->uri(array('entity' => $entity)))
	. '">'
	. ucfirst($entity)
	. '</a> )';
?>
