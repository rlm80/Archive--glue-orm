<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<title>Glue - <?php echo $title ?></title>
		<?php echo html::style(Route::get('glue_media')->uri(array('file' => 'glue.css'))) ?>
		<?php echo html::script('http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js') ?>
		<?php echo html::script(Route::get('glue_media')->uri(array('file' => 'glue.js'))) ?>
	</head>
	<body>
		<?php echo $content ?>
	</body>
</html>