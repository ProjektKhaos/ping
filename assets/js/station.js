'use strict';

(() => {
  const P = window.PFW;
  const canvas = document.getElementById('station-chart');
  const period = document.getElementById('history-period');
  const empty = document.getElementById('chart-empty');
  if (!canvas) return;
  const stationCode = canvas.dataset.station;
  let chart;

  const homeButton = document.getElementById('detail-home-button');
  const renderHomeButton = () => {
    if (!homeButton) return;
    let selected = document.body.dataset.defaultStation || 'P.1';
    try { selected = localStorage.getItem('pfw-home-station') || selected; } catch (_) {}
    const active = selected === homeButton.dataset.code;
    homeButton.setAttribute('aria-pressed', String(active));
    const label = homeButton.querySelector('[data-label]');
    if (label) label.textContent = active ? homeButton.dataset.homeLabel : homeButton.dataset.setLabel;
  };
  homeButton?.addEventListener('click', () => { P.setHomeStation(homeButton.dataset.code); renderHomeButton(); });
  renderHomeButton();

  const text = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
  const refreshCurrent = async () => {
    try {
      const response = await fetch(`${P.base}api/stations.php?lang=${encodeURIComponent(P.language)}`, {cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error('STATION_API_FAILED');
      const station = payload.data.find(item => item.code === stationCode);
      if (!station) throw new Error('STATION_NOT_FOUND');
      text('station-level', station.measurement.water_level_gauge_m === null ? '—' : Number(station.measurement.water_level_gauge_m).toFixed(2));
      text('station-msl', P.level(station.measurement.water_level_msl_m));
      text('station-capacity', station.measurement.capacity_percent !== null && station.measurement.capacity_percent !== '' && Number.isFinite(Number(station.measurement.capacity_percent)) ? `${Math.round(Number(station.measurement.capacity_percent))}%` : '—');
      text('station-time', P.localTime(station.measurement.measured_at));
      [1, 3, 6, 12, 24].forEach(hours => text(`station-trend-${hours}h`, P.trend(station.trends[`change_${hours}h_m`])));
      const freshness = document.getElementById('station-freshness');
      if (freshness) {
        freshness.className = `badge badge-${station.measurement.freshness}`;
        freshness.textContent = station.measurement.freshness_label;
      }
      P.setApiFailure('station-current', false);
      return true;
    } catch (_) {
      P.setApiFailure('station-current', true);
      return false;
    }
  };

  const renderChart = points => {
    if (!window.Chart) return;
    if (!points.length) {
      if (!chart) canvas.hidden = true;
      if (empty && !chart) empty.hidden = false;
      return;
    }
    canvas.hidden = false;
    if (empty) empty.hidden = true;
    if (chart) chart.destroy();
    const palette = P.chartPalette();
    chart = new Chart(canvas, {
      type: 'line',
      data: {labels: points.map(point => P.localTime(point.measured_at)), datasets: [{label: 'Gauge m', data: points.map(point => point.water_level_gauge_m), borderColor: palette.line, backgroundColor: palette.fill, borderWidth: 2.5, pointRadius: 0, pointHitRadius: 12, spanGaps: false, fill: true, tension: .25}]},
      options: {responsive: true, maintainAspectRatio: false, animation: false, interaction: {mode: 'index', intersect: false}, plugins: {legend: {display: false}}, scales: {x: {grid: {display: false}, ticks: {maxTicksLimit: 5, color: palette.tick, font: {size: 11}}}, y: {ticks: {color: palette.tick, font: {size: 11}, callback: value => `${Number(value).toFixed(1)} m`}, grid: {color: palette.grid}}}}
    });
  };

  const refreshChart = async () => {
    const status = document.getElementById('chart-status');
    try {
      const response = await fetch(`${P.base}api/history.php?station=${encodeURIComponent(stationCode)}&period=${encodeURIComponent(period.value)}`, {cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error('HISTORY_API_FAILED');
      renderChart(payload.data.points || []);
      if (status) status.hidden = true;
      P.setApiFailure('station-chart', false);
      return true;
    } catch (_) {
      if (status) status.hidden = false;
      P.setApiFailure('station-chart', true);
      return false;
    }
  };

  const currentTask = P.createRefreshTask(refreshCurrent, {intervalMs: 300000, resumeThresholdMs: 60000});
  const chartTask = P.createRefreshTask(refreshChart, {intervalMs: 600000, resumeThresholdMs: 60000});
  period?.addEventListener('change', () => chartTask.run(true));
  currentTask.run(true);
  chartTask.run(true);
})();
