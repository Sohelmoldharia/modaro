/*
 * MangazScans chrome — interactions for the new header/footer.
 *
 *   - dark/light schema toggle (persisted in localStorage; flips the
 *     body's text-ui-* class without a page reload)
 *   - mobile drawer open/close (hamburger, scrim, close button, Esc)
 *   - header search expand/collapse
 *
 * Intentionally written in vanilla JS so it has no dependency on
 * jQuery (which may or may not have loaded by the time this file
 * executes). Enqueued after jquery just in case, and wrapped in IIFE
 * for scope isolation.
 */

(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function flipSchema(body, preferred) {
		// Madara's class names are inverted from intuitive:
		//   text-ui-light = dark mode, text-ui-dark = light mode.
		body.classList.remove('text-ui-light', 'text-ui-dark');
		body.classList.add(preferred === 'dark' ? 'text-ui-light' : 'text-ui-dark');
	}

	ready(function () {
		var body = document.body;
		if (!body) return;

		/* ---- dark/light toggle ---- */
		var toggles = document.querySelectorAll('.mz-schema-toggle');
		toggles.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var isDark = body.classList.contains('text-ui-light')
					|| !body.classList.contains('text-ui-dark');
				var next = isDark ? 'light' : 'dark';
				flipSchema(body, next);
				try { localStorage.setItem('mz-schema', next); } catch (_) {}
			});
		});

		/* ---- mobile drawer ---- */
		var drawer = document.getElementById('mz-drawer');
		var burger = document.querySelector('.mz-hamburger');
		var closeBtn = drawer ? drawer.querySelector('.mz-drawer__close') : null;
		var scrim = drawer ? drawer.querySelector('.mz-drawer__scrim') : null;

		function openDrawer() {
			if (!drawer || !burger) return;
			drawer.classList.add('is-open');
			drawer.setAttribute('aria-hidden', 'false');
			burger.setAttribute('aria-expanded', 'true');
			body.style.overflow = 'hidden';
		}
		function closeDrawer() {
			if (!drawer || !burger) return;
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			burger.setAttribute('aria-expanded', 'false');
			body.style.overflow = '';
		}

		if (burger) burger.addEventListener('click', openDrawer);
		if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
		if (scrim) scrim.addEventListener('click', closeDrawer);

		/* ---- header search expand ---- */
		var searchToggle = document.querySelector('.mz-search-toggle');
		var searchForm = document.getElementById('mz-search');
		if (searchToggle && searchForm) {
			searchToggle.addEventListener('click', function (e) {
				e.preventDefault();
				var open = !searchForm.classList.contains('is-open');
				searchForm.classList.toggle('is-open', open);
				searchForm.setAttribute('aria-hidden', open ? 'false' : 'true');
				searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (open) {
					var input = searchForm.querySelector('input[type="search"]');
					if (input) setTimeout(function () { input.focus(); }, 40);
				}
			});
		}

		/* ---- Esc closes drawer or search ---- */
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') return;
			if (drawer && drawer.classList.contains('is-open')) closeDrawer();
			if (searchForm && searchForm.classList.contains('is-open')) {
				searchForm.classList.remove('is-open');
				if (searchToggle) searchToggle.setAttribute('aria-expanded', 'false');
			}
		});
	});
})();
