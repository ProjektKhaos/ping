const {test, expect} = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('home is responsive, live, charted, and accessible', async ({page}) => {
  await page.goto('/?lang=en');
  await expect(page.getByRole('heading', {name: 'River conditions'})).toBeVisible();
  await expect(page.locator('#primary-level')).not.toHaveText('—');
  await expect(page.locator('#water-chart')).toBeVisible();
  await expect(page.getByText('Upstream rainfall forecast')).toBeVisible();
  for (const hours of [6, 12, 24, 48]) await expect(page.locator(`#rain-${hours}h`)).not.toHaveText('—');
  await expect(page.locator('#forecast-updated')).not.toContainText('—');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  const results = await new AxeBuilder({page}).exclude('.material-symbols').analyze();
  expect(results.violations.filter(v => ['critical', 'serious'].includes(v.impact))).toEqual([]);
});

test('desktop uses the wide dashboard and header navigation', async ({page}) => {
  await page.setViewportSize({width: 1440, height: 1000});
  await page.goto('/?lang=en');
  await expect(page.locator('.desktop-nav')).toBeVisible();
  await expect(page.locator('.bottom-nav')).toBeHidden();
  const layout = await page.evaluate(() => {
    const shell = document.querySelector('.app-shell').getBoundingClientRect();
    const dashboard = getComputedStyle(document.querySelector('.dashboard-layout'));
    const main = document.querySelector('.dashboard-main').getBoundingClientRect();
    const aside = document.querySelector('.dashboard-aside').getBoundingClientRect();
    return {shellWidth: shell.width, columns: dashboard.gridTemplateColumns, mainLeft: main.left, asideLeft: aside.left};
  });
  expect(layout.shellWidth).toBeGreaterThan(1100);
  expect(layout.columns).not.toBe('none');
  expect(layout.asideLeft).toBeGreaterThan(layout.mainLeft);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(0);
});

test('Thai locale and primary navigation work', async ({page}) => {
  await page.goto('/?lang=th');
  await expect(page.locator('html')).toHaveAttribute('lang', 'th');
  await expect(page.getByRole('heading', {name: 'สถานการณ์แม่น้ำ'})).toBeVisible();
  await page.getByRole('link', {name: 'สถานี'}).last().click();
  await expect(page).toHaveURL(/stations\.php/);
  await expect(page.getByRole('heading', {name: 'สถานีวัดระดับน้ำ'})).toBeVisible();
  await page.getByRole('button', {name: 'แผนที่'}).click();
  await expect(page.getByRole('heading', {name: 'แผนที่สถานีแม่น้ำปิง'})).toBeVisible();
  await page.getByRole('button', {name: 'รายการ'}).click();
  await page.getByRole('link', {name: /P\.1/}).first().click();
  await expect(page).toHaveURL(/station\.php\?code=P\.1/);
  await expect(page.locator('#station-chart')).toBeVisible();
});

test('touch navigation targets are at least 44 pixels', async ({page}) => {
  await page.goto('/');
  const sizes = await page.locator('.bottom-nav a, .language-button, .theme-button').evaluateAll(nodes => nodes.map(node => ({width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height})));
  for (const size of sizes) {
    expect(size.width).toBeGreaterThanOrEqual(44);
    expect(size.height).toBeGreaterThanOrEqual(44);
  }
});

test('river stations follow north-to-south order', async ({page}) => {
  await page.goto('/stations.php?lang=en');
  await expect(page.locator('#stations-list-panel').getByText('NORTH / UPSTREAM')).toBeVisible();
  await expect(page.locator('#stations-list-panel').getByText('SOUTH / DOWNSTREAM')).toBeVisible();
  const codes = await page.locator('.station-card').evaluateAll(cards => cards.map(card => card.dataset.stationCode));
  expect(codes).toEqual(['P.67', 'P.103', 'CMI01', 'CMI02', 'P.1', 'FBP.2', 'CMI03', 'FBP.3', 'P.104']);
});

test('station map is interactive, attributed, accessible, and remembers its view', async ({page}) => {
  const externalRequests = [];
  const appOrigin = new URL(process.env.PFW_BASE_URL || 'https://ping.aberg.online').origin;
  page.on('request', request => {
    if (new URL(request.url()).origin !== appOrigin) externalRequests.push(request.url());
  });
  await page.goto('/stations.php?lang=en');
  expect(externalRequests).toEqual([]);
  await page.getByRole('button', {name: 'Map'}).click();
  await expect(page.locator('#stations-map-panel')).toBeVisible();
  await expect(page.locator('[data-map-station]')).toHaveCount(9);
  await expect(page.locator('.leaflet-marker-icon')).not.toHaveCount(0);
  await expect(page.locator('.leaflet-control-attribution')).toContainText('OpenStreetMap contributors');
  const geometry = await page.evaluate(() => {
    const map = document.getElementById('station-map').getBoundingClientRect();
    return [...document.querySelectorAll('.leaflet-marker-icon')].map(node => {
      const box = node.getBoundingClientRect();
      return {inside: box.left >= map.left && box.right <= map.right && box.top >= map.top && box.bottom <= map.bottom, width: box.width, height: box.height};
    });
  });
  for (const marker of geometry) {
    expect(marker.inside).toBe(true);
    expect(marker.width).toBeGreaterThanOrEqual(44);
    expect(marker.height).toBeGreaterThanOrEqual(44);
  }
  for (let index = 0; index < 7 && await page.locator('.leaflet-station-icon').count() < 9; index++) await page.getByTitle('Zoom in').click();
  await expect(page.locator('.leaflet-station-icon')).toHaveCount(9);
  await page.locator('.leaflet-station-icon[title^="Show FBP.2"]').click();
  await expect(page.locator('#map-summary-title')).toContainText('FBP.2');
  await expect(page.locator('#map-summary-gauge')).not.toHaveText('—');
  await expect(page.locator('#map-summary-link')).toHaveAttribute('href', /station\.php\?code=FBP\.2/);
  await page.locator('#map-home-station-button').click();
  await page.reload();
  await expect(page.locator('#stations-map-panel')).toBeVisible();
  for (let index = 0; index < 7 && await page.locator('.leaflet-station-icon').count() < 9; index++) await page.getByTitle('Zoom in').click();
  await expect(page.locator('.leaflet-station-icon[title^="Show FBP.2"]')).toHaveClass(/is-selected/);
  expect(externalRequests.some(url => url.startsWith('https://tile.openstreetmap.org/'))).toBe(true);
  expect(externalRequests.every(url => url.startsWith('https://tile.openstreetmap.org/'))).toBe(true);
  const results = await new AxeBuilder({page}).exclude('.material-symbols').analyze();
  expect(results.violations.filter(v => ['critical', 'serious'].includes(v.impact))).toEqual([]);
});

test('home station starts at P.1 and persists locally', async ({page}) => {
  await page.goto('/?lang=en');
  await expect(page.locator('#home-station-select')).toHaveValue('P.1');
  await page.locator('#home-station-select').selectOption('CMI02');
  await expect(page.locator('#primary-title')).toContainText('CMI02');
  await expect(page.locator('#primary-reference-value')).toHaveText('Upstream');
  await expect(page.locator('#water-chart')).toBeVisible();
  await page.reload();
  await expect(page.locator('#home-station-select')).toHaveValue('CMI02');
  await expect(page.locator('#primary-title')).toContainText('CMI02');
});

test('station list can set a home station and dark theme persists', async ({page}) => {
  await page.goto('/stations.php?lang=en');
  const card = page.locator('[data-station-code="P.104"]');
  await card.locator('.home-station-button').click();
  await expect(card).toHaveClass(/is-home/);
  await page.goto('/?lang=en');
  await expect(page.locator('#home-station-select')).toHaveValue('P.104');
  await page.locator('#theme-toggle').click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.locator('#combined-risk').evaluate(node => {
    [...node.classList].filter(name => name.startsWith('severity-')).forEach(name => node.classList.remove(name));
    node.classList.add('severity-normal');
  });
  const colors = await page.evaluate(() => ({
    body: getComputedStyle(document.body).backgroundColor,
    card: getComputedStyle(document.querySelector('.card')).backgroundColor,
    advisory: getComputedStyle(document.querySelector('#combined-risk')).backgroundColor,
    advisoryText: getComputedStyle(document.querySelector('#combined-risk')).color,
  }));
  expect(colors.body).not.toBe('rgb(245, 249, 251)');
  expect(colors.card).not.toBe('rgb(255, 255, 255)');
  expect(colors.advisory).toBe('rgb(23, 59, 45)');
  expect(colors.advisoryText).toBe('rgb(237, 246, 250)');
});

test('API validation has a stable error envelope', async ({request}) => {
  const response = await request.get('/api/history.php?station=invalid&period=24h');
  expect(response.status()).toBe(400);
  expect(await response.json()).toEqual({ok: false, error: {code: 'INVALID_STATION', message: 'The requested station is not supported.'}});
  const period = await request.get('/api/forecast.php?zone=P.67&period=7d');
  expect(period.status()).toBe(400);
  expect((await period.json()).error.code).toBe('INVALID_PERIOD');
});

test('service worker provides a clearly marked offline snapshot', async ({page, context}) => {
  await page.goto('/?lang=en');
  await expect.poll(async () => page.evaluate(() => Boolean(localStorage.getItem('pfw-home-snapshot-en')))).toBe(true);
  await page.evaluate(async () => { await navigator.serviceWorker.ready; });
  await page.reload();
  await expect.poll(async () => page.evaluate(() => Boolean(navigator.serviceWorker.controller))).toBe(true);
  await context.setOffline(true);
  await page.reload({waitUntil: 'domcontentloaded'});
  await expect(page.getByText('OFFLINE / showing last stored data')).toBeVisible();
  await expect(page.locator('#offline-snapshot')).toBeVisible();
  await context.setOffline(false);
});
