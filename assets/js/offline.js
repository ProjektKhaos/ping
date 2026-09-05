'use strict';

(() => {
  try {
    const language = document.body.dataset.language || 'en';
    const snapshot = JSON.parse(localStorage.getItem(`pfw-home-snapshot-${language}`));
    if (!snapshot || !snapshot.current) return;
    const primary = snapshot.current.data.stations.find(station => station.is_primary);
    const weather = snapshot.current.data.risks.weather.values || {};
    if (primary) document.getElementById('offline-level').textContent = primary.measurement.water_level_gauge_m === null ? '—' : Number(primary.measurement.water_level_gauge_m).toFixed(2);
    document.getElementById('offline-river').textContent = snapshot.current.data.risks.river.severity_label;
    document.getElementById('offline-rain').textContent = weather.rain_24h_min_mm === null || weather.rain_24h_max_mm === null ? '—' : `${Number(weather.rain_24h_min_mm).toFixed(1)}–${Number(weather.rain_24h_max_mm).toFixed(1)} mm`;
    document.getElementById('offline-stored').textContent = window.PFW.localTime(snapshot.storedAt);
    document.getElementById('offline-snapshot').hidden = false;
  } catch (_) {}
})();
