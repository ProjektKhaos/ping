const {test, expect} = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('refresh tasks block overlap and refresh after a 60-second resume', async ({page}) => {
  await page.goto('/?lang=en');
  const result = await page.evaluate(async () => {
    let calls = 0;
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    const overlapping = window.PFW.createRefreshTask(async () => { calls++; await gate; return true; }, {intervalMs: 3600000});
    const first = overlapping.run(true);
    const second = overlapping.run(true);
    await Promise.resolve();
    release();
    const overlapResults = await Promise.all([first, second]);
    const callsAfterOverlap = calls;

    const realNow = Date.now;
    let fakeNow = realNow();
    Date.now = () => fakeNow;
    let resumeCalls = 0;
    const resumable = window.PFW.createRefreshTask(async () => { resumeCalls++; return true; }, {intervalMs: 3600000, resumeThresholdMs: 60000});
    await resumable.run(true);
    fakeNow += 61000;
    window.dispatchEvent(new PageTransitionEvent('pageshow'));
    await new Promise(resolve => setTimeout(resolve, 30));
    Date.now = realNow;
    return {callsAfterOverlap, overlapResults, resumeCalls};
  });
  expect(result.callsAfterOverlap).toBe(1);
  expect(result.overlapResults).toEqual([true, false]);
  expect(result.resumeCalls).toBe(2);
});

test('current failure retains values while history succeeds independently', async ({page}) => {
  await page.route('**/api/current.php**', route => route.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({ok: false, error: {code: 'TEST_CURRENT', message: 'test'}})}));
  await page.goto('/?lang=en');
  await expect(page.locator('#offline-banner')).toHaveAttribute('data-state', 'api_update_failed');
  await expect(page.locator('#primary-level')).not.toHaveText('—');
  await expect(page.locator('#water-chart')).toBeVisible();
  await expect(page.locator('#chart-status')).toBeHidden();
});

test('history failure retains the last valid chart and reports its own failure', async ({page}) => {
  await page.goto('/?lang=en');
  await expect(page.locator('#water-chart')).toBeVisible();
  await page.route('**/api/history.php**', route => route.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({ok: false, error: {code: 'TEST_HISTORY', message: 'test'}})}));
  await page.evaluate(() => {
    const original = Date.now;
    Date.now = () => original() + 61000;
    window.dispatchEvent(new PageTransitionEvent('pageshow'));
  });
  await expect(page.locator('#chart-status')).toBeVisible();
  await expect(page.locator('#water-chart')).toBeVisible();
  await expect(page.locator('#primary-level')).not.toHaveText('—');
});

test('stale source data is distinct from network and API failure', async ({page}) => {
  await page.route('**/api/current.php**', async route => {
    const response = await route.fetch();
    const payload = await response.json();
    for (const station of payload.data.stations) {
      station.measurement.freshness = 'stale';
      station.measurement.freshness_label = 'STALE';
    }
    for (const zone of payload.data.weather) zone.freshness = 'stale';
    await route.fulfill({response, json: payload});
  });
  await page.goto('/?lang=en');
  await expect(page.locator('#data-stale-banner')).toBeVisible();
  await expect(page.locator('#primary-freshness')).toHaveClass(/badge-stale/);
  await expect(page.locator('#offline-banner')).toBeHidden();
});

test('Alerts patches only after API state changes and supports Thai', async ({page}) => {
  let revision = 1;
  await page.route('**/api/alerts.php**', async route => {
    const url = new URL(route.request().url());
    const thai = url.searchParams.get('lang') === 'th';
    const severity = revision === 1 ? 'watch' : 'warning';
    const data = {ok: true, data: {alerts: [{
      id: 99, severity, status: 'active', status_label: thai ? 'กำลังใช้งาน' : 'Active',
      title: revision === 1 ? (thai ? 'เฝ้าระวังน้ำท่วม' : 'Flood watch') : (thai ? 'เตือนภัยน้ำท่วม' : 'Flood warning'),
      message: thai ? 'ทดสอบ' : 'Test alert', triggered_at: '2026-08-21 00:00:00', pending_since: null,
    }]}, meta: {language: thai ? 'th' : 'en'}};
    await route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(data)});
  });
  await page.goto('/alerts.php?lang=th');
  await expect(page.getByRole('heading', {name: 'เฝ้าระวังน้ำท่วม'})).toBeVisible();
  revision = 2;
  await page.evaluate(() => {
    const original = Date.now;
    Date.now = () => original() + 61000;
    window.dispatchEvent(new PageTransitionEvent('pageshow'));
  });
  await expect(page.getByRole('heading', {name: 'เตือนภัยน้ำท่วม'})).toBeVisible();
  await expect(page.getByRole('heading', {name: 'เฝ้าระวังน้ำท่วม'})).toHaveCount(0);
});

test('notification permission is requested only by the Enable gesture', async ({page}) => {
  await mockPushPage(page);
  const requests = [];
  await page.route('**/api/push-subscribe.php', async route => {
    requests.push(JSON.parse(route.request().postData()));
    await route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({ok: true, data: {subscribed: true}, meta: {}})});
  });
  await page.route('**/api/push-unsubscribe.php', route => route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify({ok: true, data: {subscribed: false}, meta: {}})}));
  await page.goto('/alerts.php?lang=en');
  await expect(page.locator('#push-settings')).toHaveAttribute('data-state', 'not_requested');
  expect(await page.evaluate(() => window.__pushTest.permissionRequests)).toBe(0);
  await page.locator('#push-toggle').click();
  await expect(page.locator('#push-settings')).toHaveAttribute('data-state', 'subscribed');
  expect(await page.evaluate(() => window.__pushTest.permissionRequests)).toBe(1);
  expect(requests).toHaveLength(1);
  expect(requests[0].language).toBe('en');
  expect(Object.keys(requests[0]).sort()).toEqual(['contentEncoding', 'endpoint', 'keys', 'language']);
  await page.locator('#push-toggle').click();
  await expect(page.locator('#push-settings')).toHaveAttribute('data-state', 'not_requested');
  expect(await page.evaluate(() => window.__pushTest.unsubscribeCalls)).toBe(1);
});

test('iOS browser mode shows install guidance without requesting permission', async ({page}) => {
  await mockPushPage(page, true);
  await page.goto('/alerts.php?lang=en');
  await expect(page.locator('#push-settings')).toHaveAttribute('data-state', 'install_required');
  await expect(page.locator('#push-toggle')).toBeHidden();
  expect(await page.evaluate(() => window.__pushTest.permissionRequests)).toBe(0);
});

test('language switch preserves station and period query parameters', async ({page}) => {
  await page.goto('/station.php?code=P.1&period=48h&lang=en');
  await page.locator('.language-button').click();
  const url = new URL(page.url());
  expect(url.searchParams.get('code')).toBe('P.1');
  expect(url.searchParams.get('period')).toBe('48h');
  expect(url.searchParams.get('lang')).toBe('th');
});

test('push API rejects wrong method, content type and origin before disabled state', async ({request}) => {
  expect((await request.get('/api/push-subscribe.php')).status()).toBe(405);
  expect((await request.post('/api/push-subscribe.php', {headers: {'Content-Type': 'text/plain'}, data: '{}'})).status()).toBe(415);
  expect((await request.post('/api/push-subscribe.php', {headers: {'Content-Type': 'application/json', Origin: 'https://evil.example'}, data: {}})).status()).toBe(403);
  const configured = await request.post('/api/push-subscribe.php', {headers: {'Content-Type': 'application/json', Origin: new URL(process.env.PFW_BASE_URL || 'https://ping.aberg.online').origin}, data: {}});
  const configuredBody = await configured.json();
  expect([400, 503]).toContain(configured.status());
  expect(['INVALID_JSON', 'INVALID_SUBSCRIPTION', 'PUSH_DISABLED']).toContain(configuredBody.error.code);
});

test('small text, colored navigation, forced colors, and V1.1 assets meet the mobile gate', async ({page, request}) => {
  await page.goto('/?lang=en');
  const styles = await page.evaluate(() => ({
    kicker: parseFloat(getComputedStyle(document.querySelector('.kicker')).fontSize),
    metric: parseFloat(getComputedStyle(document.querySelector('.metric-grid span')).fontSize),
    nav: parseFloat(getComputedStyle(document.querySelector('.bottom-nav a')).fontSize),
    colors: [...document.querySelectorAll('.nav-icon')].map(node => getComputedStyle(node).color),
  }));
  expect(styles.kicker).toBeGreaterThanOrEqual(11);
  expect(styles.metric).toBeGreaterThanOrEqual(11);
  expect(styles.nav).toBeGreaterThanOrEqual(11);
  expect(new Set(styles.colors).size).toBeGreaterThanOrEqual(3);
  await page.emulateMedia({forcedColors: 'active'});
  expect(parseFloat(await page.locator('.card').first().evaluate(node => getComputedStyle(node).borderTopWidth))).toBeGreaterThanOrEqual(2);
  const html = await (await request.get('/?lang=en')).text();
  const worker = await (await request.get('/sw.js')).text();
  expect(html).toContain('v=1.2.3');
  expect(worker).toContain("const VERSION = '1.2.3'");
  expect(worker).not.toContain('1.0.2');
});

test('service worker localizes push payload and confines notification clicks to its scope', async () => {
  const source = fs.readFileSync(path.join(process.cwd(), 'sw.js'), 'utf8');
  const handlers = {};
  const shown = [];
  const navigated = [];
  const client = {url: 'https://ping.aberg.online/', navigate: async url => navigated.push(url), focus: async () => true};
  const context = {
    URL, Promise, Date, Number, caches: {open: async () => ({addAll: async () => {}}), keys: async () => [], match: async () => null},
    fetch: async () => ({}),
    self: {
      location: {href: 'https://ping.aberg.online/sw.js', origin: 'https://ping.aberg.online'},
      addEventListener: (name, handler) => { handlers[name] = handler; }, skipWaiting: async () => {},
      registration: {showNotification: async (title, options) => shown.push({title, options})},
      clients: {claim: async () => {}, matchAll: async () => [client], openWindow: async url => navigated.push(url)},
    },
  };
  vm.runInNewContext(source, context);
  let pending;
  handlers.push({data: {json: () => ({title: 'เตือนภัย', body: 'ข้อความ', url: 'https://evil.example/phish', tag: 'pfw-alert-1'})}, waitUntil: promise => { pending = promise; }});
  await pending;
  expect(shown[0].title).toBe('เตือนภัย');
  expect(new URL(shown[0].options.data.url, 'https://ping.aberg.online').pathname).toBe('/alerts.php');
  handlers.notificationclick({notification: {data: {url: 'https://evil.example/phish'}, close: () => {}}, waitUntil: promise => { pending = promise; }});
  await pending;
  expect(new URL(navigated[0]).origin).toBe('https://ping.aberg.online');
  expect(new URL(navigated[0]).pathname).toBe('/alerts.php');
});

async function mockPushPage(page, ios = false) {
  await page.route('**/alerts.php**', async route => {
    const response = await route.fetch();
    let body = await response.text();
    body = body.replace('data-enabled="false"', 'data-enabled="true"').replace('data-vapid-public-key=""', 'data-vapid-public-key="AQID"');
    await route.fulfill({response, body});
  });
  await page.addInitScript(isIos => {
    const state = window.__pushTest = {permission: 'default', permissionRequests: 0, subscribeCalls: 0, unsubscribeCalls: 0};
    const subscription = {
      endpoint: 'https://push.example.test/subscription/browser',
      toJSON: () => ({endpoint: 'https://push.example.test/subscription/browser', keys: {p256dh: 'cA', auth: 'YQ'}}),
      unsubscribe: async () => { state.unsubscribeCalls++; return true; },
    };
    const registration = {pushManager: {getSubscription: async () => null, subscribe: async () => { state.subscribeCalls++; return subscription; }}};
    Object.defineProperty(window, 'Notification', {configurable: true, value: {
      get permission() { return state.permission; },
      requestPermission: async () => { state.permissionRequests++; state.permission = 'granted'; return 'granted'; },
    }});
    Object.defineProperty(window, 'PushManager', {configurable: true, value: {supportedContentEncodings: ['aes128gcm']}});
    Object.defineProperty(navigator, 'serviceWorker', {configurable: true, value: {ready: Promise.resolve(registration), register: async () => registration}});
    if (isIos) {
      Object.defineProperty(navigator, 'userAgent', {configurable: true, value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari'});
      Object.defineProperty(navigator, 'platform', {configurable: true, value: 'iPhone'});
      Object.defineProperty(navigator, 'standalone', {configurable: true, value: false});
    }
  }, ios);
}
