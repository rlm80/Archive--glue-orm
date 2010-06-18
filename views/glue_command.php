<tr class="superfluous">
	<td> <?php echo isset($src_set) ? $src_set : '-' ?> </td>
	<td>
		<?php
			echo isset($relationship) ?
				'<a href="'
				. url::site(Route::get('glue_relationship')->uri(array('entity' => $src_entity, 'relationship' => $relationship)))
				. '">' . ucfirst($src_entity) . ' -&gt; ' . $relationship . '</a>' : '-'
		?>
	</td>
	<td> <?php echo isset($trg_set) ? $trg_set : '-' ?> </td>
</tr>