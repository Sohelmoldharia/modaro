/*
 * MangazScans — reader front-end toggle.
 *
 * Injects a prominent "List | Paged" segmented toggle into madara's
 * chapter nav bars so readers can pick how they want the chapter to
 * render without hunting through admin settings. The toggle just
 * navigates to the same URL with ?style=list or ?style=paged;
 * madara's own code handles honouring the query arg + persisting
 * the preference for logged-in users.
 *
 * Loaded only on is_manga_reading_page() via theme.php.
 * Zero dependencies (no jQuery required).
 */

(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function currentStyle() {
		// URL param wins, else read any existing madara dropdown, else 'list'.
		var p = new URLSearchParams(window.location.search);
		var fromUrl = p.get('style');
		if (fromUrl === 'list' || fromUrl === 'paged') return fromUrl;

		var sel = document.querySelector('.reading-style-select, select.reading-style-select');
		if (sel && (sel.value === 'list' || sel.value === 'paged')) return sel.value;

		// Default assumption when nothing says otherwise.
		var body = document.body.className || '';
		if (body.indexOf('manga-reading-paged-style') !== -1) return 'paged';
		return 'list';
	}

	function urlForStyle(style) {
		// Strip any trailing /p/{n}/ paged slug — madara adds that when
		// paged mode is active, and we want our toggle to drop it when
		// switching to list. Keep origin + pathname + search(minus style),
		// then append style=… and drop paged slug from pathname.
		var url = new URL(window.location.href);
		url.searchParams.set('style', style);

		// If going to list mode, strip the /p/N/ slug from pathname.
		if (style === 'list') {
			url.pathname = url.pathname.replace(/\/p\/\d+\/?$/i, '/');
		}
		return url.toString();
	}

	function buildToggle(current) {
		var wrap = document.createElement('div');
		wrap.className = 'mz-style-toggle';
		wrap.setAttribute('role', 'group');
		wrap.setAttribute('aria-label', 'Reading mode');

		['list', 'paged'].forEach(function (mode) {
			var a = document.createElement('a');
			a.className = 'mz-style-toggle__btn' + (current === mode ? ' is-active' : '');
			a.href = urlForStyle(mode);
			a.setAttribute('aria-pressed', current === mode ? 'true' : 'false');
			a.dataset.mode = mode;
			a.textContent = mode === 'list' ? 'All pages' : 'One by one';
			wrap.appendChild(a);
		});

		return wrap;
	}

	function inject() {
		var current = currentStyle();

		// Place the toggle as the FIRST child inside the outer reader-
		// nav wrapper (#manga-reading-nav-head / -foot), i.e. a sibling
		// that renders ABOVE .wp-manga-nav. That way it has its own
		// clean row and never fights with the host/volume/chapter/page
		// dropdowns for space.
		var wrappers = document.querySelectorAll(
			'#manga-reading-nav-head, ' +
			'#manga-reading-nav-foot, ' +
			'.entry-header.header, ' +
			'.entry-header.footer'
		);

		wrappers.forEach(function (wrap) {
			if (wrap.querySelector('.mz-style-toggle')) return; // idempotent

			var toggle = buildToggle(current);
			toggle.classList.add('mz-style-toggle--row');
			wrap.insertBefore(toggle, wrap.firstChild);

			// Madara's own 'List / Paged' dropdown is redundant now — hide
			// it locally so our toggle is the single clear control.
			var madaraPicker = wrap.querySelector('.selectpicker_load');
			if (madaraPicker) madaraPicker.style.display = 'none';
		});
	}

	ready(inject);
})();
