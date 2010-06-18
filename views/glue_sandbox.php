<form action="<?php url::site(Route::get('glue_sandbox')->uri()) ?>" method="get">
	<textarea name="query" style="display: block; width: 100%; height: 100px"><?php echo isset($query) ? htmlspecialchars($query) : 'Type your query here' ?></textarea>
	<input type="submit" value="debug" style="float: right; margin: 10px" />
</form>
<?php if (isset($query)) eval('echo ' . str_replace('execute', 'debug', $query) . ';') ?>