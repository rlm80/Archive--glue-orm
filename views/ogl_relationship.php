<table class="ogl_table context">
	<tr class="trigger">
		<th colspan="2" class="top">
			<span><?php echo (isset($closed) && $closed === true) ? '+' : '-' ?></span>
			<?php echo ucfirst($from) . ' -&gt; ' . $name ?>
		</th>
	</tr>
	<tr class="superfluous">
		<th>Target entity</th>
		<td><?php echo '<a href="'
				. url::site(Route::get('ogl_entity')->uri(array('entity' => $to)))
				. '">'
				. ucfirst($to)
				. '</a>';
			?>
		</td>
	</tr>
	<tr class="superfluous">
		<th>Type</th>
		<td>
		<?php
			switch ($type) {
				case OGL_Relationship::MANY_TO_MANY;	echo "many-to-many";	break;
				case OGL_Relationship::ONE_TO_ONE;		echo "one-to-one";		break;
				case OGL_Relationship::MANY_TO_ONE;		echo "many-to-one";		break;
				case OGL_Relationship::ONE_TO_MANY;		echo "one-to-many";		break;
			}
		?></td>
	</tr>
	<tr class="superfluous">
		<th>Mapping</th>
		<td><?php print_r($mapping) ?></td>
	</tr>
	<tr class="superfluous">
		<th>Property</th>
		<td><?php print_r($property) ?></td>
	</tr>	
	<tr class="superfluous">
		<th>Reverse</th>
		<td><a href="<?php echo url::site(Route::get('ogl_relationship')->uri(array('entity' => $to, 'relationship' => $reverse))) ?>"><?php echo ucfirst($to) . ' -&gt; ' . $reverse ?></td>
	</tr>
</table>