(() => {
  const config = window.simpleLMSPlayer;
  if (!config?.lessonId || !config.loggedIn) return;

  const completed = new Set();
  let pending = false;
  let status;

  function getStatus() {
    if (status) return status;
    status = document.querySelector('[data-simple-lms-progress-status]');
    if (!status) {
      status = document.createElement('div');
      status.setAttribute('data-simple-lms-progress-status', '');
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      document.body.append(status);
    }
    return status;
  }

  function setStatus(message, state = '') {
    const element = getStatus();
    element.textContent = message;
    element.dataset.state = state;
  }

  async function saveCompletion() {
    if (pending || completed.has(config.lessonId)) return;
    pending = true;
    setStatus(config.messages.saving, 'saving');

    try {
      const response = await fetch(config.restUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({
          lesson_id: config.lessonId,
          course_id: config.courseId,
        }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || config.messages.error);
      }

      completed.add(config.lessonId);
      setStatus(
        payload.progress?.complete ? config.messages.complete : config.messages.saved,
        'saved',
      );

      if (payload.verification_url) {
        const link = document.createElement('a');
        link.href = payload.verification_url;
        link.textContent = 'Open certificate form';
        link.dataset.simpleLmsVerification = '';
        getStatus().append(' ', link);
      }

      document.dispatchEvent(
        new CustomEvent('simple_lms:lesson-completed', {
          detail: payload,
        }),
      );
    } catch (error) {
      console.error('[SimpleLMS]', error);
      setStatus(config.messages.error, 'error');
    } finally {
      pending = false;
    }
  }

  document.addEventListener(config.eventName || 'endedMedia', saveCompletion);

  function watchNativeMedia(root = document) {
    root.querySelectorAll('presto-player').forEach((player) => {
      player.shadowRoot?.querySelectorAll('video').forEach((video) => {
        if (video.dataset.simpleLmsBound === 'true') return;
        video.dataset.simpleLmsBound = 'true';
        video.addEventListener('ended', saveCompletion, { once: true });
      });
    });
  }

  if (window.customElements?.whenDefined) {
    window.customElements.whenDefined('presto-player').then(() => watchNativeMedia()).catch(() => {});
  }
  watchNativeMedia();

  const observer = new MutationObserver(() => watchNativeMedia());
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
