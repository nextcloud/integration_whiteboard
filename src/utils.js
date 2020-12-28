let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function detectBrowser() {
	// Opera 8.0+
	// eslint-disable-next-line
	if ((!!window.opr && !!opr.addons) || !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0) {
		return 'opera'
	}

	// Firefox 1.0+
	if (typeof InstallTrigger !== 'undefined') {
		return 'firefox'
	}

	// Chrome 1 - 79
	// eslint-disable-next-line
	if (!!window.chrome && (!!window.chrome.webstore || !!window.chrome.runtime)) {
		return 'chrome'
	}

	// Safari 3.0+ "[object HTMLElementConstructor]"
	// eslint-disable-next-line
	if (/constructor/i.test(window.HTMLElement) || (function (p) { return p.toString() === "[object SafariRemoteNotification]"; })(!window['safari'] || (typeof safari !== 'undefined' && safari.pushNotification))) {
		return 'safari'
	}

	// Internet Explorer 6-11
	// eslint-disable-next-line
	if (/*@cc_on!@*/false || !!document.documentMode) {
		return 'ie'
	}

	// Edge 20+
	// eslint-disable-next-line
	if (!isIE && !!window.StyleMedia) {
		return 'edge'
	}

	// Edge (based on chromium) detection
	// eslint-disable-next-line
	if (isChrome && (navigator.userAgent.indexOf("Edg") != -1)) {
		return 'edge-chromium'
	}

	// Blink engine detection
	// eslint-disable-next-line
	if ((isChrome || isOpera) && !!window.CSS) {
		return 'blink'
	}
}
