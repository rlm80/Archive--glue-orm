<table class="ogl_entity">
	<tr style="display: table-row" onclick="
				elems = $(this).siblings();
				exp = $(this).find('.ogl_entity_expand');
				if (exp.text() === '+') {
					elems.show();
					exp.text('-');
				}
				else {
					elems.hide();
					exp.text('+');
				}
	">
		<th class="ogl_entity_top" colspan="7">
			<span class="ogl_entity_expand">+</span>
			<?php echo ucfirst($name) ?>
		</th>
	</tr>
	<tr><th colspan="7">Fields properties</th></tr>
	<tr>
		<th>Fields</th>
		<th>Columns</th>
		<th>Property</th>
		<th>Type</th>
		<th>Primary key</th>
		<th>Autoincrement</th>
		<th>Foreign key</th>
	</tr>
	<?php
		foreach($fields as $field) {
			echo '<tr>';
				echo '<th>' . $field . '</th>';
				echo '<td>';
					$arr = array();
					foreach($columns[$field] as $table => $column)
						$arr[] = $table . '::' . $column;
					echo implode(' , ', $arr);
				echo '</td>';
				echo '<td>' . $properties[$field] . '</td>';
				echo '<td>' . $types[$field] . '</td>';
				echo '<td>' . (in_array($field, $pk) ? 'yes' : '-') . '</td>';
				echo '<td>' . (in_array($field, $pk) && $autoincrement ? 'yes' : '-') . '</td>';
				echo '<td>' . (in_array($field, $pk) ? $fk[$field] : '-') . '</td>';
			echo '</tr>';
		}
	?>
	<tr><th colspan="7">Other properties</th></tr>
	<tr class="ogl_entity_bottom">
		<th>Database</th>
		<td colspan="6"><?php echo $db ?></td>
	</tr>
	<tr>
		<th>Model class</th>
		<td colspan="6"><?php echo $model ?></td>
	</tr>
</table>