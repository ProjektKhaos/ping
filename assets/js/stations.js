'use strict';

(() => {
  const P = window.PFW;
  const stationCodes = [...document.querySelectorAll('.station-card[data-station-code]')]
    .map(node => node.dataset.stationCode);
  const mapDataNodes = [...document.querySelectorAll('[data-map-station]')];
  const mapStations = new Map(mapDataNodes.map(node => [node.dataset.code, {...node.dataset}]));
  const leafletMarkers = new Map();
  let leafletMap;
  let selectedMapCode = document.body.dataset.defaultStation || 'P.1';
  try { selectedMapCode = localStorage.getItem('pfw-map-station') || selectedMapCode; } catch (_) {}
  if (!mapStations.has(selectedMapCode)) selectedMapCode = P.homeStation(stationCodes);

  const setText = (id, value) => {
    const node = document.getElementById(id);
    if (node) node.textContent = value;
  };

  const renderHomeStation = () => {
    const selected = P.homeStation(stationCodes);
    document.querySelectorAll('.home-station-button').forEach(button => {
      const active = button.dataset.code === selected;
      button.setAttribute('aria-pressed', String(active));
      const label = button.querySelector('[data-label]');
      if (label) label.textContent = active ? button.dataset.homeLabel : button.dataset.setLabel;
      button.closest('.station-card')?.classList.toggle('is-home', active);
    });
  };

  const markerIcon = station => {
    const active = station.code === selectedMapCode ? ' is-selected' : '';
    const role = ['upstream', 'primary', 'downstream'].includes(station.role) ? station.role : 'upstream';
    const freshness = ['live', 'delayed', 'stale', 'offline'].includes(station.freshness) ? station.freshness : 'offline';
    const code = String(station.code).replace(/[^A-Za-z0-9.]/g, '');
    return window.L.divIcon({
      className: `leaflet-station-icon role-${role} freshness-${freshness}${active}`,
      html: `<strong>${code}</strong><span aria-hidden="true"></span>`,
      iconSize: [64, 44], iconAnchor: [32, 22], popupAnchor: [0, -22],
    });
  };

  const popupContent = station => {
    const wrapper = document.createElement('div');
    wrapper.className = 'station-map-popup';
    const title = document.createElement('strong'); title.textContent = station.code;
    const name = document.createElement('span'); name.textContent = station.name;
    const reading = document.createElement('span');
    reading.textContent = `${station.gauge === '' ? '—' : Number(station.gauge).toFixed(2) + ' m'} · ${station.freshnessLabel}`;
    const link = document.createElement('a');
    link.href = station.detailUrl; link.textContent = document.getElementById('station-map')?.dataset.detailsLabel || 'View details';
    wrapper.append(title, name, reading, link);
    return wrapper;
  };

  const updateLeafletMarker = code => {
    const station = mapStations.get(code); const marker = leafletMarkers.get(code);
    if (!station || !marker) return;
    marker.setIcon(markerIcon(station));
    marker.setPopupContent(popupContent(station));
  };

  const renderMapSummary = code => {
    const station = mapStations.get(code);
    if (!station) return;
    const previous = selectedMapCode;
    selectedMapCode = code;
    try { localStorage.setItem('pfw-map-station', code); } catch (_) {}
    setText('map-summary-role', station.roleLabel);
    setText('map-summary-title', `${station.code} · ${station.name}`);
    setText('map-summary-gauge', station.gauge === '' ? '—' : Number(station.gauge).toFixed(2));
    setText('map-summary-trend', station.trend || '—');
    setText('map-summary-time', station.time || '—');
    const latitude = Number(station.latitude); const longitude = Number(station.longitude);
    setText('map-summary-coordinates', Number.isFinite(latitude) && Number.isFinite(longitude)
      ? `${latitude.toFixed(5)}, ${longitude.toFixed(5)}` : '—');
    const freshness = document.getElementById('map-summary-freshness');
    if (freshness) {
      freshness.className = `badge badge-${station.freshness || 'offline'}`;
      freshness.textContent = station.freshnessLabel || '—';
    }
    const homeButton = document.getElementById('map-home-station-button');
    if (homeButton) homeButton.dataset.code = station.code;
    const link = document.getElementById('map-summary-link');
    if (link) link.href = station.detailUrl;
    if (previous !== code) updateLeafletMarker(previous);
    updateLeafletMarker(code);
    renderHomeStation();
  };

  const initializeMap = () => {
    if (leafletMap) { window.setTimeout(() => leafletMap.invalidateSize(), 0); return; }
    const element = document.getElementById('station-map');
    if (!element || !window.L || mapStations.size === 0) return;
    leafletMap = window.L.map(element, {zoomControl: true, attributionControl: false, preferCanvas: true});
    leafletMap.attributionControl = window.L.control.attribution({position: 'topright', prefix: false}).addTo(leafletMap);
    const attributionUrl = element.dataset.attributionUrl || 'https://www.openstreetmap.org/copyright';
    const tileLayer = window.L.tileLayer(element.dataset.tileUrl, {
      maxZoom: Number(element.dataset.maxZoom) || 18,
      attribution: `&copy; <a href="${attributionUrl}" target="_blank" rel="noopener">OpenStreetMap contributors</a>`,
      crossOrigin: true,
    });
    let tileErrors = 0;
    tileLayer.on('tileerror', () => {
      tileErrors++;
      if (tileErrors < 3 || element.querySelector('.map-tile-error')) return;
      const notice = document.createElement('p'); notice.className = 'map-tile-error'; notice.textContent = element.dataset.tileError;
      element.append(notice);
    });
    tileLayer.addTo(leafletMap);

    const markerLayer = window.L.markerClusterGroup({
      maxClusterRadius: 42, disableClusteringAtZoom: 13,
      showCoverageOnHover: false, spiderfyOnMaxZoom: true, zoomToBoundsOnClick: true,
      removeOutsideVisibleBounds: false,
      iconCreateFunction: cluster => window.L.divIcon({
        html: `<div><span>${cluster.getChildCount()}</span></div>`,
        className: 'marker-cluster pfw-marker-cluster', iconSize: [44, 44],
      }),
    });
    mapStations.forEach(station => {
      const latitude = Number(station.latitude); const longitude = Number(station.longitude);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
      const marker = window.L.marker([latitude, longitude], {
        icon: markerIcon(station), title: station.selectLabel, alt: station.selectLabel,
        keyboard: true, riseOnHover: true, zIndexOffset: station.role === 'primary' ? 1000 : 0,
      });
      marker.bindPopup(popupContent(station), {closeButton: true, minWidth: 190});
      marker.on('click', () => renderMapSummary(station.code));
      leafletMarkers.set(station.code, marker);
      markerLayer.addLayer(marker);
    });
    markerLayer.addTo(leafletMap);
    leafletMap.fitBounds(markerLayer.getBounds(), {padding: [28, 28], maxZoom: 12});
    window.setTimeout(() => leafletMap.invalidateSize(), 0);
  };

  document.querySelectorAll('.home-station-button').forEach(button => button.addEventListener('click', () => {
    P.setHomeStation(button.dataset.code);
    renderHomeStation();
  }));

  const renderView = view => {
    const selected = view === 'map' ? 'map' : 'list';
    document.querySelectorAll('[data-station-view]').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.stationView === selected));
    });
    document.querySelectorAll('[data-view-panel]').forEach(panel => {
      panel.hidden = panel.dataset.viewPanel !== selected;
    });
    try { localStorage.setItem('pfw-station-view', selected); } catch (_) {}
    if (selected === 'map') { renderMapSummary(selectedMapCode); initializeMap(); }
  };
  document.querySelectorAll('[data-station-view]').forEach(button => {
    button.addEventListener('click', () => renderView(button.dataset.stationView));
  });
  let preferredView = 'list';
  try { preferredView = localStorage.getItem('pfw-station-view') || preferredView; } catch (_) {}
  renderView(preferredView);
  renderMapSummary(selectedMapCode);
  renderHomeStation();

  const refresh = async () => {
    try {
      const response = await fetch(`${P.base}api/stations.php?lang=${encodeURIComponent(P.language)}`, {cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error('STATIONS_API_FAILED');
      payload.data.forEach(station => {
        const card = document.querySelector(`.station-card[data-station-code="${CSS.escape(station.code)}"]`);
        if (card) {
          const set = (field, value) => { const node = card.querySelector(`[data-field="${field}"]`); if (node) node.textContent = value; };
          set('gauge', station.measurement.water_level_gauge_m === null ? '—' : Number(station.measurement.water_level_gauge_m).toFixed(2));
          set('trend', P.trend(station.trends.change_1h_m));
          set('time', P.localTime(station.measurement.measured_at));
          const freshness = card.querySelector('[data-field="freshness"]');
          if (freshness) { freshness.className = `badge badge-${station.measurement.freshness}`; freshness.textContent = station.measurement.freshness_label; }
        }

        const mapStation = mapStations.get(station.code);
        if (!mapStation) return;
        Object.assign(mapStation, {
          name: station.name,
          gauge: station.measurement.water_level_gauge_m === null ? '' : String(station.measurement.water_level_gauge_m),
          trend: P.trend(station.trends.change_1h_m),
          time: P.localTime(station.measurement.measured_at),
          freshness: station.measurement.freshness,
          freshnessLabel: station.measurement.freshness_label,
        });
        updateLeafletMarker(station.code);
      });
      renderMapSummary(selectedMapCode);
      P.setApiFailure('stations', false);
      return true;
    } catch (_) {
      P.setApiFailure('stations', true);
      return false;
    }
  };
  P.createRefreshTask(refresh, {intervalMs: 300000, resumeThresholdMs: 60000}).run(true);
})();
