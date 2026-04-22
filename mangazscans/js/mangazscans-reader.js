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

		// We add the toggle to BOTH header + footer nav bars so readers
		// see it at the top and at the bottom after they finish a chapter.
		var containers = document.querySelectorAll(
			'#manga-reading-nav-head .wp-manga-nav, ' +
			'#manga-reading-nav-foot .wp-manga-nav, ' +
			'.entry-header.header .wp-manga-nav, ' +
			'.entry-header.footer .wp-manga-nav'
		);

		containers.forEach(function (nav) {
			// Don't double-inject if we come back through ajax.
			if (nav.querySelector('.mz-style-toggle')) return;

			var toggle = buildToggle(current);

			// Try to drop the toggle into the left 'select-view' group so
			// it sits with the chapter / reading-style pickers. Fall back
			// to prepending to the whole nav.
			var target = nav.querySelector('.select-view');
			if (target) {
				target.appendChild(toggle);
			} else {
				nav.insertBefore(toggle, nav.firstChild);
			}

			// madara also ships its own .reading-style-select dropdown
			// inside .selectpicker_load — hide it per-instance since our
			// toggle replaces it. We do it locally so other plugins that
			// also scope by .selectpicker_load don't get clobbered.
			var madaraPicker = nav.querySelector('.selectpicker_load');
			if (madaraPicker) madaraPicker.style.display = 'none';
		});
	}

	ready(inject);
})();
