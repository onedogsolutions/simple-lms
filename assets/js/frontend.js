/**
 * Frontend JavaScript for SimpleLMS.
 *
 * Handles:
 *   - Lesson completion toggling (with completion redirect follow-through)
 *   - Video gating (enable the Complete button after N% watched)
 *   - Quiz countdown timer (disable the GF submit button on expiry)
 *
 * Vanilla JS, no dependencies.
 */
(function () {
	'use strict';

	/* ─── Complete button ──────────────────────────────────────────── */

	function initCompleteButtons() {
		var buttons = document.querySelectorAll('.slms-complete-toggle');

		buttons.forEach(function (button) {
			button.addEventListener('click', function (e) {
				e.preventDefault();

				var btn = e.currentTarget;

				if (btn.disabled) {
					return;
				}

				var data = {
					user_id: parseInt(btn.dataset.userId, 10),
					course_id: parseInt(btn.dataset.courseId, 10),
					lesson_id: parseInt(btn.dataset.lessonId, 10),
					completed: !btn.classList.contains('is-completed'),
				};

				btn.disabled = true;
				btn.style.opacity = '0.5';

				fetch(btn.dataset.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': btn.dataset.nonce,
					},
					body: JSON.stringify(data),
				})
					.then(function (response) {
						if (!response.ok) {
							throw new Error('Network response was not ok');
						}
						return response.json();
					})
					.then(function (result) {
						if (!result.success) {
							return;
						}

						if (data.completed) {
							btn.classList.add('is-completed', 'button-primary');
							btn.classList.remove('button-secondary');
						} else {
							btn.classList.remove(
								'is-completed',
								'button-primary'
							);
							btn.classList.add('button-secondary');
						}

						// Follow the course-completion redirect when present.
						if (data.completed && result.redirect) {
							window.location.href = result.redirect;
						}
					})
					.catch(function (error) {
						// eslint-disable-next-line no-console
						console.error(
							'SimpleLMS: Error toggling lesson completion.',
							error
						);
					})
					.finally(function () {
						btn.disabled = false;
						btn.style.opacity = '1';
					});
			});
		});
	}

	/* ─── Video gating ─────────────────────────────────────────────── */

	function initVideoGating() {
		var gated = document.querySelectorAll(
			'.slms-complete-toggle.is-gated'
		);

		if (!gated.length) {
			return;
		}

		function unlock(btn) {
			btn.disabled = false;
			btn.classList.remove('is-gated');
			var notice = btn.parentNode
				? btn.parentNode.querySelector('.slms-gate-notice')
				: null;
			if (notice) {
				notice.style.display = 'none';
			}
		}

		function attach(media, btn, threshold) {
			if (media.dataset.slmsGateBound) {
				return;
			}
			media.dataset.slmsGateBound = '1';

			media.addEventListener('timeupdate', function () {
				if (!media.duration || media.duration === Infinity) {
					return;
				}
				var pct = (media.currentTime / media.duration) * 100;
				if (pct >= threshold) {
					unlock(btn);
				}
			});

			// A completed 'ended' event always satisfies the gate.
			media.addEventListener('ended', function () {
				unlock(btn);
			});
		}

		gated.forEach(function (btn) {
			var threshold = parseInt(btn.dataset.videoGate, 10) || 100;

			// Presto Player loads its media asynchronously; poll briefly for the
			// underlying <video>/<audio> element and bind once found.
			var tries = 0;
			var poll = setInterval(function () {
				var media = document.querySelector(
					'.slms-lesson-video-container video, .slms-lesson-video-container audio, video, audio'
				);
				if (media) {
					attach(media, btn, threshold);
					clearInterval(poll);
				}
				if (++tries > 40) {
					// ~20s: give up polling. Fail open so students are never
					// permanently locked out by a missing player.
					clearInterval(poll);
					unlock(btn);
				}
			}, 500);
		});
	}

	/* ─── Quiz timer ───────────────────────────────────────────────── */

	function initQuizTimer() {
		var timer = document.querySelector('.slms-quiz-timer');

		if (!timer) {
			return;
		}

		var minutes = parseInt(timer.dataset.minutes, 10) || 0;
		if (minutes <= 0) {
			return;
		}

		var clock = timer.querySelector('.slms-quiz-timer-clock');
		var remaining = minutes * 60;

		function render() {
			var m = Math.floor(remaining / 60);
			var s = remaining % 60;
			if (clock) {
				clock.textContent =
					String(m).padStart(2, '0') +
					':' +
					String(s).padStart(2, '0');
			}
		}

		function expire() {
			timer.classList.add('is-expired');

			var container = timer.closest('.slms-lesson-quiz-container') || document;

			// Disable the Gravity Forms submit button(s).
			var submits = container.querySelectorAll(
				'.gform_button, input[type="submit"], button[type="submit"]'
			);
			submits.forEach(function (el) {
				el.disabled = true;
				el.setAttribute('aria-disabled', 'true');
			});

			var notice = container.querySelector('.slms-quiz-expired-notice');
			if (notice) {
				notice.hidden = false;
			}
		}

		render();

		var interval = setInterval(function () {
			remaining -= 1;
			if (remaining <= 0) {
				remaining = 0;
				render();
				clearInterval(interval);
				expire();
				return;
			}
			render();
		}, 1000);
	}

	/* ─── Boot ─────────────────────────────────────────────────────── */

	function init() {
		initCompleteButtons();
		initVideoGating();
		initQuizTimer();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
