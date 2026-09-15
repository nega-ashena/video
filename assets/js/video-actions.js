/**
 * دکمه‌های لایک و اشتراک‌گذاری ویدیو.
 * بدون jQuery، بدون متغیر سراسری، فقط یک آبجکت محلی wp_localize.
 */
(function () {
	'use strict';

	var likeButtons = document.querySelectorAll('.negv-action--like');

	if (likeButtons.length && window.negvActions) {
		likeButtons.forEach(function (likeButton) {
			likeButton.addEventListener('click', function () {
				var postId = likeButton.getAttribute('data-post-id');
				var countEl = likeButton.querySelector('.negv-action__count');

				if (!postId) {
					return;
				}

				likeButton.classList.add('is-loading');

				fetch(window.negvActions.restUrl + 'video/' + postId + '/like', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': window.negvActions.nonce
					}
				})
					.then(function (response) {
						return response.json().then(function (data) {
							return { ok: response.ok, status: response.status, data: data };
						});
					})
					.then(function (result) {
						likeButton.classList.remove('is-loading');

						if (result.status === 429) {
							likeButton.classList.add('is-locked');
							likeButton.setAttribute('aria-label', result.data.message || '');
							window.setTimeout(function () {
								likeButton.classList.remove('is-locked');
							}, 300000);
							return;
						}

						if (!result.ok) {
							throw new Error('Like request failed');
						}

						likeButton.classList.toggle('is-liked', result.data.liked);
						likeButton.setAttribute('aria-pressed', result.data.liked ? 'true' : 'false');

						if (countEl) {
							countEl.textContent = result.data.count;
						}
					})
					.catch(function () {
						likeButton.classList.remove('is-loading');
					});
			});
		});
	}

	var shareButtons = document.querySelectorAll('.negv-action--share');

	if (shareButtons.length) {
		shareButtons.forEach(function (shareButton) {
			shareButton.addEventListener('click', function () {
				var title = shareButton.getAttribute('data-title') || '';
				var url = shareButton.getAttribute('data-url') || window.location.href;

				if (navigator.share) {
					navigator.share({ title: title, url: url }).catch(function () {});
					return;
				}

				copyToClipboard(url);
				showFeedback(shareButton);
			});
		});
	}

	function showFeedback(button) {
		var original = button.innerHTML;
		var feedback = button.getAttribute('data-copied') || '';

		if (!feedback) {
			feedback = 'کپی شد!';
		}

		button.innerHTML = feedback;

		window.setTimeout(function () {
			button.innerHTML = original;
		}, 2000);
	}

	function copyToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).catch(function () {});
			return;
		}

		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
		} catch (error) {}

		document.body.removeChild(textarea);
	}
})();
