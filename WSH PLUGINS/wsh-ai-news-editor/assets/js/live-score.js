(function () {
	var config = window.wshAineLiveScore || {};
	var ajaxUrl = config.ajaxUrl || '';
	var nonce = config.nonce || '';
	var interval = parseInt(config.interval, 10) || 15000;

	if (!ajaxUrl || !nonce) {
		return;
	}

	function refreshBox(box) {
		if (!box || box.getAttribute('data-live') !== '1') {
			return;
		}

		var body = new FormData();
		body.append('action', 'wsh_aine_refresh_live_score');
		body.append('nonce', nonce);
		body.append('sport', box.getAttribute('data-sport') || 'football');
		body.append('league_id', box.getAttribute('data-league-id') || '0');
		body.append('event_id', box.getAttribute('data-event-id') || '0');
		body.append('title', box.getAttribute('data-title') || '1');

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data) {
					return;
				}

				var isLive = !!payload.data.is_live;
				box.setAttribute('data-live', isLive ? '1' : '0');

				if (payload.data.html) {
					box.innerHTML = payload.data.html;
				}

				if (!isLive && box._wshLiveTimer) {
					window.clearInterval(box._wshLiveTimer);
					box._wshLiveTimer = null;
				}
			})
			.catch(function () {});
	}

	function initBox(box) {
		if (box._wshLiveReady) {
			return;
		}

		box._wshLiveReady = true;

		if (box.getAttribute('data-live') !== '1') {
			return;
		}

		box._wshLiveTimer = window.setInterval(function () {
			refreshBox(box);
		}, interval);
	}

	function init() {
		var boxes = document.querySelectorAll('.wsh-aine-live-score-shortcode');
		for (var i = 0; i < boxes.length; i++) {
			initBox(boxes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
