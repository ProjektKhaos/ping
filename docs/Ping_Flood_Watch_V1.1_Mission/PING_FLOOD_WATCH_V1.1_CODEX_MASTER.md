# Ping Flood Watch V1.1 — Complete Codex Mission

**Use this file if you want to give Codex the entire mission in one go.**


---

# FILE: 00_README.md

# Ping Flood Watch — V1.1 Improvement Mission Package

**Mission version:** 1.00  
**Target:** Codex  
**Project:** Ping Flood Watch  
**Source reviewed:** `ping.zip`  
**Live site:** https://ping.aberg.online/  
**Scope:** Improve the existing application. Do **not** rebuild working V1 functionality.

## Purpose

This package contains a focused V1.1 mission based on review of the current Ping Flood Watch codebase.

The current architecture is good and should be preserved. The mission focuses on the most useful gaps and polish items:

1. real Web Push notifications,
2. upstream station auto-refresh on Home,
3. Station Detail and Alerts auto-refresh,
4. upstream rapid-rise early-warning logic,
5. configuration-driven freshness instead of hardcoded SQL values,
6. collector/cron watchdog in health status,
7. remove hardcoded provider names from risk/dashboard logic,
8. preserve query parameters when changing language,
9. separate current-data refresh from chart-history refresh,
10. distinguish offline/network failure from backend/API failure,
11. increase the smallest UI fonts,
12. make bottom-navigation icons more colorful, polished and easier to scan,
13. improve Open-Meteo response/unit validation,
14. refresh immediately when the app returns from background.

## Important rule

Do not redesign or replace the existing application architecture.

Keep:

- PHP 8.2+,
- MariaDB/MySQL,
- PDO,
- current repository/service/provider structure,
- Chart.js,
- PWA/service worker,
- English + Thai localization,
- portable `BASE_URL` behavior,
- current River Risk / Weather Risk / Combined Advisory separation.

## Mission files

- `01_MASTER_MISSION.md` — main implementation mission
- `02_PUSH_NOTIFICATIONS.md` — Web Push implementation
- `03_REFRESH_RESILIENCE.md` — auto-refresh, offline/error handling and visibility refresh
- `04_UPSTREAM_EARLY_WARNING.md` — P.67/P.103 rapid-rise signal
- `05_UI_TYPOGRAPHY_NAV.md` — larger small fonts and improved colorful navigation
- `06_HEALTH_CONFIG_PROVIDER.md` — freshness, health watchdog and provider abstraction
- `07_MISC_FIXES.md` — language URL, chart/current decoupling and provider validation
- `08_TEST_ACCEPTANCE.md` — required tests and Definition of Done

## Recommended execution

Give Codex `01_MASTER_MISSION.md` first.

The detailed files exist so Codex can inspect exact requirements when implementing each section.

---

# FILE: 01_MASTER_MISSION.md

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

---

# FILE: 02_PUSH_NOTIFICATIONS.md

# Mission Detail — Real Web Push Notifications

**Priority:** P0

---

# 1. Problem

The current app creates and stores alerts and alert events, but users receive no notification if the PWA/browser is closed.

The app must be able to actively warn an opted-in user.

---

# 2. Architecture

Use this flow:

```text
RiskCoordinator
    ↓
AlertManager
    ↓
persisted alert / alert_event
    ↓
NotificationDispatcher
    ↓
Web Push provider
    ↓
Service Worker
    ↓
Mobile notification
```

Do not send push directly from `WarningEngine` or provider code.

---

# 3. User opt-in

Add a clear user-controlled notification setting.

Example UI:

```text
Flood alerts
[ Enable notifications ]
```

After enabled:

```text
Flood alerts
✓ Notifications enabled
[ Disable ]
```

Rules:

- never request browser permission immediately on first page load,
- permission request must follow a user gesture,
- show useful text explaining what will be sent,
- handle denied permission gracefully,
- do not nag after denial.

---

# 4. Service worker

Extend `sw.js` with:

```javascript
self.addEventListener('push', ...)
self.addEventListener('notificationclick', ...)
```

Notification payload should support:

```text
title
body
severity
alert_id
event_type
url
tag
timestamp
```

Use a stable notification `tag` to prevent duplicates where appropriate.

Click should open/focus:

```text
alerts.php
```

or the appropriate alert URL within the current PWA scope.

Must remain portable under a subdirectory `BASE_URL`.

---

# 5. Server-side Web Push

Use a maintained PHP Web Push implementation.

Preferred:

```text
minishlink/web-push
```

if compatible with the project's PHP version and dependency constraints.

Do not implement Web Push cryptography manually.

Store VAPID keys outside the repository.

Suggested config:

```php
'push' => [
    'enabled' => false,
    'subject' => 'mailto:...',
    'public_key' => '',
    'private_key' => '',
],
```

`config.example.php` must contain placeholders only.

---

# 6. Database

Add an idempotent `push_subscriptions` table.

Suggested fields:

```text
id
endpoint_hash
endpoint
p256dh
auth
content_encoding
language
user_agent
created_at
updated_at
last_success_at
failure_count
disabled_at
```

Unique:

```text
endpoint_hash
```

Do not log the full endpoint unnecessarily.

Add a delivery/deduplication table, for example:

```text
push_deliveries
```

Fields:

```text
id
alert_event_id
subscription_id
status
http_status
error_code
attempted_at
delivered_at
```

Unique pair:

```text
(alert_event_id, subscription_id)
```

This prevents repeat delivery.

---

# 7. Alert transitions that should notify

At minimum:

```text
opened
escalated
cleared
```

Optional:

```text
deescalated
```

Do not notify on every same-severity `touch/update`.

Recommended behavior:

- new WATCH → notify,
- WATCH → WARNING → notify,
- WARNING → CRITICAL → notify,
- CRITICAL → WARNING may optionally notify,
- cleared → notify once,
- normal collector refresh with no transition → no notification.

---

# 8. Notification wording

Use localized strings.

English examples:

```text
Ping Flood Watch — Watch
River/weather conditions need attention.
```

```text
Ping Flood Watch — Warning
Flood potential has increased. Open the app for current details.
```

```text
Ping Flood Watch — Critical
Critical river-risk conditions are being reported by the monitoring system.
```

Clear:

```text
Ping Flood Watch
The active warning has cleared.
```

Do not claim official evacuation instructions unless supplied by an official source.

---

# 9. Failed subscriptions

Handle Web Push responses indicating expired/invalid subscription.

For 404/410-style permanent failures:

- mark subscription disabled,
- do not keep retrying forever.

Temporary failure:

- increment failure count,
- retain subscription,
- log concise error.

---

# 10. API

Add protected-by-design same-origin endpoints:

```text
POST api/push-subscribe.php
POST api/push-unsubscribe.php
```

Validate JSON body.

Subscription endpoint should accept only expected fields.

Do not expose private VAPID key.

Provide public VAPID key to frontend via safe endpoint/config/rendered data.

---

# 11. Security

- HTTPS required in production.
- Validate subscription structure.
- Limit request body size.
- No SQL string interpolation.
- Escape UI.
- Do not expose other users' subscriptions.
- Do not expose endpoint lists publicly.
- Consider basic same-origin/CSRF protection appropriate to the current app architecture.

---

# 12. Tests

Required:

- subscribe,
- duplicate subscribe updates existing subscription,
- unsubscribe,
- invalid subscription rejected,
- event delivery recorded,
- same event cannot be delivered twice to same subscription,
- expired subscription disabled,
- no push on same-severity refresh,
- push on open/escalation/clear,
- service worker handles push payload,
- notification click opens correct in-scope URL.

---

# FILE: 03_REFRESH_RESILIENCE.md

# Mission Detail — Refresh, Mobile Resume & Resilience

---

# 1. Home upstream rows

Current `app/views/home.php` renders upstream station rows as static server HTML.

Current `assets/js/home.js` updates the primary station and risk/weather values but not those upstream rows.

Add data hooks:

```text
data-station-code
data-field
```

or equivalent.

Refresh at least:

```text
gauge level
1h trend
freshness
measurement time
```

for P.67 and P.103.

If a station has no data:

```text
—
STALE/OFFLINE
```

Do not leave an old value looking current.

---

# 2. Station Detail auto-refresh

Current `assets/js/station.js` refreshes history only.

Add a lightweight current station API request periodically.

Update:

- current gauge level,
- MSL level,
- 1h / 3h / 6h / 12h / 24h changes,
- freshness badge,
- latest measurement timestamp,
- source situation if displayed.

Chart history may refresh less often if desired.

Suggested:

```text
current station state: every 5 min
chart: every 5–10 min
```

---

# 3. Alerts auto-refresh

Add:

```text
api/alerts.php
assets/js/alerts.js
```

or equivalent clean implementation.

The page should update recent alerts without full reload.

Use DOM patch/re-render only when data changes to avoid flicker.

---

# 4. Visibility/resume refresh

Create a shared helper in `assets/js/app.js` or per-page code.

Track:

```text
lastSuccessfulRefreshAt
```

On:

```javascript
visibilitychange
online
pageshow
```

if the page is visible and data is older than a small threshold, refresh immediately.

Suggested resume threshold:

```text
60 seconds
```

This threshold may be client-side constant with a clear comment.

---

# 5. Decouple Home API calls

Replace all-or-nothing `Promise.all()` behavior.

Current dashboard fetch and chart history must be independently handled.

Pseudo:

```javascript
const currentResult = await fetchCurrentSafely();
if (currentResult.ok) renderCurrent(...);

const historyResult = await fetchHistorySafely();
if (historyResult.ok) renderChart(...);
```

If current succeeds and history fails:

```text
keep live current data
show chart unavailable/stale indicator
```

If history succeeds and current fails:

```text
keep chart
show current update failed / cached current data
```

---

# 6. Distinguish states

Implement three concepts:

```text
NETWORK_OFFLINE
API_UPDATE_FAILED
DATA_STALE
```

Examples:

## No network

```text
OFFLINE
No network connection. Showing last stored data.
```

## Backend/API failed

```text
UPDATE FAILED
Could not refresh server data. Showing last valid values.
```

## Source measurement stale

```text
DATA STALE
Latest source measurement is older than the configured freshness limit.
```

These are not the same problem.

---

# 7. Cached snapshot

Keep the useful localStorage snapshot behavior.

Enhance snapshot metadata:

```text
storedAt
currentFetchedAt
historyFetchedAt
```

When displaying cache, always show a clear cached/old state.

Never display cached data as LIVE.

---

# 8. Loading behavior

Do not flash zeroes.

Do not blank valid existing data during refresh.

Prefer:

```text
existing values remain
small updating indicator if useful
replace only after successful response
```

---

# FILE: 04_UPSTREAM_EARLY_WARNING.md

# Mission Detail — Upstream Rapid-Rise Early Warning

**Priority:** P0

---

# 1. Current limitation

`app/Services/WarningEngine.php` already evaluates:

- primary P.1 level thresholds,
- ThaiWater source situation,
- upstream `high` / `overflow`,
- primary P.1 rise rate.

However rapid-rise thresholds are currently applied only to the primary station.

P.67/P.103 may begin rising significantly before P.1 reaches a high state.

---

# 2. Goal

Add an upstream trend signal without inventing hydrological certainty.

Possible states:

```text
normal
rising
rising_fast
unknown
```

This is an early-awareness signal, not a prediction of exact arrival time.

---

# 3. Configuration

Add separate configurable thresholds.

Example config keys:

```php
'alerts' => [
    ...
    'upstream_rise_watch_cm_per_hour' => null,
    'upstream_rise_warning_cm_per_hour' => null,
    'upstream_min_stations_for_multi_rise' => 2,
],
```

Production defaults may remain `null` if no verified thresholds exist.

Do not silently reuse primary-station thresholds unless explicitly documented.

---

# 4. WarningEngine behavior

For enabled non-primary stations:

- require fresh enough data,
- inspect `rate_1h_m_per_hour`,
- convert to cm/hour consistently,
- detect configured threshold crossings,
- add reason codes.

Suggested reason codes:

```text
RIVER_UPSTREAM_RISE_WATCH
RIVER_UPSTREAM_RISE_WARNING
RIVER_UPSTREAM_MULTI_STATION_RISE
```

Context should include:

```text
station code
station id
rate_cm_per_hour
change_1h_m
```

for affected stations.

---

# 5. Severity

Safe minimum policy:

- upstream rapid rise may elevate `normal` → `watch`,
- it must not automatically create `critical`,
- warning escalation should require a specifically configured upstream warning threshold or combined evidence,
- primary measured critical level always wins.

Do not claim:

```text
water will reach P.1 in X hours
```

unless a future verified travel-time model is added.

---

# 6. User-facing UI

On Home upstream rows, show trend clearly:

```text
P.67
0.81 m
↑ +22 cm / 1h
```

If configured rapid-rise signal is active:

```text
Rising fast
```

Use amber/orange, not red unless actual warning severity warrants red.

---

# 7. Advisory message

Example:

```text
Upstream rise detected.
P.67 is rising faster than the configured watch threshold while P.1 remains below warning level.
```

Keep uncertainty explicit.

---

# 8. Tests

Add unit cases:

- upstream stable,
- one upstream station over watch threshold,
- two upstream stations over watch threshold,
- upstream over warning threshold,
- stale upstream data ignored,
- null config means no rate-based upstream alert,
- primary warning remains stronger,
- primary critical remains stronger,
- reason/context contains correct upstream station.

---

# FILE: 05_UI_TYPOGRAPHY_NAV.md

# Mission Detail — Mobile Typography & Colorful Bottom Navigation

**User request:** Make the smallest fonts a little larger and make the menu icons more colorful and visually attractive.

Do this as a polish pass, not a redesign.

---

# 1. Typography goal

The current UI contains several 9–11 px user-facing text sizes.

Raise the small-text floor so the app is easier to read on a phone.

Do not make the layout bulky.

---

# 2. Specific current CSS to review

`assets/css/app.css` currently contains examples such as:

```text
.brand-copy small                  10px
.eyebrow / .kicker                 10px
.badge                             10px
.metric-grid span                  10px
.station-row small                 11px
.range-note                        11px
.attribution                       11px
.forecast-horizons span             9px
.forecast-horizons strong          10px
.updated                           11px
.threshold-list a                 11px
.page-footer                       10px
.bottom-nav a                      10px
chart ticks in JS                   9px
@media max-width:370 metric span    9px
```

---

# 3. Target sizes

Use these as starting targets, adjusting slightly if needed to avoid overflow:

```text
brand tagline                     11.5–12px
eyebrow/kicker                    11–12px
badges                            11px
metric labels                     11.5–12px
station secondary text            12px
range/attribution text            12px
forecast horizon labels           11px
forecast horizon values           11.5–12px
updated timestamp                 12px
threshold source links            12px
footer                            11.5–12px
bottom nav labels                 11.5–12px
chart ticks                       11px
```

Avoid visible UI text below approximately `11px`.

For Thai, ensure the chosen size remains comfortably readable.

---

# 4. Preserve hierarchy

Do not enlarge everything equally.

Keep:

- main level reading dominant,
- h1 prominent,
- risk headings clear,
- metadata secondary.

The purpose is to improve legibility of tiny text, not flatten the hierarchy.

---

# 5. Forecast card

The four forecast horizon cells are compact.

After font increase:

- ensure 6h / 12h / 24h / 48h still fit at 360 px,
- values like `123.4–145.7 mm` must not break the card badly,
- use responsive wrapping or slightly wider vertical layout if necessary,
- do not shrink the font back to 9px.

---

# 6. Chart ticks

Current chart ticks use 9 px in:

```text
assets/js/home.js
assets/js/station.js
```

Increase to approximately:

```text
11px
```

Reduce tick count if needed rather than making text tiny.

---

# 7. Bottom navigation visual polish

Current nav:

```text
Home
Stations
Alerts
```

with monochrome Material Symbols.

Keep the same three destinations.

Use existing Material Symbols Rounded.

Recommended icons:

```text
Home      home
Stations  waves or water_drop
Alerts    notifications_active
```

Do not introduce external icon libraries.

---

# 8. Color direction

Give each nav icon its own subtle identity:

```text
Home:
blue

Stations:
cyan / teal

Alerts:
amber / coral
```

Suggested concept:

```text
inactive icon:
soft colored rounded background
colored icon

active item:
slightly stronger colored icon background
stronger label color
subtle tinted item surface
```

Do not make inactive icons grey-only.

Do not use saturated red for Alerts unless it represents an actual warning state.

---

# 9. Suggested markup

Prefer explicit nav classes/data attributes rather than fragile `nth-child` styling.

Example:

```html
<a class="nav-item nav-home" ...>
    <span class="nav-icon"><span class="material-symbols">home</span></span>
    <span class="nav-label">Home</span>
</a>
```

Likewise:

```text
nav-stations
nav-alerts
```

This makes styling maintainable.

---

# 10. Icon containers

Suggested size:

```text
32–36px icon bubble
20–23px symbol inside
```

Touch target remains at least:

```text
44 × 44px
```

Do not reduce the current nav touch target.

---

# 11. Alert-aware icon enhancement

Optional but desirable:

If an active warning exists, Alerts nav may display a small badge/dot.

Example:

```text
●
```

Do not show a red dot when there is no active alert.

If this requires invasive global querying on every page, skip it unless cleanly available.

---

# 12. Accessibility

- color must not be the only active-state signal,
- retain `aria-current="page"`,
- ensure label contrast,
- icons remain `aria-hidden` if labels provide meaning,
- forced-colors mode must remain usable,
- touch target test must still pass.

---

# 13. Mobile QA

Test:

```text
360 × 800
390 × 844
430 × 932
```

No horizontal overflow.

Bottom nav must remain visually balanced.

Thai labels must fit.

---

# FILE: 06_HEALTH_CONFIG_PROVIDER.md

# Mission Detail — Health, Freshness & Provider Configuration

---

# 1. Freshness must use Config

Current values exist in `app/Config.php`:

```text
freshness.water_live_minutes
freshness.water_delayed_minutes
freshness.weather_current_minutes
freshness.weather_aging_minutes
```

But repository SQL independently hardcodes the same numbers.

Fix this.

---

# 2. Water repository

`app/Repositories/MeasurementRepository.php::stationsWithState()`

Replace hardcoded SQL values `75` and `120`.

Preferred safe implementation:

- use prepared query parameters for minute limits if supported cleanly,
- or calculate cutoff timestamps in PHP UTC and pass them as parameters.

Do not concatenate untrusted values into SQL.

---

# 3. Weather repository

`app/Repositories/ForecastRepository.php::states()`

Replace hardcoded `90` and `180` with configured values.

Same safety rules apply.

---

# 4. Provider selection

Use:

```php
Config::get('providers.water')
Config::get('providers.weather')
```

instead of hardcoded strings in services/APIs.

Review at minimum:

```text
app/Services/RiskCoordinator.php
app/Services/DashboardService.php
api/weather.php
```

Also search the repository for:

```text
'thaiwater'
'openmeteo'
```

Distinguish legitimate provider implementation names from improper business-logic hardcoding.

---

# 5. Collector watchdog

Use existing:

```text
collector_runs
```

Expected collectors:

```text
water
weather
```

Inspect actual collector names generated by current cron code and use those real names.

Add config such as:

```php
'health' => [
    'water_collector_max_age_minutes' => 15,
    'weather_collector_max_age_minutes' => 45,
],
```

Choose defaults consistent with current schedules, with reasonable buffer.

---

# 6. Health API

Enhance `api/health.php`.

Return structured state:

```json
{
  "status": "ok|degraded",
  "database": "ok",
  "providers": [...],
  "collectors": [
    {
      "name": "water",
      "status": "ok|stale|failed|missing",
      "last_started_at": "...",
      "last_finished_at": "...",
      "age_minutes": 7
    }
  ]
}
```

Degraded when:

- DB fails,
- required provider has recent failures,
- provider success is too old,
- collector last run is too old,
- collector latest status failed,
- collector has never run.

Do not expose credentials or internal paths.

---

# 7. Provider health age

Add configurable provider success-age thresholds or derive them from collector expectations.

A provider health row with:

```text
consecutive_failures = 0
last_success_at = 2 days ago
```

must not be `ok`.

---

# 8. Status API

Review `DashboardService::status()` and `api/status.php`.

Avoid duplicating conflicting health logic.

If possible, create one reusable health evaluator/service.

Suggested:

```text
app/Services/HealthService.php
```

so both status/health output use the same rules.

---

# 9. Tests

- config override changes water freshness behavior,
- config override changes weather freshness behavior,
- healthy recent collector,
- stale water collector,
- stale weather collector,
- failed latest collector,
- provider old last success,
- provider consecutive failure,
- no collector history,
- provider name comes from config.

---

# FILE: 07_MISC_FIXES.md

# Mission Detail — Smaller Correctness Fixes

---

# 1. Preserve query parameters in language_url()

Current:

```php
function language_url(string $language): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? url(), PHP_URL_PATH) ?: url();
    return $path . '?lang=' . rawurlencode($language);
}
```

Problem:

```text
station.php?code=P.67&lang=en
```

can become:

```text
station.php?lang=th
```

and lose `code=P.67`.

Implement robust query preservation.

Requirements:

- parse current URI,
- preserve all existing non-language query values,
- replace only `lang`,
- build query with `http_build_query`,
- preserve project/subdirectory portability,
- escape only at output, not inside helper incorrectly.

Unit-test it.

---

# 2. Open-Meteo validation

`app/Providers/OpenMeteoProvider.php`

Validate response metadata when present.

Expected request:

```text
hourly precipitation
hourly precipitation_probability
hourly weather_code
timezone Asia/Bangkok
```

Check relevant `hourly_units`.

Expected:

```text
precipitation = mm
precipitation_probability = %
```

If metadata is missing:

- do not invent it,
- decide whether current API contract can safely proceed,
- document behavior.

If metadata exists and is inconsistent:

- reject with clear `ProviderException`.

Suggested error codes:

```text
OPENMETEO_UNIT_INVALID
OPENMETEO_TIMEZONE_INVALID
OPENMETEO_SCHEMA_INVALID
OPENMETEO_VALUE_INVALID
```

---

# 3. Probability validation

When a probability is numeric:

```text
0 <= value <= 100
```

If outside range, reject or null according to a documented provider policy.

Prefer reject because it indicates malformed upstream data.

---

# 4. Date validation

Do not let invalid timestamps silently normalize into unexpected PHP dates.

Catch date parsing errors and throw provider schema exception.

---

# 5. Array consistency

Current code validates `time` and `precipitation` lengths.

Also validate arrays used by index:

```text
precipitation_probability
weather_code
```

They may be optional, but if present and non-empty their length must match `time`.

---

# 6. Asset version bump

Current references include V1-style versions such as:

```text
app.css?v=1.0.2
app.js?v=1.0.2
home.js?v=1.0.2
service worker cache ping-flood-watch-shell-v1.0.2
```

After V1.1 frontend changes, update consistently.

Suggested:

```text
1.1.0
```

Do not leave mixed cache/version identifiers.

---

# 7. Packaging hygiene

Do not make this a blocker for app behavior, but update `.gitignore`/release guidance so source/deploy packages do not need to include:

```text
node_modules/
.phpunit.cache/
storage/logs/*.log
storage/locks/*
```

`vendor/` inclusion is a deployment choice; document it rather than deleting it blindly.

Do not remove dependencies from the live project as part of this mission.

---

# FILE: 08_TEST_ACCEPTANCE.md

# Ping Flood Watch V1.1 — Test & Acceptance Checklist

Codex must update `docs/TEST_REPORT.md`.

---

# A. Regression

- [ ] Existing PHPUnit suite passes.
- [ ] Existing Playwright suite passes.
- [ ] Home still loads real/current data.
- [ ] Thai locale still works.
- [ ] Charts still render.
- [ ] PWA service worker still registers.
- [ ] Offline shell still works.
- [ ] No horizontal overflow at tested mobile widths.

---

# B. Typography

At:

```text
360 × 800
390 × 844
430 × 932
```

- [ ] Smallest normal user-facing text is visibly larger than V1.
- [ ] No 9px chart ticks remain.
- [ ] Forecast cells do not overflow.
- [ ] Thai remains readable.
- [ ] Footer remains compact.
- [ ] No new horizontal scroll.

---

# C. Bottom navigation

- [ ] Home icon visually distinct and colored.
- [ ] Stations icon visually distinct and colored.
- [ ] Alerts icon visually distinct and colored.
- [ ] Active state is obvious without relying only on color.
- [ ] `aria-current` retained.
- [ ] Touch targets remain >= 44px.
- [ ] Forced-colors mode remains usable.

---

# D. Home refresh

- [ ] P.1 refreshes.
- [ ] P.67 refreshes.
- [ ] P.103 refreshes.
- [ ] Upstream freshness updates.
- [ ] Upstream trend updates.
- [ ] Weather refreshes.
- [ ] Risks refresh.
- [ ] History failure does not discard valid current data.
- [ ] Current-data failure does not erase a working chart.

---

# E. Resume behavior

- [ ] Background app for several minutes.
- [ ] Return to foreground.
- [ ] Data refreshes immediately.
- [ ] Going online triggers refresh.
- [ ] No duplicate overlapping refresh storm.

---

# F. Error state

- [ ] Device offline → OFFLINE state.
- [ ] Backend HTTP 500 while device online → UPDATE FAILED state, not OFFLINE.
- [ ] Source stale → DATA STALE state.
- [ ] Cached values are not labelled LIVE.

---

# G. Station page

- [ ] Current station values auto-refresh.
- [ ] Current measurement time auto-refreshes.
- [ ] Freshness auto-refreshes.
- [ ] Trends auto-refresh.
- [ ] Chart refreshes without breaking period selection.

---

# H. Alerts page

- [ ] New alert appears without full reload.
- [ ] Escalation updates.
- [ ] Cleared alert updates.
- [ ] No visible full-page flicker.
- [ ] Empty state transitions correctly when first alert arrives.

---

# I. Upstream rise

- [ ] Stable P.67/P.103 does not trigger.
- [ ] Configured P.67 rapid rise triggers upstream reason.
- [ ] Configured P.103 rapid rise triggers upstream reason.
- [ ] Multi-station rise reason works.
- [ ] Stale upstream station ignored.
- [ ] Null thresholds disable rate-based upstream warnings.
- [ ] P.1 critical remains strongest state.

---

# J. Freshness/config

- [ ] Water freshness values come from Config.
- [ ] Weather freshness values come from Config.
- [ ] Changing config in test changes behavior.
- [ ] No duplicate hardcoded 75/120 or 90/180 business rules remain.

---

# K. Provider abstraction

- [ ] RiskCoordinator uses configured water provider.
- [ ] RiskCoordinator uses configured weather provider.
- [ ] DashboardService uses configured providers.
- [ ] Weather API uses configured provider.
- [ ] Provider implementation classes still retain their legitimate names.

---

# L. Health

- [ ] Recently successful water collector = OK.
- [ ] Water collector not run within configured max age = degraded.
- [ ] Weather collector not run within configured max age = degraded.
- [ ] Latest collector failure = degraded.
- [ ] Old provider success = degraded.
- [ ] DB health works.
- [ ] API leaks no credentials.

---

# M. Language URL

- [ ] `/station.php?code=P.67&lang=en` → language switch retains `code=P.67`.
- [ ] Existing unrelated query parameters are retained.
- [ ] `lang` is replaced, not duplicated.

---

# N. Open-Meteo validation

- [ ] Valid mm/% units accepted.
- [ ] Wrong precipitation unit rejected.
- [ ] Wrong probability unit rejected.
- [ ] Invalid timezone metadata rejected where applicable.
- [ ] Invalid timestamp rejected.
- [ ] probability <0 rejected.
- [ ] probability >100 rejected.
- [ ] inconsistent hourly array lengths rejected.

---

# O. Web Push

- [ ] Permission requested only after user gesture.
- [ ] Subscription stored.
- [ ] Duplicate subscription does not duplicate rows.
- [ ] Unsubscribe works.
- [ ] New alert sends one push.
- [ ] Same unchanged alert does not send repeated push.
- [ ] Escalation sends push.
- [ ] Clear sends push.
- [ ] Expired push subscription becomes disabled.
- [ ] Notification click opens/focuses app.
- [ ] Push URL works under non-root BASE_URL.
- [ ] Private VAPID key is never exposed.
- [ ] Push setup documented.

---

# P. Cache/version

- [ ] CSS/JS version bumped consistently.
- [ ] Service worker cache version bumped.
- [ ] Old cache removed on activate.
- [ ] Reload receives new assets.

---

# Definition of Done

Mission is complete only when:

- all P0 items are implemented,
- all acceptance checks pass or are explicitly documented as blocked,
- `docs/TEST_REPORT.md` is updated,
- no existing V1 feature is knowingly broken,
- live/public deployment is not claimed successful unless actually tested.
