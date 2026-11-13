/* global jQuery, psSmushVideo */
(function ($) {
	'use strict';

	$(function () {
		var overview = $('#ps-smush-video-bulk');
		if (!overview.length) {
			return;
		}
		var start = $('#ps-video-start');
		var recheck = $('#ps-video-recheck');
		var stop = $('#ps-video-stop');
		var progress = $('#ps-video-progress');
		var results = $('#ps-video-results');
		var clearLog = $('#ps-video-clear-log');
		var logStatus = $('#ps-video-log-status');
		var clearing = false;
		var running = false;
		var stopping = false;
		var processed = 0;
		var successful = 0;
		var errors = 0;
		var total = 0;
		var mode = 'optimize';
		var available = overview.attr('data-available') === '1';

		function request(action, data) {
			return $.ajax({
				url: psSmushVideo.url,
				method: 'POST',
				dataType: 'json',
				timeout: 1950000,
				data: $.extend({ action: action, nonce: psSmushVideo.nonce }, data)
			});
		}

		function controls() {
			start.prop('disabled', running || clearing || !available || Number(overview.attr('data-pending')) === 0);
			recheck.prop('disabled', running || clearing || !available || Number(overview.attr('data-total')) === 0);
			stop.prop('disabled', !running || stopping);
			clearLog.prop('disabled', running || clearing || results.children().length === 0);
			overview.find('.icon-fi-loader').toggle(running);
		}

		function updateBar(complete) {
			var percent = complete ? 100 : (total > 0 ? Math.min(100, Math.round(processed / total * 100)) : 0);
			$('#ps-video-bar').attr('aria-valuenow', percent).find('.ps-video-progress-inner').css('width', percent + '%');
		}

		function finish(message, refresh) {
			progress.text(message);
			if (refresh) {
				stopping = true;
				controls();
				refreshStats(function () {
					running = false;
					controls();
				});
			} else {
				running = false;
				controls();
			}
		}

		function networkFailure() {
			finish(psSmushVideo.networkError, false);
		}

		function refreshStats(callback) {
			request('ps_smush_video_stats', {}).done(function (response) {
				if (!response || !response.success || !response.data ||
					typeof response.data.total !== 'number' || typeof response.data.pending !== 'number') {
					finish(response && response.data && response.data.message ? response.data.message : psSmushVideo.statsError, false);
					return;
				}
				var stats = response.data;
				overview.find('[data-video-stat]').each(function () {
					var key = $(this).attr('data-video-stat');
					$(this).text(stats[key]);
				});
				overview.attr('data-total', stats.total).attr('data-pending', stats.pending);
				$('#ps-video-pending').text(stats.pending > 0 ? psSmushVideo.pending.replace('%d', stats.pending) : psSmushVideo.done);
				controls();
				if (callback) {
					callback(stats);
				}
			}).fail(networkFailure);
		}

		function counts() {
			return psSmushVideo.progress.replace('%1$d', processed).replace('%2$d', successful).replace('%3$d', errors);
		}

		function processPage(items, index, page) {
			if (stopping) {
				finish(psSmushVideo.stopped + ' ' + counts(), true);
				return;
			}
			if (index >= items.length) {
				if (page.done) {
					updateBar(true);
					finish((mode === 'check' ? psSmushVideo.checked : psSmushVideo.complete) + ' ' + counts(), true);
				} else {
					refreshStats(function () {
						loadPage(page.cursor, page.ceiling);
					});
				}
				return;
			}
			var item = items[index];
			progress.text(counts() + ' — ' + item.title + ': ' + (mode === 'check' ? psSmushVideo.checking : psSmushVideo.working));
			request('ps_smush_video_process', { id: item.id, mode: mode }).done(function (response) {
				if (!response || typeof response.success !== 'boolean' || !response.data || typeof response.data.message !== 'string') {
					networkFailure();
					return;
				}
				processed++;
				var message = item.title + ': ' + response.data.message;
				if (response.success) {
					successful++;
					if (mode === 'optimize') {
						message += ' ' + response.data.before_human + ' → ' + response.data.after_human +
							' (' + response.data.change_human + ' ' + (response.data.saved < 0 ? psSmushVideo.larger : response.data.saved > 0 ? psSmushVideo.smaller : psSmushVideo.unchanged) + ')';
						if (response.data.dimensions_before && response.data.dimensions_before !== response.data.dimensions_after) {
							message += ' · ' + response.data.dimensions_before + ' → ' + response.data.dimensions_after;
						}
					}
				} else {
					errors++;
					results.closest('details').prop('open', true);
				}
				$('<li>').text(message).prependTo(results);
				results.children().slice(100).remove();
				updateBar(false);
				if (!response.success && response.data.code === 'video_busy') {
					finish(message, true);
					return;
				}
				processPage(items, index + 1, page);
			}).fail(networkFailure);
		}

		function loadPage(cursor, ceiling) {
			request('ps_smush_video_list', { cursor: cursor, ceiling: ceiling, mode: mode }).done(function (response) {
				if (!response || !response.success || !response.data || !Array.isArray(response.data.items)) {
					finish(response && response.data && response.data.message ? response.data.message : psSmushVideo.networkError, false);
					return;
				}
				processPage(response.data.items, 0, response.data);
			}).fail(networkFailure);
		}

		function begin(nextMode) {
			if (running || clearing) {
				return;
			}
			running = true;
			stopping = false;
			mode = nextMode;
			processed = successful = errors = 0;
			results.empty();
			$('#ps-video-progress-wrap').prop('hidden', false);
			updateBar(false);
			controls();
			progress.text(mode === 'check' ? psSmushVideo.checking : psSmushVideo.working);
			refreshStats(function (stats) {
				total = mode === 'check' ? stats.total : stats.pending;
				loadPage(0, 0);
			});
		}

		start.on('click', function () { begin('optimize'); });
		recheck.on('click', function () { begin('check'); });
		stop.on('click', function () {
			stopping = true;
			controls();
		});
		clearLog.on('click', function () {
			if (running || clearing) {
				return;
			}
			clearing = true;
			logStatus.text(psSmushVideo.clearing);
			controls();
			request('ps_smush_video_clear_log', {}).done(function (response) {
				if (!response || !response.success || !response.data || typeof response.data.message !== 'string') {
					logStatus.text(response && response.data && response.data.message ? response.data.message : psSmushVideo.clearError);
					return;
				}
				results.empty().closest('details').prop('open', false);
				logStatus.text(response.data.message);
			}).fail(function (xhr) {
				logStatus.text(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : psSmushVideo.clearError);
			}).always(function () {
				clearing = false;
				controls();
			});
		});
	});
}(jQuery));
