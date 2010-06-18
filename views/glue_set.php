<?php
	echo $name
	.' ( '
	.'<a href="'
	. url::site(Route::get('glue_entity')->uri(array('entity' => $entity)))
	. '">'
	. ucfirst($entity)
	. '</a> )';
?>
