'use strict';

(() => {
  const P = window.PFW;
  const cacheKey = `pfw-home-snapshot-${P.language}`;
  let chart;
  let chartStationCode;
  let currentPayload;
  let currentStationCode = document.body.dataset.defaultStation || 'P.1';
  let historyTask;

  const text = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
  const risk = (name, value) => {
    const card = document.getElementById(`${name}-risk`);
    P.severityClass(card, value.severity);
    text(`${name}-risk-title`, value.severity_label);
    text(`${name}-message`, value.message);
  };
  const setFreshness = (node, state, label, cached = false) => {
    if (!node) return;
    const effective = cached ? 'offline' : (state || 'offline');
    node.className = `${node.classList.contains('badge-inline') ? 'badge badge-inline' : 'badge'} badge-${effective}`;
    node.textContent = cached ? (P.language === 'th' ? 'ออฟไลน์' : 'OFFLINE') : label;
  };
  const renderCurrent = (payload, cached = false) => {
    if (!payload?.data?.stations || !payload.data.risks) return;
    currentPayload = payload;
    const codes = payload.data.stations.map(station => station.code);
    currentStationCode = P.homeStation(codes);
    const primary = payload.data.stations.find(station => station.code === currentStationCode)
      || payload.data.stations.find(station => station.is_primary);
    if (primary) {
      const picker = document.getElementById('home-station-select');
      if (picker) picker.value = primary.code;
      text('primary-title', `${primary.code} · ${primary.name}`);
      text('primary-level', primary.measurement.water_level_gauge_m === null ? '—' : Number(primary.measurement.water_level_gauge_m).toFixed(2));
      text('primary-msl', P.level(primary.measurement.water_level_msl_m));
      text('primary-trend', P.trend(primary.trends.change_1h_m));
      text('primary-time', P.localTime(primary.measurement.measured_at));
      setFreshness(document.getElementById('primary-freshness'), primary.measurement.freshness, primary.measurement.freshness_label, cached);
      const card = document.querySelector('.level-card');
      const referenceLabel = document.getElementById('primary-reference-label');
      const referenceValue = document.getElementById('primary-reference-value');
      if (primary.is_primary) {
        if (referenceLabel) referenceLabel.textContent = referenceLabel.dataset.warningLabel;
        if (referenceValue) referenceValue.textContent = referenceValue.dataset.warningValue;
      } else {
        const key = {upstream: 'roleUpstream', primary: 'rolePrimary', downstream: 'roleDownstream'}[primary.river_role] || 'roleUpstream';
        if (referenceLabel) referenceLabel.textContent = referenceLabel.dataset.positionLabel;
        if (referenceValue) referenceValue.textContent = card?.dataset[key] || primary.river_role;
      }
    }

    const rapidStations = new Set((payload.data.risks.river.values?.upstream_trends || []).map(item => item.station_code));
    payload.data.stations.filter(station => !station.is_primary).forEach(station => {
      const row = document.querySelector(`[data-station-code="${CSS.escape(station.code)}"]`);
      if (!row) return;
      const field = name => row.querySelector(`[data-field="${name}"]`);
      field('gauge').textContent = P.level(station.measurement.water_level_gauge_m);
      field('trend').textContent = `${P.trend(station.trends.change_1h_m)} / 1h`;
      field('time').textContent = P.localTime(station.measurement.measured_at);
      setFreshness(field('freshness'), station.measurement.freshness, station.measurement.freshness_label, cached);
      const rapid = field('rapid-rise');
      if (rapid) rapid.hidden = !rapidStations.has(station.code);
    });

    risk('river', payload.data.risks.river);
    risk('weather', payload.data.risks.weather);
    P.severityClass(document.getElementById('combined-risk'), payload.data.risks.combined.severity);
    text('advisory-title', payload.data.risks.combined.severity_label);
    text('combined-message', payload.data.risks.combined.message);
    const context = payload.data.risks.weather.values || {};
    text('rain-range', context.rain_24h_min_mm === null || context.rain_24h_max_mm === null ? '—' : `${Number(context.rain_24h_min_mm).toFixed(1)}–${Number(context.rain_24h_max_mm).toFixed(1)} mm`);
    text('rain-peak', P.rain(context.max_hourly_rain_mm));
    text('rain-probability', context.max_probability_pct !== null && context.max_probability_pct !== '' && Number.isFinite(Number(context.max_probability_pct)) ? `${Math.round(Number(context.max_probability_pct))}%` : '—');
    [6, 12, 24, 48].forEach(hours => {
      const min = context[`rain_${hours}h_min_mm`]; const max = context[`rain_${hours}h_max_mm`];
      text(`rain-${hours}h`, min === null || max === null || min === undefined || max === undefined ? '—' : `${Number(min).toFixed(1)}–${Number(max).toFixed(1)} mm`);
    });
    const stale = document.getElementById('data-stale-banner');
    const staleWater = !primary || !['live', 'delayed'].includes(primary.measurement.freshness);
    const staleWeather = (payload.data.weather || []).some(zone => !['current', 'aging'].includes(zone.freshness));
    if (stale) stale.hidden = !cached && !staleWater && !staleWeather;
    const updated = document.getElementById('forecast-updated');
    if (updated) updated.textContent = updated.dataset.template.replace('{time}', P.localTime(context.forecast_received_at));
    const dashboardUpdated = document.getElementById('dashboard-updated');
    if (dashboardUpdated) dashboardUpdated.textContent = dashboardUpdated.dataset.template.replace('{time}', P.localTime(payload.meta?.generated_at));
  };

  const renderChart = points => {
    const canvas = document.getElementById('water-chart');
    const empty = document.getElementById('chart-empty');
    if (!canvas || !window.Chart) return;
    if (!points.length) { canvas.hidden = true; empty.hidden = false; return; }
    canvas.hidden = false; empty.hidden = true;
    const labels = points.map(point => P.localTime(point.measured_at));
    const values = points.map(point => point.water_level_gauge_m);
    const palette = P.chartPalette();
    if (chart) chart.destroy();
    chartStationCode = currentStationCode;
    chart = new Chart(canvas, {
      type: 'line', data: {labels, datasets: [{data: values, borderColor: palette.line, backgroundColor: palette.fill, borderWidth: 2.5, pointRadius: 0, pointHitRadius: 12, spanGaps: false, fill: true, tension: .28}]},
      options: {responsive: true, maintainAspectRatio: false, animation: false, interaction: {mode: 'index', intersect: false}, plugins: {legend: {display: false}}, scales: {x: {grid: {display: false}, ticks: {maxTicksLimit: 4, color: palette.tick, font: {size: 11}}}, y: {grid: {color: palette.grid}, ticks: {color: palette.tick, font: {size: 11}, callback: value => `${Number(value).toFixed(1)} m`}}}}
    });
  };

  const snapshot = () => {
    try { return JSON.parse(localStorage.getItem(cacheKey)) || {}; } catch (_) { return {}; }
  };
  const saveSnapshot = values => {
    const next = {...snapshot(), ...values, storedAt: new Date().toISOString()};
    localStorage.setItem(cacheKey, JSON.stringify(next));
  };

  const refreshCurrent = async () => {
    try {
      const response = await fetch(`${P.base}api/current.php?lang=${encodeURIComponent(P.language)}`, {cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error('CURRENT_API_FAILED');
      renderCurrent(payload);
      saveSnapshot({current: payload, currentFetchedAt: new Date().toISOString()});
      P.setApiFailure('home-current', false);
      return true;
    } catch (_) {
      P.setApiFailure('home-current', true);
      const cached = snapshot();
      if (cached.current && document.getElementById('primary-level')?.textContent.trim() === '—') renderCurrent(cached.current, true);
      return false;
    }
  };
  const refreshHistory = async () => {
    const status = document.getElementById('chart-status');
    const requestedStation = currentStationCode;
    try {
      const response = await fetch(`${P.base}api/history.php?station=${encodeURIComponent(requestedStation)}&period=24h`, {cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error('HISTORY_API_FAILED');
      if (requestedStation !== currentStationCode) return refreshHistory();
      renderChart(payload.data.points || []);
      saveSnapshot({history: payload, historyStation: requestedStation, historyFetchedAt: new Date().toISOString()});
      if (status) status.hidden = true;
      P.setApiFailure('home-history', false);
      return true;
    } catch (_) {
      if (status) status.hidden = false;
      P.setApiFailure('home-history', true);
      if (!chart) {
        const cached = snapshot();
        if (cached.history && cached.historyStation === currentStationCode) renderChart(cached.history.data?.points || []);
      }
      return false;
    }
  };

  const currentTask = P.createRefreshTask(refreshCurrent, {intervalMs: 300000, resumeThresholdMs: 60000});
  historyTask = P.createRefreshTask(refreshHistory, {intervalMs: 600000, resumeThresholdMs: 60000});
  document.getElementById('home-station-select')?.addEventListener('change', event => {
    P.setHomeStation(event.target.value);
    if (currentPayload) renderCurrent(currentPayload);
    if (chart && chartStationCode !== currentStationCode) {
      chart.destroy(); chart = null; chartStationCode = null;
      const canvas = document.getElementById('water-chart');
      const empty = document.getElementById('chart-empty');
      if (canvas) canvas.hidden = true;
      if (empty) empty.hidden = false;
    }
    historyTask.run(true);
  });
  currentTask.run(true).then(() => historyTask.run(true));
})();
