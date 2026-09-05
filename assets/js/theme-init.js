'use strict';

(() => {
  let preference = 'system';
  try {
    const stored = localStorage.getItem('pfw-theme');
    if (['system', 'light', 'dark'].includes(stored)) preference = stored;
  } catch (_) {}
  const dark = preference === 'dark'
    || (preference === 'system' && window.matchMedia?.('(prefers-color-scheme: dark)').matches);
  document.documentElement.dataset.themePreference = preference;
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
})();
