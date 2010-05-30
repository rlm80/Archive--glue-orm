<table class="ogl_table context">
	<tr class="trigger">
		<th colspan="4" class="top">
			<span><?php echo (isset($closed) && $closed === true) ? '+' : '-' ?></span>
			<?php echo $title ?>
		</th>
	</tr>
	<tr class="superfluous">
		<th>SQL</th>
		<td colspan="3"><?php echo htmlspecialchars($sql) ?></td>
	</tr>
	<tr class="superfluous">
		<th rowspan="<?php echo (count($commands) + 1) ?>">Commands</th>
		<th>Source set</th><th>Relationship</th><th>Target set</th>
	</tr>
	<?php foreach($commands as $command) echo $command;	?>
	<?php if (count($roots) >= 1) { ?>
	<tr class="superfluous">
		<th>Subrequests</th>
		<td colspan="3" style="padding: 0 10px">
			<?php foreach($roots as $root) echo $root;	?>
		</td>
	</tr>
	<?php } ?>
</table>