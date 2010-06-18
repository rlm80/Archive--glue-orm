$(document).ready(function(){
	// Add click handlers to all triggers, now and ever entering the DOM :
	$('.trigger').live('click', function() {
		if ($(this).find('span').text() === '+')
			expand($(this).closest('.context'));
		else
			collapse($(this).closest('.context'));
	});

	refresh();
});

function expand(context) {
	context.find('.trigger span').filter(function () {
		return $(this).closest('.context')[0] === context[0];
	}).text('-');
	context.find('.superfluous').filter(function () {
		return $(this).closest('.context')[0] === context[0];
	}).show();
}

function collapse(context) {
	context.find('.trigger span').filter(function () {
		return $(this).closest('.context')[0] === context[0];
	}).text('+');
	context.find('.superfluous').filter(function () {
		return $(this).closest('.context')[0] === context[0];
	}).hide();
}

// Call this every time the DOM is modified.
function refresh() {
	$('.trigger').each(function() {
		if ($(this).find('span').text() === '+')
			collapse($(this).closest('.context'));
		else
			expand($(this).closest('.context'));
	});
}