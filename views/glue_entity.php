<table class="glue_table context">
	<tr class="trigger">
		<th colspan="7" class="top">
			<span><?php echo (isset($closed) && $closed === true) ? '+' : '-' ?></span>
			<?php echo ucfirst($name) ?>
		</th>
	</tr>
	<tr class="superfluous"><th colspan="7">Fields</th></tr>
	<tr class="superfluous">
		<th>Fields</th>
		<th>Table::columns</th>
		<th>Property</th>
		<th>PHP Type</th>
		<th>Primary key</th>
		<th>Autoincrement</th>
		<th>Foreign key</th>
	</tr>
	<?php
		foreach($fields as $field) {
			echo '<tr class="superfluous">';
				echo '<td>' . $field . '</td>';
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
	<tr class="superfluous"><th colspan="7">&nbsp;</th></tr>
	<tr class="superfluous" class="bottom">
		<th>Database</th>
		<td colspan="6"><?php echo $db ?></td>
	</tr>
	<tr class="superfluous">
		<th>Model class</th>
		<td colspan="6"><?php echo $model ?></td>
	</tr>
</table>