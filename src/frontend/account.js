import './account.css';

(() => {
  const root = document.querySelector('[data-simple-lms-account]');
  if (!root) return;

  const tabs = [...root.querySelectorAll('[data-slms-tab]')];
  const panels = [...root.querySelectorAll('[data-slms-panel]')];

  function activate(tab) {
    const target = tab.dataset.slmsTab;
    tabs.forEach((item) => {
      const selected = item === tab;
      item.setAttribute('aria-selected', String(selected));
      item.tabIndex = selected ? 0 : -1;
    });
    panels.forEach((panel) => {
      panel.hidden = panel.dataset.slmsPanel !== target;
    });
  }

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activate(tab));
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      let next = index;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      activate(tabs[next]);
      tabs[next].focus();
    });
  });
})();
