"use strict";
(function($){
	if (!_pv_config.account)
		return;

	var config = _pv_config,
		keys = [],
		elements = {};

	$('.pageviews-placeholder').each(function() {
		var $el = $(this),
			key = $el.data('key');

		if (key != config.incr)
			keys.push(key);

		elements[key] = $el;
	});

	if (config.incr) {
		$.ajax({
			method: 'post',
			url: config.base + '/incr/' + config.incr,
			headers: {'X-Account': config.account}
		}).done(function(e){
			for (var i in e) {
				if (elements[i]) {
					elements[i].text(e[i]);
				}
			}
		});
	}

	if (keys.length > 0) {
		$.ajax({
			method: 'get',
			url: config.base + '/get/' + keys.join(','),
			headers: {'X-Account': config.account}
		}).done(function(e){
			for (var i in e) {
				if (elements[i]) {
					elements[i].text(e[i]);
				}
			}
		});
	}
}(jQuery));