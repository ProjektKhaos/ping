# Mission: Ping Flood Watch V1.1 — Audit Fixes, Push Alerts & UI Polish

**Mission ID:** PFW-V1.1-001  
**Priority:** High  
**Target:** Existing Ping Flood Watch codebase  
**Do not rewrite the application.**

---

# 1. Goal

Improve the existing production-ready V1 application without changing its basic architecture.

The current app already has:

- ThaiWater water-level provider,
- Open-Meteo weather provider,
- river/weather/combined risk engines,
- alert lifecycle,
- PWA and offline shell,
- English/Thai localization,
- mobile UI,
- station/history APIs,
- health/status APIs,
- tests.

V1.1 should close the remaining operational and UX gaps.

---

# 2. Required work — priority order

## P0 — Must complete

### 2.1 Real Web Push notifications

The app currently persists alerts/events but does not notify the user when the app is closed.

Implement Web Push with:

- opt-in permission flow,
- VAPID configuration,
- subscription storage,
- service-worker `push` handling,
- notification click handling,
- delivery deduplication,
- disable/remove expired subscriptions,
- no repeated notification every collector cycle,
- notify on relevant alert lifecycle transitions.

Detailed requirements: `02_PUSH_NOTIFICATIONS.md`.

### 2.2 Fix Home upstream auto-refresh

Current `assets/js/home.js` refreshes:

- primary P.1 data,
- river/weather/combined risk,
- forecast values,
- chart.

But the upstream rows rendered in `app/views/home.php` are server-rendered and are not updated by `home.js`.

Fix this so P.67/P.103 values, trend/freshness and timestamp/status on the Home screen refresh with the rest of the dashboard.

Do not reload the entire page.

### 2.3 Upstream rapid-rise early warning

Current `app/Services/WarningEngine.php` uses source situation (`high` / `overflow`) for upstream stations, but rate-of-rise thresholds are applied only to the primary station.

Add configurable upstream rapid-rise detection.

Detailed requirements: `04_UPSTREAM_EARLY_WARNING.md`.

### 2.4 Health/cron watchdog

Current `api/health.php` mainly checks `provider_health.consecutive_failures`.

If a cron job stops running completely, no new provider failure is recorded and health may remain `ok`.

Add collector age/watchdog checks using existing `collector_runs`.

Health must become degraded when expected collectors have not run within configured time limits.

---

# 3. Required work — high value

## 3.1 Auto-refresh Station Detail

Current `assets/js/station.js` loads chart history on initial load and period change.

Add periodic refresh for:

- current level,
- freshness,
- trends,
- measurement time,
- threshold/state summary where applicable,
- chart.

Do not destroy usability while the user is interacting with the period selector.

## 3.2 Auto-refresh Alerts page

Current `alerts.php` / `app/views/alerts.php` is server-rendered and has no periodic JS refresh.

Implement an alerts JSON endpoint or extend an existing endpoint cleanly.

Refresh active/recent alerts automatically.

Avoid re-render flicker.

## 3.3 Refresh immediately after returning to the app

Mobile browsers suspend timers in the background.

Add:

```javascript
document.addEventListener('visibilitychange', ...)
```

When `document.visibilityState === 'visible'`:

- refresh current data immediately if last successful refresh is older than a small configurable/client-side threshold,
- do not wait up to five minutes for the interval.

Also refresh on `online`.

## 3.4 Separate current-data and history/chart failures

Current `assets/js/home.js` uses one `Promise.all()` for:

- `api/current.php`
- `api/history.php`

If history fails, valid current data is discarded and the cached snapshot may be shown instead.

Refactor:

- current dashboard data should succeed independently,
- chart history should succeed independently,
- if chart fails, keep current live values,
- if current fails but history works, show current-data error/stale state but preserve chart.

---

# 4. Configuration and architecture fixes

## 4.1 Remove hardcoded freshness thresholds from SQL

Current code contains hardcoded values:

`app/Repositories/MeasurementRepository.php`

```text
75 / 120 minutes
```

while `app/Config.php` already contains:

```text
freshness.water_live_minutes
freshness.water_delayed_minutes
```

Likewise:

`app/Repositories/ForecastRepository.php`

currently hardcodes:

```text
90 / 180 minutes
```

while config contains:

```text
freshness.weather_current_minutes
freshness.weather_aging_minutes
```

Use configuration consistently.

Do not maintain two competing sources of truth.

## 4.2 Remove hardcoded provider names from services

Current code explicitly calls:

```php
$health->get('thaiwater')
$health->get('openmeteo')
```

in areas including:

- `app/Services/RiskCoordinator.php`
- `app/Services/DashboardService.php`
- `api/weather.php`

Use configured provider names:

```text
providers.water
providers.weather
```

Provider changes should not require editing service logic.

## 4.3 Improve provider health semantics

A provider with zero consecutive failures but a very old `last_success_at` must not always be treated as healthy.

Health should account for:

- consecutive failures,
- last provider success age,
- collector age/status.

---

# 5. UX polish

## 5.1 Increase small UI font sizes

The existing design contains several 9–11 px labels.

Increase the smallest user-facing text while preserving the compact mobile layout.

Detailed targets: `05_UI_TYPOGRAPHY_NAV.md`.

## 5.2 Improve bottom navigation icons

Current bottom navigation is clean but visually plain.

Make Home / Stations / Alerts icons:

- a little more colorful,
- visually distinct,
- still professional,
- still consistent with the flood/weather visual language,
- not cartoonish,
- accessible in inactive and active states.

Use the existing Material Symbols Rounded font; do not add a large icon dependency.

Detailed requirements: `05_UI_TYPOGRAPHY_NAV.md`.

---

# 6. Smaller fixes

## 6.1 Preserve query parameters during language switch

Current `language_url()` in `app/helpers.php` rebuilds the URL as:

```text
current-path?lang=xx
```

This can discard parameters such as:

```text
station.php?code=P.67
```

Fix it so language changes preserve existing query parameters and only replace `lang`.

Example:

```text
/station.php?code=P.67&lang=en
→ Thai
/station.php?code=P.67&lang=th
```

## 6.2 Distinguish network offline from API/server failure

Do not show an `OFFLINE` banner merely because a backend endpoint returned 500.

Use distinct states such as:

```text
OFFLINE
No network connection. Showing last stored data.
```

and:

```text
UPDATE FAILED
Server data could not be refreshed. Showing last valid data.
```

The browser's `navigator.onLine` may be used only as one signal, not proof that the backend works.

## 6.3 Improve Open-Meteo schema/unit validation

Current provider verifies precipitation values are numeric but does not explicitly validate relevant response metadata/units.

Validate, where returned:

- precipitation unit is `mm`,
- precipitation probability is `%`,
- timezone is the requested/expected timezone,
- hourly arrays have consistent lengths,
- timestamps are valid,
- probability values are within sensible API bounds (0–100).

Fail clearly with provider-specific error codes.

Do not invent missing metadata.

---

# 7. Cache/version handling

After JS/CSS/service-worker changes:

- bump asset version from current V1 values,
- bump the service-worker cache name,
- ensure old cache gets removed on activation,
- avoid users remaining stuck on old JS/CSS.

Use one centrally documented V1.1 asset/cache version.

---

# 8. Files expected to be touched

Likely files include, but Codex should inspect before editing:

```text
app/Config.php
app/config.example.php
app/helpers.php

app/Repositories/MeasurementRepository.php
app/Repositories/ForecastRepository.php
app/Repositories/ProviderHealthRepository.php
app/Repositories/AlertRepository.php

app/Services/WarningEngine.php
app/Services/RiskCoordinator.php
app/Services/DashboardService.php
app/Services/AlertManager.php

app/Providers/OpenMeteoProvider.php

app/views/home.php
app/views/station.php
app/views/alerts.php
app/views/partials/header.php
app/views/partials/footer.php

api/current.php
api/stations.php
api/status.php
api/health.php
api/weather.php
```

Possible new files:

```text
api/alerts.php
api/push-subscribe.php
api/push-unsubscribe.php

app/Repositories/PushSubscriptionRepository.php
app/Services/PushNotificationService.php
app/Services/NotificationDispatcher.php

assets/js/alerts.js
assets/js/push.js
```

Existing frontend files likely touched:

```text
assets/css/app.css
assets/js/app.js
assets/js/home.js
assets/js/station.js
assets/js/stations.js
sw.js
manifest.webmanifest
```

Database:

```text
sql/schema.sql
```

Add new tables idempotently.

---

# 9. Do not do

Do not:

- rebuild the app in React/Vue/etc.,
- replace PHP/PDO,
- replace Chart.js,
- replace the current risk model wholesale,
- introduce desktop-first UI,
- hardcode project folder names,
- remove Thai localization,
- remove offline PWA support,
- create false deterministic flood predictions,
- spam notifications,
- send push notifications without explicit user opt-in.

---

# 10. Documentation

Update:

```text
README.md
docs/INSTALL.md
docs/API.md
docs/ALERTS.md
docs/TEST_REPORT.md
```

Add push setup instructions including:

- VAPID key generation,
- local config fields,
- HTTPS requirement,
- subscription behavior,
- how to test push safely.

Document all new config keys.

---

# Definition of Done

V1.1 is complete only when all required tests in `08_TEST_ACCEPTANCE.md` pass or blocked items are explicitly documented.
