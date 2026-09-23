(function (window, document) {
	if (!window || !document) {
		return;
	}

	// Global koji free plugin već prosleđuje u cvc.js.
	if (typeof window.wshViewsCounter === 'undefined') {
		return;
	}

	var postId = parseInt(window.wshViewsCounter.post_id || 0, 10);
	if (!postId) {
		return;
	}

	function dispatchViewEvent() {
		var detail = { postId: postId };

		var ev;
		try {
			ev = new CustomEvent('wshViewsCounter:view', { detail: detail });
		} catch (e) {
			// IE fallback.
			ev = document.createEvent('CustomEvent');
			ev.initCustomEvent('wshViewsCounter:view', true, true, detail);
		}

		window.dispatchEvent(ev);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', dispatchViewEvent);
	} else {
		dispatchViewEvent();
	}
})(window, document);
