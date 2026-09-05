'use strict';

(() => {
  const base = document.body.dataset.baseUrl || '/';
  const banner = document.getElementById('offline-banner');
  const apiFailures = new Set();

  const renderConnectionState = () => {
    const offline = navigator.onLine === false;
    document.documentElement.dataset.network = offline ? 'offline' : 'online';
    if (!banner) return;
    if (offline) {
      banner.textContent = banner.dataset.offline || 'OFFLINE';
      banner.dataset.state = 'network_offline';
      banner.hidden = false;
    } else if (apiFailures.size) {
      banner.textContent = banner.dataset.updateFailed || 'UPDATE FAILED';
      banner.dataset.state = 'api_update_failed';
      banner.hidden = false;
    } else {
      banner.hidden = true;
      delete banner.dataset.state;
    }
  };
  window.addEventListener('online', renderConnectionState);
  window.addEventListener('offline', renderConnectionState);
  renderConnectionState();

  const themeToggle = document.getElementById('theme-toggle');
  const themeMedia = window.matchMedia?.('(prefers-color-scheme: dark)');
  const themePreference = () => ['system', 'light', 'dark'].includes(document.documentElement.dataset.themePreference)
    ? document.documentElement.dataset.themePreference : 'system';
  const renderThemeButton = () => {
    if (!themeToggle) return;
    const preference = themePreference();
    const label = themeToggle.dataset[preference] || preference;
    const icon = themeToggle.querySelector('.material-symbols');
    if (icon) icon.textContent = {system: 'brightness_auto', light: 'light_mode', dark: 'dark_mode'}[preference];
    const accessible = (themeToggle.dataset.label || 'Theme: {mode}').replace('{mode}', label);
    themeToggle.setAttribute('aria-label', accessible);
    themeToggle.title = accessible;
  };
  renderThemeButton();
  themeToggle?.addEventListener('click', () => {
    const next = {system: 'dark', dark: 'light', light: 'system'}[themePreference()];
    try { localStorage.setItem('pfw-theme', next); } catch (_) {}
    document.documentElement.dataset.themePreference = next;
    document.documentElement.dataset.theme = next === 'dark'
      || (next === 'system' && themeMedia?.matches) ? 'dark' : 'light';
    renderThemeButton();
    window.location.reload();
  });
  themeMedia?.addEventListener?.('change', event => {
    if (themePreference() === 'system') document.documentElement.dataset.theme = event.matches ? 'dark' : 'light';
  });

  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register(`${base}sw.js`, {scope: base}).catch(() => {}));
  }

  window.PFW = {
    language: document.body.dataset.language || 'en',
    base,
    homeStation(codes = []) {
      const fallback = document.body.dataset.defaultStation || 'P.1';
      let stored = fallback;
      try { stored = localStorage.getItem('pfw-home-station') || fallback; } catch (_) {}
      return codes.includes(stored) ? stored : (codes.includes(fallback) ? fallback : codes[0]);
    },
    setHomeStation(code) {
      try { localStorage.setItem('pfw-home-station', code); } catch (_) {}
      window.dispatchEvent(new CustomEvent('pfw:homestationchange', {detail: {code}}));
    },
    chartPalette() {
      const styles = getComputedStyle(document.documentElement);
      const value = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;
      return {
        line: value('--blue', '#0c6db2'), fill: value('--chart-fill', 'rgba(12,109,178,.10)'),
        tick: value('--muted', '#596b78'), grid: value('--chart-grid', '#e7eef2'),
      };
    },
    localTime(value) {
      if (!value) return '—';
      const iso = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
      const locale = this.language === 'th' ? 'th-TH-u-nu-latn' : 'en-GB-u-nu-latn';
      try {
        return new Intl.DateTimeFormat(locale, {timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short'}).format(new Date(iso));
      } catch (_) { return '—'; }
    },
    level(value) { return value !== null && value !== '' && Number.isFinite(Number(value)) ? `${Number(value).toFixed(2)} m` : '—'; },
    rain(value) { return value !== null && value !== '' && Number.isFinite(Number(value)) ? `${Number(value).toFixed(1)} mm` : '—'; },
    trend(value) {
      if (value === null || value === '' || !Number.isFinite(Number(value))) return '—';
      const cm = Math.round(Number(value) * 100);
      return `${cm > 0 ? '↑ +' : cm < 0 ? '↓ ' : '→ '}${cm} cm`;
    },
    severityClass(element, severity) {
      if (!element) return;
      [...element.classList].filter(name => name.startsWith('severity-')).forEach(name => element.classList.remove(name));
      element.classList.add(`severity-${severity}`);
    },
    setApiFailure(source, failed) {
      if (failed) apiFailures.add(source); else apiFailures.delete(source);
      renderConnectionState();
    },
    createRefreshTask(callback, {intervalMs, resumeThresholdMs = 60000, onError = null} = {}) {
      let inFlight = false;
      let lastSuccessfulRefreshAt = 0;
      const run = async (force = false) => {
        if (inFlight) return false;
        if (!force && document.visibilityState === 'hidden') return false;
        inFlight = true;
        try {
          const result = await callback();
          if (result !== false) lastSuccessfulRefreshAt = Date.now();
          return result !== false;
        } catch (error) {
          if (typeof onError === 'function') onError(error);
          return false;
        } finally {
          inFlight = false;
        }
      };
      const resume = () => {
        if (document.visibilityState !== 'hidden' && Date.now() - lastSuccessfulRefreshAt >= resumeThresholdMs) run(true);
      };
      document.addEventListener('visibilitychange', resume);
      window.addEventListener('pageshow', resume);
      window.addEventListener('online', resume);
      if (Number.isFinite(intervalMs) && intervalMs > 0) window.setInterval(() => run(false), intervalMs);
      return {run, resume, get lastSuccessfulRefreshAt() { return lastSuccessfulRefreshAt; }};
    }
  };
})();
