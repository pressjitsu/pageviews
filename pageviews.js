"use strict";
(function($){
	if (!_pv_config.account)
		return;

	$(document).on('pageviews-update', function() {
		var config = _pv_config,
			keys = [],
			elements = {};

		if (!config.output_filter) {
			config.output_filter = function(t) {
				return t;
			};
		}

		$('.pageviews-placeholder').each(function() {
			var $el = $(this),
				key = $el.data('key');

			// Don't process already processed containers.
			if ($el.data('pv-processed'))
				return;

			if (key != config.incr)
				keys.push(key);

			if (!elements[key])
				elements[key] = [];

			elements[key].push($el);
		});

		if (config.incr) {
			$.ajax({
				method: 'post',
				url: config.base + '/incr/' + config.incr,
				headers: {'X-Account': config.account}
			}).done(function(e){
				for (var i in e) {
					if (elements[i]) {
						for (var j in elements[i]) {
							elements[i][j].text(config.output_filter(e[i]));
						}
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
				var el;

				for (var i in e) {
					if (elements[i]) {
						for (var j in elements[i]) {
							el = elements[i][j];
							el.text(config.output_filter(e[i]));
							el.data('pv-processed', 1);
						}
					}
				}
			});
		}
	});

	$(document).trigger('pageviews-update');

	// Support for Jetpack's infinite scroll.
	$(document.body).on('post-load', function() {
		$(document).trigger('pageviews-update');
	});
}(jQuery));