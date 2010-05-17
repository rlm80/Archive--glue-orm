$(document).ready(function(){
	// Add click handlers to all entities, now and ever entering the DOM :
	$('.trigger').live('click', function() {
		if ($(this).find('span').text() === '+')
			expand($(this).closest('.context'));
		else
			collapse($(this).closest('.context'));
	});

	refresh();
});

function expand(context) {
	context.find('.trigger span').text('-');
	context.find('.superfluous').show();
}

function collapse(context) {
	context.find('.trigger span').text('+');
	context.find('.superfluous').hide();
}

function refresh() {
	$('.trigger').each(function() {
		if ($(this).find('span').text() === '+')
			collapse($(this).closest('.context'));
		else
			expand($(this).closest('.context'));
	});
}