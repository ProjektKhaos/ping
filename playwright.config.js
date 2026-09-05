const {defineConfig, devices} = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/E2E',
  timeout: 30000,
  expect: {timeout: 7000},
  reporter: [['list'], ['html', {open: 'never'}]],
  use: {
    baseURL: process.env.PFW_BASE_URL || 'https://ping.aberg.online',
    browserName: 'chromium',
    ignoreHTTPSErrors: false,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure'
  },
  projects: [
    {name: 'mobile-360', use: {...devices['Desktop Chrome'], viewport: {width: 360, height: 800}, deviceScaleFactor: 1}},
    {name: 'mobile-390', use: {...devices['Desktop Chrome'], viewport: {width: 390, height: 844}, deviceScaleFactor: 1}},
    {name: 'mobile-430', use: {...devices['Desktop Chrome'], viewport: {width: 430, height: 932}, deviceScaleFactor: 1}}
  ]
});
