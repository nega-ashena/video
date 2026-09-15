/**
 * فیلتر تب‌بندی + لود بیشتر گرید ویدیوها.
 * بدون jQuery، بدون متغیر سراسری.
 */
(function () {
	'use strict';

	var config = window.negvGrid || {};

	function fetchVideos(page, category, onSuccess, onError) {
		var url = config.restUrl + 'grid/load-more?page=' + page + '&per_page=' + (config.perPage || 12);
		if (category) {
			url += '&category=' + encodeURIComponent(category);
		}

		fetch(url, {
			headers: { 'X-WP-Nonce': config.nonce || '' }
		})
			.then(function (res) {
				if (!res.ok) throw new Error(res.statusText);
				return res.json();
			})
			.then(onSuccess)
			.catch(onError);
	}

	function initGridWrap(wrap) {
		var grid = wrap.querySelector('.negv-video-grid');
		var tabs = wrap.querySelectorAll('.negv-video-tab');
		var currentPage = 1;
		var maxPages = parseInt(wrap.getAttribute('data-max-pages') || '1', 10);
		var activeCategory = wrap.getAttribute('data-category') || '';
		var isLoading = false;

		function getLoadMoreBtn() {
			return wrap.querySelector('.negv-load-more-btn');
		}

		function updateLoadMoreBtn() {
			var btn = getLoadMoreBtn();
			if (!btn) return;
			if (currentPage >= maxPages) {
				btn.classList.add('is-hidden');
			} else {
				btn.classList.remove('is-hidden');
				btn.classList.remove('is-loading');
				btn.disabled = false;
				btn.textContent = config.i18n && config.i18n.loadMore ? config.i18n.loadMore : 'Load More';
			}
		}

		function setLoading(loading) {
			var btn = getLoadMoreBtn();
			if (!btn) return;
			isLoading = loading;
			btn.classList.toggle('is-loading', loading);
			btn.disabled = loading;
			if (!loading) {
				btn.textContent = config.i18n && config.i18n.loadMore ? config.i18n.loadMore : 'Load More';
			}
		}

		function showEmpty(show) {
			var empty = wrap.querySelector('.negv-video-empty');
			if (empty) empty.hidden = !show;
		}

		function appendCards(html) {
			var temp = document.createElement('div');
			temp.innerHTML = html;
			var cards = temp.querySelectorAll('.negv-video-card');
			cards.forEach(function (card) {
				grid.appendChild(card);
			});
		}

		function loadMore() {
			if (isLoading) return;
			isLoading = true;
			setLoading(true);

			fetchVideos(
				currentPage + 1,
				activeCategory,
				function (data) {
					if (data.html) {
						appendCards(data.html);
					}
					currentPage = data.page || currentPage + 1;
					maxPages = data.max_pages || maxPages;
					wrap.setAttribute('data-page', currentPage);
					wrap.setAttribute('data-max-pages', maxPages);
					isLoading = false;
					updateLoadMoreBtn();
				},
				function () {
					isLoading = false;
					setLoading(false);
					if (config.i18n && config.i18n.error) {
						alert(config.i18n.error);
					}
				}
			);
		}

		function switchTab(tab) {
			if (isLoading) return;
			if (tab.classList.contains('is-active')) return;

			var filter = tab.getAttribute('data-filter');

			tabs.forEach(function (t) {
				t.classList.remove('is-active');
				t.setAttribute('aria-selected', 'false');
			});

			tab.classList.add('is-active');
			tab.setAttribute('aria-selected', 'true');

			isLoading = true;
			currentPage = 1;
			activeCategory = filter === '*' ? '' : filter;
			wrap.setAttribute('data-page', '1');
			wrap.setAttribute('data-category', activeCategory);

			fetchVideos(
				1,
				activeCategory,
				function (data) {
					grid.innerHTML = data.html || '';
					currentPage = 1;
					maxPages = data.max_pages || 1;
					wrap.setAttribute('data-max-pages', maxPages);
					showEmpty(!data.html || data.html.trim() === '');
					isLoading = false;
					updateLoadMoreBtn();
				},
				function () {
					isLoading = false;
					updateLoadMoreBtn();
					if (config.i18n && config.i18n.error) {
						alert(config.i18n.error);
					}
				}
			);
		}

		// Event delegation on the wrap for load more button clicks
		wrap.addEventListener('click', function (e) {
			var btn = e.target.closest('.negv-load-more-btn');
			if (btn) {
				e.preventDefault();
				loadMore();
			}
		});

		// Tab click handlers
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				switchTab(tab);
			});
		});

		// Keyboard navigation for tabs
		var tabList = wrap.querySelector('.negv-video-tabs');
		if (tabList && tabs.length) {
			tabList.addEventListener('keydown', function (e) {
				if (
					e.key !== 'ArrowLeft' &&
					e.key !== 'ArrowRight' &&
					e.key !== 'ArrowHome' &&
					e.key !== 'ArrowEnd'
				) {
					return;
				}

				var current = wrap.querySelector('.negv-video-tab.is-active');
				var idx = Array.prototype.indexOf.call(tabs, current);

				if (e.key === 'ArrowRight') {
					idx = (idx + 1) % tabs.length;
				} else if (e.key === 'ArrowLeft') {
					idx = (idx - 1 + tabs.length) % tabs.length;
				} else if (e.key === 'ArrowHome') {
					idx = 0;
				} else if (e.key === 'ArrowEnd') {
					idx = tabs.length - 1;
				}

				e.preventDefault();
				tabs[idx].focus();
				tabs[idx].click();
			});
		}

		// Initial state
		showEmpty(false);
	}

	var wraps = document.querySelectorAll('.negv-video-grid-wrap');
	wraps.forEach(initGridWrap);
})();
