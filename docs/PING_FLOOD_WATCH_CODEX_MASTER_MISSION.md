# Ping Flood Watch — Codex Master Mission

**Package version:** 1.01  
**Updated:** 2026-08-21 06:12 Asia/Bangkok  

This file combines the complete mission package for convenient use in Codex.


---

# FILE: 00_README.md

# Ping Flood Watch — Codex Mission Package

**Version:** 1.01  
**Created:** 2026-08-21  
**Target:** Mobile-only PWA/web app for Chiang Mai / Ping River flood monitoring  
**Primary design:** Figma — `Chiang Mai Ping Flood Watch — Mobile`  
**Figma file:** https://www.figma.com/design/nVxpoCWiaJMFVEG0Z4x4Rh

## Purpose

Build a production-ready first version of a mobile-only web app that lets a user quickly:

- see current Ping River water levels,
- see whether levels are rising or falling,
- compare upstream stations,
- view recent history,
- receive warnings when conditions become concerning,
- see forecast rainfall that may affect the Ping River,
- see a separate forward-looking Weather Risk for upstream rainfall,
- combine river measurements and forecast risk without presenting forecasts as certainty,
- install the app as a PWA on a mobile device.

The app should prioritize **fast situational awareness**. A user should be able to open the app and understand the current situation within a few seconds.

## Important implementation principles

1. **Mobile-only design**
   - Optimize for approximately 390 px mobile width.
   - Desktop does not need a separate layout.
   - Wider displays may center the mobile shell.

2. **Portable project**
   - Project directory is assumed to be the DocumentRoot.
   - Do not hardcode the project directory name or URL prefix.
   - Use a `BASE_URL` / `url()` helper or equivalent for links, assets, redirects and API endpoints.

3. **Data adapters**
   - Never bind the UI directly to one external data provider.
   - External water-data sources must sit behind provider adapters.
   - A provider outage must not crash the app.

4. **Do not guess flood thresholds**
   - Thresholds must be configurable.
   - Seed values may be used for development only and clearly marked as unverified.
   - Before production use, verify official station reference levels and warning thresholds.

5. **Traceability**
   - Store source name, station ID, timestamps, raw values and normalized values.
   - Never silently alter source measurements.

6. **Simple first**
   - Build a strong version 1 before adding unnecessary features.

## Suggested stack

- PHP 8.2+
- MariaDB / MySQL
- PDO
- Vanilla JavaScript or a lightweight JS layer
- Chart.js for history charts
- PWA: manifest + service worker
- Web Push as optional phase after the warning engine works reliably
- Cron for scheduled data collection

## Package contents

- `01_MISSION_BUILD_APP.md` — main Codex mission
- `02_ARCHITECTURE_AND_DATA.md` — application structure, DB and provider model
- `03_UI_UX_FIGMA.md` — mobile UI implementation requirements
- `04_ALERT_ENGINE.md` — warning and alert logic
- `05_API_PROVIDER_RESEARCH.md` — mandatory API validation mission
- `06_TEST_ACCEPTANCE.md` — QA and acceptance criteria
- `07_DEPLOYMENT_AND_OPERATIONS.md` — deployment, cron, logging and operations
- `08_WEATHER_FORECAST_AND_RISK.md` — mandatory V1 weather forecast and forecast-risk requirements

## Codex completion rule

Codex must not report the mission as complete until:

- the application runs,
- the data collector works with at least one verified provider or an explicitly documented mock provider,
- history is stored,
- the main mobile screen matches the Figma direction,
- warning status is calculated from configuration,
- forecast rainfall is collected from at least one verified weather provider or explicit mock provider,
- Weather Risk is calculated separately from River Risk,
- combined advisory logic is implemented without turning forecasts into false certainty,
- failure states are handled,
- installation instructions are documented,
- all acceptance criteria in `06_TEST_ACCEPTANCE.md` have been addressed.

---

# FILE: 01_MISSION_BUILD_APP.md

# Mission: Build Ping Flood Watch

**Mission ID:** PFW-001  
**Version:** 1.01  
**Priority:** High  
**Target:** Codex  
**Language:** Code/comments in English or clear Swedish; UI may initially be English as in the Figma design.

---

## Objective

Create a mobile-only flood monitoring PWA for Chiang Mai focused on the Ping River.

The first release must provide:

- current water level for the main station,
- latest measurement time,
- trend for the last hour,
- 24-hour chart,
- upstream station overview,
- flood status,
- warning state,
- automatic scheduled data collection,
- local history in MariaDB,
- robust provider-error handling,
- forecast rainfall for upstream/catchment areas,
- a separate Weather Risk indicator,
- combined advisory logic using both river observations and forecast rainfall.

The primary city station for the UI concept is:

- **P.1 — Nawarat Bridge**

The initial upstream overview should support:

- **P.67 — Mae Tae**
- **P.103 — Don Kaeo**
- **P.1 — Nawarat**

The internal architecture must allow additional stations without changing UI logic.

---

# 1. Repository structure

Create a clean structure similar to:

```text
/
├── index.php
├── stations.php
├── alerts.php
├── manifest.webmanifest
├── service-worker.js
├── .htaccess
│
├── app/
│   ├── config.php
│   ├── db.php
│   ├── helpers.php
│   ├── StationRepository.php
│   ├── MeasurementRepository.php
│   ├── AlertRepository.php
│   ├── WarningEngine.php
│   ├── WeatherRiskEngine.php
│   ├── AdvisoryEngine.php
│   └── Providers/
│       ├── WaterProviderInterface.php
│       ├── WeatherProviderInterface.php
│       ├── ThaiWaterProvider.php
│       ├── VerifiedWeatherProvider.php
│       ├── MockWaterProvider.php
│       └── MockWeatherProvider.php
│
├── api/
│   ├── current.php
│   ├── history.php
│   ├── stations.php
│   ├── weather.php
│   ├── forecast.php
│   └── status.php
│
├── cron/
│   ├── collect_water_levels.php
│   └── collect_weather_forecast.php
│
├── assets/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   ├── chart.js
│   │   └── pwa.js
│   └── icons/
│
├── sql/
│   ├── schema.sql
│   └── seed.sql
│
├── storage/
│   ├── logs/
│   └── cache/
│
└── docs/
    ├── API.md
    ├── INSTALL.md
    ├── PROVIDERS.md
    ├── WEATHER.md
    └── ALERTS.md
```

Adapt if necessary, but keep responsibilities separated.

---

# 2. Portability

The application must work regardless of whether it lives at:

```text
https://example.com/
```

or:

```text
https://example.com/flood/
```

Requirements:

- define `BASE_URL` centrally,
- create `url(string $path)` helper,
- no hardcoded `/flood/`,
- redirects must use the helper,
- asset URLs must use the helper,
- frontend API calls must receive a generated base path or use relative-safe URLs.

---

# 3. Main mobile dashboard

Implement the Figma screen direction.

Required hierarchy:

```text
Ping Flood Watch

Chiang Mai
P.1 · Nawarat Bridge
LIVE · NORMAL

1.34 m
↑ +5 cm
last hour

Updated 05:00 · 21 Aug

Current level progress indicator
Now 1.34 m
Critical 4.20 m

24 hour trend
+39 cm
[graph]

Upstream stations

P.67 · Mae Tae
0.81 m
↑ 2 cm/h

P.103 · Don Kaeo
1.12 m
↑ 3 cm/h

P.1 · Nawarat
1.34 m
↑ 5 cm/h

No flood warning
Levels are below alert thresholds.

Weather outlook
HIGH RAINFALL RISK
Next 24h upstream rainfall: 62–85 mm
Main forecast window: next 12–24h

Combined advisory
Conditions could deteriorate if upstream rainfall develops as forecast.

Bottom navigation:
Home | Stations | Alerts
```

Values shown above are **design examples**, not production defaults.

---

# 4. Main screens

## Home

Show:

- main station,
- live/stale state,
- current level,
- recent rate of change,
- current warning status,
- last updated time,
- threshold progress,
- 24h graph,
- upstream station cards,
- current warning summary.

## Stations

Show all enabled stations.

Each station card should include:

- display name,
- provider station code,
- river,
- latest normalized water level,
- latest timestamp,
- 1h change,
- 3h change,
- status,
- data freshness.

Allow opening a station detail screen.

## Station detail

Show:

- current level,
- source,
- last update,
- 1h / 3h / 6h / 12h / 24h changes,
- chart with selectable period,
- configured thresholds,
- raw/normalized source metadata for debugging if admin/debug mode is enabled.

## Alerts

Show:

- current active warning,
- recent alert history,
- station(s) responsible for the warning,
- reason the warning engine triggered,
- time triggered,
- time cleared.

---

# 5. Data collection

Create a CLI-safe collector:

```bash
php cron/collect_water_levels.php
```

It must:

1. load enabled providers,
2. request latest station measurements,
3. validate response,
4. normalize measurements,
5. store new samples,
6. avoid duplicate samples,
7. calculate/update current station state,
8. run the warning engine,
9. log success/failure.

Recommended cron frequency:

```cron
*/5 * * * * php /path/to/project/cron/collect_water_levels.php
```

Do not put absolute deployment paths into committed code.

---

# 6. Data freshness

Every station must have a freshness state.

Suggested logic, configurable:

```text
0–15 min    LIVE
15–30 min   DELAYED
>30 min     STALE
No data     OFFLINE
```

Do not display stale data as if it were current.

If data is stale:

```text
DATA DELAYED
Last measurement: 42 minutes ago
```

---

# 7. Normalization

Provider adapters must return a normalized object similar to:

```php
[
    'provider' => 'thaiwater',
    'station_code' => 'P.1',
    'measured_at' => '2026-08-21T05:00:00+07:00',
    'water_level_raw' => 301.84,
    'water_level_raw_unit' => 'm_msl',
    'water_level_gauge_m' => 1.34,
    'discharge_m3s' => 165.5,
    'rainfall_mm' => null,
    'raw_payload' => $rawPayload,
]
```

Not every source provides every field.

Never fabricate missing values.

---

# 8. Gauge vs MSL

This is critical.

The application must distinguish:

- absolute elevation relative to Mean Sea Level,
- local station gauge height.

Do not compare MSL values directly against a gauge threshold.

Store both separately if both exist.

If gauge conversion is configured, document:

```text
gauge_level = MSL_level - station_zero_reference
```

The reference value must be station-specific and configurable.

---

# 9. Warning engine

Use the requirements in `04_ALERT_ENGINE.md`.

The warning engine must be deterministic and testable.

It must return:

```php
[
    'severity' => 'normal|watch|warning|critical|unknown',
    'reason_codes' => [...],
    'message' => '...',
    'stations' => [...],
]
```

---

# 10. API endpoints

Internal endpoints should return JSON.

Examples:

```text
GET api/current.php
GET api/stations.php
GET api/history.php?station=P.1&period=24h
GET api/status.php
```

Response structure should be consistent:

```json
{
  "ok": true,
  "data": {},
  "meta": {
    "generated_at": "..."
  }
}
```

Errors:

```json
{
  "ok": false,
  "error": {
    "code": "PROVIDER_UNAVAILABLE",
    "message": "..."
  }
}
```

---

# 11. Weather forecast — mandatory V1

Implement the requirements in `08_WEATHER_FORECAST_AND_RISK.md`.

The app must collect forecast rainfall for one or more configured upstream/catchment forecast zones.

Minimum forecast windows:

```text
1h
3h
6h
12h
24h
48h
```

The app must clearly distinguish:

```text
Observed river state
Forecast weather risk
Combined advisory
```

Do not merge forecast rainfall into the measured river level.

The UI must never present a weather forecast as a guaranteed future river level.

Weather provider access must use `WeatherProviderInterface`.

If no verified weather provider can be completed during initial development, use `MockWeatherProvider` and clearly display `DEMO FORECAST DATA`.

---

# 12. Weather Risk and combined advisory

Create a separate:

```text
WeatherRiskEngine
```

with levels:

```text
unknown
low
moderate
high
very_high
```

Inputs may include:

- forecast rainfall accumulation,
- forecast intensity,
- duration,
- observed recent rainfall if available,
- configured upstream/catchment zones,
- official weather warnings if a verified provider exposes them.

Then create an:

```text
AdvisoryEngine
```

that combines:

```text
River Risk + Weather Risk
```

without replacing either individual status.

Example:

```text
River Risk: NORMAL
Weather Risk: HIGH

Combined advisory:
Conditions may deteriorate during the next 12–24 hours.
```

Another example:

```text
River Risk: WATCH
Weather Risk: VERY HIGH

Combined advisory:
Elevated flood potential. Upstream levels are rising and heavy rainfall is forecast.
```

Never output a deterministic future river height unless a separately verified hydrological prediction model is implemented.

---

# 13. Weather data collection

Create a CLI-safe collector:

```bash
php cron/collect_weather_forecast.php
```

It must:

1. load configured forecast zones,
2. request forecast data,
3. validate units and timestamps,
4. normalize forecast periods,
5. store forecast snapshots,
6. calculate Weather Risk,
7. preserve previous forecasts for comparison where practical,
8. update provider health,
9. log success/failure.

Recommended collection frequency:

```cron
*/15 * * * * php /path/to/project/cron/collect_weather_forecast.php
```

Actual frequency must respect provider terms/rate limits.

---

# 14. Internal weather API

Add endpoints such as:

```text
GET api/weather.php
GET api/forecast.php?zone=ping_upstream&period=24h
```

Return:

- forecast generation time,
- forecast provider,
- forecast zone,
- rainfall accumulation,
- max hourly rainfall if available,
- Weather Risk,
- data freshness,
- official warning metadata if available.

---

# 15. PWA

Implement:

- `manifest.webmanifest`
- icons/placeholders
- installable PWA
- service worker
- static asset caching
- offline shell

When offline, do **not** imply cached measurements are live.

Display:

```text
OFFLINE
Showing last stored measurement
```

---

# 16. Security

Minimum:

- PDO prepared statements,
- escape all HTML output,
- no credentials in repository,
- config secrets via local config/env,
- do not expose raw provider credentials,
- set sensible HTTP timeouts,
- limit external response size where practical,
- log provider errors without dumping secrets,
- CSRF if any future admin settings are added.

---

# 17. Documentation

Create:

## `docs/INSTALL.md`

Include:

- requirements,
- DB setup,
- configuration,
- cron setup,
- permissions,
- PWA notes.

## `docs/PROVIDERS.md`

Include:

- provider endpoints,
- authentication requirements,
- station mapping,
- units,
- update frequency,
- known quirks,
- verification date.

## `docs/ALERTS.md`

Explain every configured threshold and reason code.

## `docs/WEATHER.md`

Document:

- forecast provider,
- forecast zones,
- units,
- forecast windows,
- Weather Risk rules,
- limitations,
- distinction between forecast risk and measured river risk.

## `docs/API.md`

Document internal JSON endpoints.

---

# Definition of Done

This mission is complete only when all required acceptance tests in `06_TEST_ACCEPTANCE.md` pass or any blocked test is explicitly documented with the reason.

---

# FILE: 02_ARCHITECTURE_AND_DATA.md

# Architecture and Data Model

**Mission:** Ping Flood Watch  
**Version:** 1.01

---

# 1. Architecture

Use a provider-independent pipeline:

```text
Water provider ───────→ Water provider adapter ─→ Measurement repository ─→ River Risk
                                                                       │
Weather provider ──────→ Weather provider adapter ─→ Forecast repository ───→ Weather Risk
                                                                       │
                                                                       ↓
                                                              Combined advisory
                                                                       ↓
                                                                  Internal API
                                                                       ↓
                                                                  Mobile PWA
```

The UI must never know how ThaiWater, RID or another provider structures its payload.

---

# 2. Provider interface

Example:

```php
interface WaterProviderInterface
{
    public function getName(): string;

    /**
     * @return array<int, array<string,mixed>>
     */
    public function fetchLatestMeasurements(array $stationCodes): array;

    /**
     * Optional when provider supports historical data.
     */
    public function fetchHistory(
        string $stationCode,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array;
}
```

Provider failures should throw provider-specific exceptions which are caught by the collector.

---

# 3. Suggested database schema

## `stations`

```text
id
provider
provider_station_code
display_name
river_name
latitude
longitude
enabled
is_primary
sort_order

gauge_zero_msl
warning_level_m
critical_level_m
overflow_level_m

created_at
updated_at
```

Notes:

- thresholds are nullable,
- do not invent unknown coordinates/reference levels,
- `gauge_zero_msl` is nullable,
- threshold values must have documented source.

---

## `measurements`

```text
id
station_id
measured_at

water_level_gauge_m
water_level_msl_m
discharge_m3s
rainfall_mm

source_status
raw_payload_json
received_at
created_at
```

Unique key:

```text
(station_id, measured_at)
```

If the provider can revise historical values, decide and document whether to update existing rows.

---

## `station_state`

One current row per station:

```text
station_id
latest_measurement_id
freshness_status
change_1h_m
change_3h_m
change_6h_m
change_12h_m
change_24h_m
rate_1h_m_per_hour
updated_at
```

Values may be nullable if history is insufficient.

---

## `alerts`

```text
id
severity
status
title
message
reason_codes_json
triggered_at
cleared_at
created_at
updated_at
```

---

## `alert_stations`

```text
alert_id
station_id
measurement_id
```

---

## `provider_health`

```text
provider
last_success_at
last_failure_at
consecutive_failures
last_error_code
last_error_message
updated_at
```

---

# 4. Timezone

Use:

```text
Asia/Bangkok
```

Database timestamps may be stored in UTC if preferred, but all conversions must be explicit.

UI should show Chiang Mai local time.

Never assume server timezone equals local timezone.

---

# 5. Trend calculations

Do not require measurements at exactly 60 minutes before the latest sample.

For a target period:

```text
1h
3h
6h
12h
24h
```

Select the nearest valid measurement around the target time within a configurable tolerance.

Example:

```text
latest: 05:02
target 1h: 04:02
acceptable sample window: 03:57–04:07
```

If no valid comparison exists:

```text
change_1h = null
```

UI displays:

```text
—
```

not `0`.

---

# 6. Measurement validation

Reject or quarantine obviously invalid values.

Validation must be configurable and provider-aware.

Examples:

- missing station ID,
- invalid timestamp,
- non-numeric level,
- impossible future timestamp,
- response older than maximum provider age,
- duplicate timestamp.

Do not automatically reject a large level change merely because it is unusual. Flag it for anomaly handling instead.

---

# 7. Raw data

Keep provider raw payload for debugging where storage volume is reasonable.

Do not expose raw payload publicly by default.

Raw payload must never include provider secrets.

---

# 8. Caching

The public UI should read from the local database/internal API, not directly from external providers.

This means:

```text
External provider unavailable
        ↓
Collector fails
        ↓
Existing measurements remain available
        ↓
UI shows STALE / DELAYED
```

This is a major reliability requirement.

---

# 9. Configuration

Create a clear configuration layer for:

```text
timezone
BASE_URL
database
provider
station mapping
freshness thresholds
warning thresholds
rate-of-rise thresholds
notification settings
```

Production secrets must not be committed.

# 10. Weather forecast data model

Add a provider-independent forecast model.

## `forecast_zones`

```text
id
code
display_name
latitude
longitude
zone_type
enabled
sort_order
notes
created_at
updated_at
```

`zone_type` examples:

```text
upstream
catchment
city
reference
```

Coordinates must come from verified configuration or documented research.

---

## `weather_forecasts`

Store forecast snapshots rather than only overwriting the latest forecast.

```text
id
provider
forecast_zone_id
issued_at
valid_from
valid_to

rainfall_mm
rainfall_probability_pct
max_hourly_rainfall_mm
weather_code
source_status

raw_payload_json
received_at
created_at
```

Not all providers supply every field.

Unknown fields remain null.

---

## `weather_state`

```text
forecast_zone_id
latest_forecast_received_at

rain_1h_mm
rain_3h_mm
rain_6h_mm
rain_12h_mm
rain_24h_mm
rain_48h_mm

max_hourly_rain_mm
weather_risk
freshness_status
updated_at
```

---

# 11. Weather provider interface

Example:

```php
interface WeatherProviderInterface
{
    public function getName(): string;

    /**
     * @return array<int, array<string,mixed>>
     */
    public function fetchForecast(array $forecastZones): array;

    /**
     * Optional: current/recent observed rainfall.
     */
    public function fetchObservedRainfall(array $forecastZones): array;
}
```

Normalized forecast object example:

```php
[
    'provider' => 'verified-weather-provider',
    'zone_code' => 'ping_upstream_01',
    'issued_at' => '2026-08-21T06:00:00+07:00',
    'valid_from' => '2026-08-21T06:00:00+07:00',
    'valid_to' => '2026-08-22T06:00:00+07:00',
    'rainfall_mm' => 72.4,
    'rainfall_probability_pct' => 85,
    'max_hourly_rainfall_mm' => 18.2,
    'weather_code' => null,
    'raw_payload' => $rawPayload,
]
```

---

# 12. Weather Risk storage

Keep River Risk and Weather Risk separate.

Example current state:

```text
river_risk = normal
weather_risk = high
combined_advisory = monitor
```

Do not collapse all three into a single database field.

This distinction is important because a high rainfall forecast does not mean flooding is already occurring.

---

# 13. Forecast freshness

Forecast data needs freshness logic just like river measurements.

Example configurable logic:

```text
0–60 min      CURRENT
1–3 h         AGING
>3 h          STALE
No forecast   OFFLINE
```

Exact values depend on provider update cadence.

Stale weather data must not be presented as a current forecast.

---

# 14. Forecast comparison

Where practical, retain previous forecast snapshots to detect:

```text
forecast increasing
forecast decreasing
forecast shifted in time
```

This may later support messages such as:

```text
Forecast rainfall increased by 28 mm since the previous model run.
```

Do not make this mandatory for the first UI if it adds complexity, but keep the schema capable of it.

---

# FILE: 03_UI_UX_FIGMA.md

# UI / UX Mission — Figma Implementation

**Version:** 1.01  
**Figma:** https://www.figma.com/design/nVxpoCWiaJMFVEG0Z4x4Rh  
**Target:** Mobile only

---

# Design goal

The user must understand the flood situation within approximately 2–3 seconds.

The visual hierarchy is:

1. Is there danger?
2. What is the current P.1 level?
3. Is it rising?
4. What is happening upstream?
5. When was data last updated?
6. What has happened over the last 24 hours?
7. Is heavy rainfall forecast upstream during the next 1–48 hours?
8. Could conditions worsen even if the river is currently normal?

---

# Main viewport

Primary reference:

```text
390 × 844 px
```

The app may scale to common phone widths, but do not create a desktop dashboard.

On larger screens:

```css
.app-shell {
    max-width: 430px;
    margin: 0 auto;
}
```

---

# Visual character

Use the Figma design as the reference.

Desired feel:

- calm,
- trustworthy,
- modern,
- weather/monitoring app,
- clean white/light surfaces,
- subtle blue borders,
- blue river/data accents,
- green for safe/normal,
- amber/orange for elevated risk,
- red only when needed.

Avoid:

- gaming dashboard aesthetics,
- excessive gradients,
- neon,
- excessive animations,
- tiny technical text,
- information overload.

---

# Header

Title:

```text
Ping Flood Watch
```

Main location:

```text
Chiang Mai
P.1 · Nawarat Bridge
```

Status pill example:

```text
● LIVE · NORMAL
```

Possible status labels:

```text
LIVE · NORMAL
LIVE · WATCH
LIVE · WARNING
LIVE · CRITICAL
DELAYED
STALE
OFFLINE
```

Do not use green when the data is stale.

---

# Primary level card

Must visually dominate.

Example structure:

```text
1.34 m                 ↑ +5 cm
                       last hour

Updated 05:00 · 21 Aug

[ current level progress bar ]

Now 1.34 m              Critical 4.20 m
```

Requirements:

- current level uses large bold type,
- trend has direction arrow,
- timestamp is visible but secondary,
- progress graphic must not imply a verified threshold if threshold is unavailable.

If no verified threshold:

```text
Threshold unavailable
```

and hide the progress-to-critical visualization.

---

# Trend representation

Trend sign:

```text
↑ rising
↓ falling
→ stable
```

Use actual numerical change when available.

Examples:

```text
↑ +5 cm
↓ −3 cm
→ 0 cm
```

Do not call a value `0 cm` if comparison data is missing.

---

# 24-hour chart

Card title:

```text
24 hour trend
```

Show:

- line chart,
- 24h / 12h / 6h / Now axis cues,
- latest cumulative 24h change if available.

The chart must:

- fit comfortably on phone,
- have no unnecessary legend,
- make missing data visible,
- not interpolate massive gaps deceptively.

Chart.js is acceptable.

---

# Upstream stations

Section:

```text
Upstream stations          Early warning
```

Cards:

```text
P.67 · Mae Tae              0.81 m
Normal                      ↑ 2 cm/h
```

Status should be visually obvious.

Sort upstream to downstream.

The main station may also appear in this sequence for context.

---

# Warning card

Normal example:

```text
✓ No flood warning

Levels are below alert thresholds.
Upstream rise is moderate.
```

Watch example:

```text
! Upstream rise detected

P.67 has risen rapidly during the last hour.
Monitor P.1 closely.
```

Warning:

```text
! Flood warning

P.1 is approaching the configured warning level.
Water level is rising.
```

Critical:

```text
! Flood risk

P.1 has reached or exceeded the configured critical level.
Follow official local instructions.
```

Never claim an official evacuation order unless the app actually receives one from an official source.

---

# Weather outlook card — mandatory V1

Add a clear weather section to the Home screen.

Example:

```text
Weather outlook

HIGH RAINFALL RISK

Next 24h upstream rainfall
62–85 mm

Peak period
18:00–03:00

Forecast updated
05:45
```

If provider only returns a single deterministic value, display that value without inventing a range.

If forecast confidence/probability exists, it may be shown.

Do not fabricate uncertainty bounds.

---

# River Risk vs Weather Risk

Always display these as separate concepts.

Example:

```text
River
🟢 NORMAL

Weather
🟠 HIGH
```

Then show a short combined advisory:

```text
Conditions could deteriorate during the next 12–24 hours.
```

Another example:

```text
River
🟡 WATCH

Weather
🔴 VERY HIGH

Elevated flood potential.
Heavy upstream rainfall is forecast while river levels are already rising.
```

The combined advisory must not look like an official evacuation notice.

---

# Forecast timeline

Provide a compact forecast presentation.

Minimum useful periods:

```text
Next 6h
Next 12h
Next 24h
Next 48h
```

Possible compact UI:

```text
Forecast rain

6h       14 mm
12h      38 mm
24h      72 mm
48h      96 mm
```

If data is missing:

```text
—
```

not `0 mm`.

---

# Weather data freshness

Examples:

```text
Forecast updated 22 min ago
```

or:

```text
WEATHER DATA STALE
Last forecast received 4 hours ago.
```

Never show `HIGH RAINFALL RISK` based on stale forecast data without also showing the stale-data warning.

---

# Optional V1.1 radar

Rainfall radar is useful but is not mandatory in the first stable release.

Reserve future UI space/architecture for:

```text
Radar
```

but do not delay V1 for radar rendering.

---

# Bottom navigation

Fixed navigation:

```text
Home
Stations
Alerts
```

Use clear icons and labels.

The current tab must be obvious.

Respect mobile safe areas.

---

# Interaction

Keep interaction minimal.

Allowed:

- tap station card → station detail,
- tap chart period control,
- tap Alerts,
- pull/press refresh if implemented,
- enable notifications.

Avoid nested menus for core information.

---

# Accessibility

Minimum:

- text contrast should meet WCAG AA where practical,
- do not communicate severity by color alone,
- include icon + label,
- buttons minimum ~44 px touch target,
- support browser font scaling reasonably,
- chart summary must also be available as text.

---

# Loading states

Use skeletons or simple loaders.

Do not flash `0.00 m` before data arrives.

---

# Error states

Provider unavailable but cached data exists:

```text
DATA DELAYED

Last valid measurement:
05:00 · 21 Aug
```

No data:

```text
NO CURRENT DATA

Water-level data is temporarily unavailable.
```

---

# Language readiness

Structure strings so future localization is possible.

Possible future languages:

- English
- Thai
- Swedish

Do not scatter hardcoded UI strings unnecessarily through PHP templates and JS.

---

# FILE: 04_ALERT_ENGINE.md

# Warning & Alert Engine

**Version:** 1.01

---

# Goal

Provide useful flood-risk warnings without pretending to be an official emergency authority.

The river engine must combine:

- absolute station level,
- threshold margin,
- rate of rise,
- upstream behavior,
- water-data freshness,
- water-provider health.

A separate Weather Risk engine must combine:

- forecast rainfall accumulation,
- forecast intensity where available,
- forecast duration,
- upstream/catchment zone relevance,
- observed recent rainfall where available,
- weather-data freshness,
- weather-provider health,
- official weather warning metadata where available.

A third advisory layer may combine River Risk and Weather Risk for user-facing guidance.

All parameters must be configurable.

---

# 1. Severity levels

River Risk uses:

```text
unknown
normal
watch
warning
critical
```

Weather Risk uses:

```text
unknown
low
moderate
high
very_high
```

Meaning:

## `unknown`

Cannot confidently assess risk.

Examples:

- primary station has no current data,
- data is stale beyond configured maximum,
- thresholds needed for the calculation are missing.

## `normal`

No configured risk rule triggered.

## `watch`

Something deserves attention.

Examples:

- upstream station rising rapidly,
- primary station rising rapidly but still well below warning threshold.

## `warning`

Flood risk is becoming significant.

Examples:

- primary station enters warning margin,
- multiple upstream stations show sustained rapid rise.

## `critical`

Configured critical/overflow condition reached.

---

# 2. Reason codes

Use stable machine-readable codes.

Suggested:

```text
PRIMARY_LEVEL_WATCH
PRIMARY_LEVEL_WARNING
PRIMARY_LEVEL_CRITICAL
PRIMARY_RISE_FAST
PRIMARY_RISE_VERY_FAST
UPSTREAM_RISE_FAST
UPSTREAM_MULTI_STATION_RISE
THRESHOLD_MARGIN_LOW
DATA_DELAYED
DATA_STALE
PROVIDER_FAILURE
INSUFFICIENT_DATA
WEATHER_RAIN_MODERATE
WEATHER_RAIN_HIGH
WEATHER_RAIN_VERY_HIGH
WEATHER_INTENSITY_HIGH
WEATHER_DATA_STALE
WEATHER_PROVIDER_FAILURE
COMBINED_RIVER_WEATHER_RISK
```

A warning may contain multiple reason codes.

---

# 3. Threshold configuration

Do not hardcode production thresholds in source code.

Per-station DB/config fields:

```text
watch_level_m
warning_level_m
critical_level_m
overflow_level_m
```

If only some are known, only use known thresholds.

Each threshold should optionally store:

```text
source_name
source_url
verified_at
notes
```

---

# 4. Rate-of-rise rules

Suggested development configuration only:

```text
rise_watch_cm_per_hour
rise_warning_cm_per_hour
rise_critical_cm_per_hour
```

Codex must not assume example values are hydrologically authoritative.

Expose values in configuration.

Use sustained trend where possible rather than one noisy sample.

---

# 5. Upstream early warning

The system should support ordered river stations.

Example conceptual order:

```text
P.67
  ↓
P.103
  ↓
P.1
  ↓
P.104
```

Do not encode travel time as a fixed truth unless verified.

Instead support optional metadata:

```text
estimated_travel_minutes_to_primary
```

If unknown:

- still warn that upstream stations are rising,
- do not claim an exact arrival time.

---

# 6. Example rules

Pseudo logic:

```text
IF primary data stale:
    severity = unknown

ELSE IF primary >= critical threshold:
    severity = critical

ELSE IF primary >= warning threshold:
    severity = warning

ELSE IF primary rise >= configured very-fast threshold:
    severity = warning

ELSE IF upstream fast-rise count >= configured count:
    severity = watch

ELSE:
    severity = normal
```

Apply strongest triggered severity.

---

# 7. Alert lifecycle

An alert should not generate a new row every 5 minutes for the same continuing event.

Implement lifecycle:

```text
new
active
updated
cleared
```

Example:

```text
05:00 WATCH begins
05:05 still WATCH → update current alert
05:20 WARNING → escalate same event or create linked escalation
07:10 NORMAL sustained → clear alert
```

Use configurable hysteresis/clear delay.

Example concept:

```text
must remain below clear condition for 20 minutes
```

This avoids alert flapping.

---

# 8. Notification suppression

Do not spam.

Persist notification state.

Examples:

- notify when severity increases,
- optional reminder while critical after configurable interval,
- notify when cleared,
- do not send repeated identical watch notifications every collection cycle.

---

# 9. Browser push

Implement only after local warning logic is reliable.

Architecture:

```text
warning engine
    ↓
alert event
    ↓
notification queue
    ↓
Web Push sender
```

Do not send notifications directly inside provider parsing code.

---

# 10. Notification content

Good:

```text
Ping Flood Watch — WARNING

P.1 Nawarat Bridge is approaching the configured warning level.
Current level: 3.72 m
Rising: +11 cm in the last hour
Updated: 05:00
```

Bad:

```text
EVACUATE NOW!!!
```

unless an official emergency feed explicitly provides that instruction.

---

# 11. Data-quality warning

If the source is stale during high water:

```text
WARNING DATA DELAYED

Last measurement was 38 minutes ago.
Last recorded level: 3.81 m.
Current risk cannot be confirmed.
```

Do not downgrade risk to normal just because new data stopped arriving.

---

# 12. Unit tests

Warning engine must be unit-testable without calling the external API.

Include tests for:

- normal,
- watch from upstream,
- warning from primary level,
- warning from fast rise,
- critical threshold,
- missing threshold,
- missing history,
- stale data,
- alert escalation,
- clear hysteresis,
- no duplicate alert spam.

# 13. Weather Risk rules

Weather Risk thresholds must be configuration-driven.

Suggested configuration names:

```text
weather_6h_moderate_mm
weather_6h_high_mm
weather_6h_very_high_mm

weather_12h_moderate_mm
weather_12h_high_mm
weather_12h_very_high_mm

weather_24h_moderate_mm
weather_24h_high_mm
weather_24h_very_high_mm

weather_48h_moderate_mm
weather_48h_high_mm
weather_48h_very_high_mm

weather_hourly_intensity_high_mm
```

Do not assume example values are authoritative.

Thresholds should be tuned only after provider units and local hydrological context are verified.

---

# 14. Combined advisory logic

Do not replace River Risk with Weather Risk.

Example matrix:

```text
River NORMAL + Weather LOW/MODERATE
→ No additional advisory

River NORMAL + Weather HIGH
→ "Conditions may deteriorate if forecast rainfall develops."

River NORMAL + Weather VERY HIGH
→ "High upstream rainfall potential. Monitor river levels closely."

River WATCH + Weather HIGH
→ "Elevated flood potential. River levels are rising and heavy rainfall is forecast."

River WARNING + Weather HIGH/VERY HIGH
→ Stronger warning text, but River Risk remains the authoritative measured-river status.

River CRITICAL
→ Critical river warning regardless of Weather Risk.
```

`unknown` data should never automatically become `normal`.

---

# 15. Forecast horizon wording

The advisory may reference forecast horizons such as:

```text
next 6 hours
next 12 hours
next 24 hours
next 48 hours
```

Only use a horizon supported by the actual forecast data.

Do not claim:

```text
P.1 will reach X meters at Y time
```

unless a separate verified hydrological prediction model explicitly provides that result.

---

# 16. Weather notification examples

Good:

```text
Ping Flood Watch — WEATHER WATCH

Heavy rainfall is forecast in configured upstream areas during the next 12 hours.
River status is currently NORMAL.
Monitor conditions for changes.
```

Good combined alert:

```text
Ping Flood Watch — ELEVATED FLOOD POTENTIAL

River status: WATCH
Weather risk: HIGH

P.67 is rising and heavy upstream rainfall is forecast during the next 12–24 hours.
```

Avoid sensational wording.

---

# FILE: 05_API_PROVIDER_RESEARCH.md

# Mandatory Provider/API Validation Mission

**Version:** 1.01  
**Run before claiming live production data support.**

---

# Objective

Identify and verify the best machine-readable source(s) for Chiang Mai / Ping River water levels.

Potential water providers include:

- ThaiWater / Hydro Informatics Institute,
- Royal Irrigation Department / regional hydrology systems,
- other official Thai government telemetry feeds.

Potential weather providers must also be researched and verified.

Prefer:

- official Thai Meteorological Department data/API where technically usable,
- another reliable machine-readable forecast provider as fallback,
- a provider capable of hourly rainfall forecasts and geographic coordinates.

Do not hardcode a provider choice before testing actual access, units, update intervals and terms.

Third-party sites may be used for comparison, but should not become the only production dependency without an explicit decision.

---

# Important

Do not trust endpoint names copied from old examples without testing them.

For every water provider, verify:

1. endpoint currently responds,
2. response is machine-readable,
3. authentication requirements,
4. allowed request frequency,
5. station codes,
6. timestamp timezone,
7. water-level units,
8. whether value is gauge height or MSL elevation,
9. update frequency,
10. missing-value representation,
11. historical data availability,
12. rate limits / terms if documented.

For every weather provider, verify:

1. endpoint currently responds,
2. machine-readable forecast format,
3. authentication requirements,
4. allowed request frequency / rate limits,
5. hourly rainfall availability,
6. rainfall units,
7. probability-of-precipitation availability,
8. forecast issue time,
9. valid-from / valid-to timestamps,
10. timezone handling,
11. forecast horizon,
12. geographic input model (lat/lon, grid, district, station),
13. official weather-warning availability if any,
14. missing-value behavior,
15. usage/licensing/attribution requirements.

---

# Provider research output

Create:

```text
docs/PROVIDERS.md
```

For each provider include:

```text
Provider:
Base URL:
Endpoint:
Method:
Authentication:
Tested:
Test date:
Station P.1 present:
Station P.67 present:
Station P.103 present:
Units:
Reference system:
Timestamp:
Typical update interval:
Historical endpoint:
Notes:
Fallback suitability:
```

---

# Station validation

For each selected station, establish:

```text
provider station ID
public display code
name
river
latitude
longitude
level datum type
gauge zero if available
official warning threshold if available
official critical/overflow threshold if available
```

If a field is unknown, store `null`.

Do not infer coordinates or thresholds.

---

# Cross-checking

During development, compare P.1 values with at least one independent public display if possible.

Purpose:

- catch unit mistakes,
- catch station mapping mistakes,
- catch MSL vs gauge confusion,
- catch timezone mistakes.

Document discrepancies instead of hiding them.

---

# Provider abstraction requirement

Even if ThaiWater is chosen, the rest of the application should only call:

```php
WaterProviderInterface
```

not provider-specific code.

---

# Development fallback

If no reliable API can be immediately verified:

1. implement `MockWaterProvider`,
2. complete UI, DB, collector and warning engine against mock data,
3. clearly show `DEMO DATA`,
4. document the blocked live-provider task,
5. do not pretend mock data is live.

---

# API resilience

Use:

- connection timeout,
- request timeout,
- User-Agent,
- HTTP status validation,
- JSON validation,
- schema checks,
- graceful exception handling,
- provider health state.

Do not retry aggressively every request cycle.

---

# Raw response samples

Store sanitized samples under:

```text
docs/provider_samples/
```

Examples:

```text
thaiwater_latest_sample.json
thaiwater_history_sample.json
```

Remove any tokens or sensitive credentials before committing.

# Weather-provider research output

Extend:

```text
docs/PROVIDERS.md
```

and create:

```text
docs/WEATHER.md
```

For each tested weather provider document:

```text
Provider:
Base URL:
Endpoint:
Method:
Authentication:
Tested:
Test date:
Coordinates supported:
Hourly rainfall:
Rain probability:
Forecast horizon:
Units:
Timezone:
Issue timestamp:
Update interval:
Warnings available:
Rate limits:
Attribution requirements:
Fallback suitability:
Notes:
```

---

# Forecast zones

Do not use only "Chiang Mai city weather" as the weather-risk source.

Research and configure one or more forecast zones representing areas relevant to upstream Ping River inflow.

Possible conceptual zone types:

```text
upper_ping
ping_upstream_north
mae_taeng_reference
chiang_mai_city
```

These names are examples only.

Codex must document how each zone/coordinate was selected.

Do not claim a forecast point represents the entire watershed unless that is actually justified.

---

# Weather-provider abstraction requirement

All weather data must enter through:

```php
WeatherProviderInterface
```

The rest of the application must not depend on provider-specific JSON.

---

# Weather development fallback

If no production weather API can be verified immediately:

1. implement `MockWeatherProvider`,
2. complete forecast storage,
3. complete Weather Risk logic,
4. complete UI,
5. clearly label `DEMO FORECAST DATA`,
6. document the live-provider blocker,
7. never present mock forecast values as real.

---

# FILE: 06_TEST_ACCEPTANCE.md

# Test & Acceptance Criteria

**Version:** 1.01

Codex should record test results in:

```text
docs/TEST_REPORT.md
```

---

# A. Installation

- [ ] Fresh database schema imports successfully.
- [ ] Application loads with an empty database.
- [ ] Missing optional provider configuration produces a useful message.
- [ ] No PHP fatal errors.
- [ ] No secrets are committed.

---

# B. Portability

Test at least conceptually or locally for:

```text
/
```

and:

```text
/some-subdirectory/
```

- [ ] Internal links work.
- [ ] Assets load.
- [ ] API requests work.
- [ ] Redirects work.
- [ ] PWA paths work.

---

# C. Collector

- [ ] Collector can run from CLI.
- [ ] Successful measurements are inserted.
- [ ] Duplicate timestamps are not duplicated.
- [ ] Provider error does not delete old measurements.
- [ ] Provider error is logged.
- [ ] Provider health is updated.
- [ ] Invalid response is rejected safely.

---

# D. Units

- [ ] Gauge level and MSL level are separate fields.
- [ ] UI uses the intended value.
- [ ] Threshold comparisons use the same datum as the threshold.
- [ ] Missing conversion reference prevents unsafe conversion.

---

# E. Trend calculations

- [ ] 1h trend works.
- [ ] 3h trend works.
- [ ] 6h trend works.
- [ ] 12h trend works.
- [ ] 24h trend works.
- [ ] Missing comparison sample returns null, not zero.

---

# F. Warning engine

- [ ] Normal scenario.
- [ ] Upstream watch scenario.
- [ ] Fast rise scenario.
- [ ] Warning threshold scenario.
- [ ] Critical threshold scenario.
- [ ] Missing-threshold scenario.
- [ ] Stale-data scenario.
- [ ] Alert escalation.
- [ ] Alert clearing.
- [ ] No notification spam.

---

# G. Mobile UI

Test around:

```text
360 × 800
390 × 844
430 × 932
```

- [ ] No horizontal scrolling.
- [ ] Primary water level is immediately visible.
- [ ] Status is visible without scrolling.
- [ ] Trend is understandable.
- [ ] Upstream cards fit cleanly.
- [ ] Bottom navigation is usable.
- [ ] Safe-area padding works.
- [ ] Text is readable.

---

# H. Figma fidelity

Compare the implementation with:

https://www.figma.com/design/nVxpoCWiaJMFVEG0Z4x4Rh

Check:

- [ ] hierarchy,
- [ ] spacing,
- [ ] rounded cards,
- [ ] blue data accents,
- [ ] green normal state,
- [ ] primary level typography,
- [ ] upstream list,
- [ ] warning card,
- [ ] bottom navigation.

Exact pixel parity is not required if usability improves, but deviations should be intentional.

---

# I. Stale/offline state

- [ ] Stale measurement never displays as LIVE.
- [ ] Offline PWA shows cached shell.
- [ ] Cached measurements say they are cached/old.
- [ ] No connection does not produce a false NORMAL state.

---

# J. API

- [ ] JSON endpoints return valid JSON.
- [ ] Correct content type.
- [ ] Errors have stable structure.
- [ ] Invalid station code handled safely.
- [ ] Invalid period handled safely.

---

# K. PWA

- [ ] Valid manifest.
- [ ] Service worker registers.
- [ ] App shell works offline.
- [ ] Installability checked where possible.
- [ ] Service worker does not cache API data indefinitely as if live.

---

# L. Security

- [ ] SQL uses prepared statements.
- [ ] HTML output escaped.
- [ ] Provider secrets are server-side only.
- [ ] Logs do not leak credentials.
- [ ] Raw provider payload not publicly exposed by default.

---

# M. Weather forecast

- [ ] Weather provider is accessed through `WeatherProviderInterface`.
- [ ] Forecast collector runs from CLI.
- [ ] Forecast snapshots are stored.
- [ ] 1h forecast rain is handled.
- [ ] 3h forecast rain is handled.
- [ ] 6h forecast rain is handled.
- [ ] 12h forecast rain is handled.
- [ ] 24h forecast rain is handled.
- [ ] 48h forecast rain is handled.
- [ ] Missing forecast values return null, not zero.
- [ ] Rainfall units are validated.
- [ ] Forecast timezone is validated.
- [ ] Forecast issue time is stored.
- [ ] Forecast validity window is stored.
- [ ] Weather provider failure does not crash river monitoring.
- [ ] Stale forecast is visibly marked stale.
- [ ] Mock forecast data is visibly labelled DEMO.

---

# N. Weather Risk

- [ ] LOW scenario.
- [ ] MODERATE scenario.
- [ ] HIGH scenario.
- [ ] VERY HIGH scenario.
- [ ] UNKNOWN scenario for missing/stale forecast.
- [ ] Thresholds are configuration-driven.
- [ ] River Risk remains separate from Weather Risk.
- [ ] Weather Risk does not directly invent a future river level.

---

# O. Combined advisory

- [ ] River NORMAL + Weather LOW.
- [ ] River NORMAL + Weather HIGH.
- [ ] River WATCH + Weather HIGH.
- [ ] River WARNING + Weather VERY HIGH.
- [ ] River CRITICAL remains critical regardless of forecast.
- [ ] Weather UNKNOWN does not become LOW automatically.
- [ ] Advisory wording uses uncertainty appropriately.
- [ ] No false evacuation or deterministic flood claim.

---

# P. Mobile weather UI

- [ ] Weather outlook is easy to find.
- [ ] River Risk and Weather Risk are visually distinct.
- [ ] 6h / 12h / 24h / 48h rainfall values fit on mobile.
- [ ] Forecast update time is visible.
- [ ] Stale forecast state is visible.
- [ ] Missing values show `—`, not `0 mm`.

---

# Completion report

`docs/TEST_REPORT.md` must contain:

```text
Date
Environment
PHP version
DB version
Provider used
Tests passed
Tests failed
Blocked tests
Known limitations
Recommended next actions
```

---

# FILE: 07_DEPLOYMENT_AND_OPERATIONS.md

# Deployment & Operations

**Version:** 1.01

---

# 1. Requirements

Recommended:

```text
Apache 2.4+
PHP 8.2+
MariaDB / MySQL
PHP PDO MySQL
PHP cURL
HTTPS
cron
```

Optional:

```text
Web Push library
```

---

# 2. Apache

The project directory is the web DocumentRoot unless deployment says otherwise.

Allow the app to work in root or subdirectory.

If `.htaccess` is used, keep it minimal and document required Apache modules.

---

# 3. HTTPS

PWA installation and Web Push require secure contexts in production.

Production must use HTTPS.

---

# 4. Cron

Recommended river collection interval:

```cron
*/5 * * * *
```

Recommended weather-forecast collection interval:

```cron
*/15 * * * *
```

Adjust the weather interval to the verified provider update cadence and rate limits.

Document exact server path during deployment in local operations notes, not portable source code.

Prevent overlapping collectors.

Possible approaches:

```text
flock
DB advisory lock
application lock file
```

The collector must exit cleanly if another collector is already running.

---

# 5. Logging

Create dedicated logs:

```text
storage/logs/collector.log
storage/logs/provider.log
storage/logs/alerts.log
```

Include:

```text
timestamp
severity
provider
station
event code
message
```

Do not log secrets.

Add log rotation guidance.

---

# 6. Database retention

Measurements every 5 minutes:

```text
288 samples/day/station
```

Even multiple stations remain modest, but establish retention policy.

Suggested:

- raw 5-minute measurements: retain at least 12 months initially,
- aggregate later if needed,
- do not delete alert history casually.

---

# 7. Backup

Back up:

- database,
- app configuration excluding secrets where appropriate,
- documented threshold sources,
- alert configuration.

Raw cache does not necessarily need backup.

---

# 8. Monitoring

App should make provider health visible internally.

Useful operational fields:

```text
last river collector run
last weather collector run
last water-provider success
last water-provider failure
last weather-provider success
last weather-provider failure
latest measurement age
latest forecast age
consecutive water-provider failures
consecutive weather-provider failures
```

Optional health endpoint:

```text
api/health.php
```

Do not expose sensitive internals.

---

# 9. Future notification channels

Design the architecture so notification senders can be added later:

```text
WebPushNotifier
EmailNotifier
TelegramNotifier
HomeAssistantWebhookNotifier
```

They should consume alert events, not provider data directly.

---

# 10. Future features

Do not build these unless time remains after V1 is stable:

- rainfall radar visualization,
- flood-map overlay,
- geolocation,
- user-selected favorite station,
- multilingual Thai/English/Swedish,
- Home Assistant integration,
- Telegram bot,
- SMS,
- multiple cities/rivers,
- official announcement ingestion,
- predictive river-arrival model.

---

# 11. Production readiness checklist

Before public production:

- verify primary water provider,
- verify weather forecast provider,
- verify forecast-zone coordinates,
- verify station mapping,
- verify water units,
- verify rainfall units,
- verify station datum,
- verify river thresholds,
- verify Weather Risk thresholds,
- verify timestamps/timezones,
- verify both cron collectors,
- verify stale-data behavior,
- verify HTTPS,
- verify backups,
- verify warning wording,
- test on actual Android/iPhone browser.

---

# FILE: 08_WEATHER_FORECAST_AND_RISK.md

# Weather Forecast & Forecast Risk — Mandatory V1 Mission

**Mission ID:** PFW-WEATHER-001  
**Version:** 1.00  
**Status:** Mandatory for V1

---

# Objective

Extend Ping Flood Watch from a purely reactive river monitor into an early-awareness app.

The app must answer two different questions:

```text
1. What is the river doing now?
2. Is forecast rainfall likely to increase flood potential later?
```

These must remain separate in both code and UI.

---

# 1. Core concepts

Implement three separate outputs:

## River Risk

Based on measured river data:

```text
UNKNOWN
NORMAL
WATCH
WARNING
CRITICAL
```

## Weather Risk

Based on forecast rainfall:

```text
UNKNOWN
LOW
MODERATE
HIGH
VERY HIGH
```

## Combined Advisory

Short human-readable guidance derived from both.

Example:

```text
River Risk: NORMAL
Weather Risk: HIGH

Conditions may deteriorate during the next 12–24 hours if forecast rainfall develops.
```

---

# 2. Forecast geography

Flood impact in Chiang Mai depends heavily on rain upstream.

Do not monitor only a single weather point in central Chiang Mai.

Support multiple configurable forecast zones.

Each zone should contain:

```text
code
display name
latitude
longitude
zone type
notes
```

Possible zone types:

```text
upstream
catchment
city
reference
```

The initial configuration must be based on documented research.

Do not pretend one coordinate represents the full Ping watershed.

---

# 3. Required forecast horizons

Normalize precipitation forecasts into:

```text
1h
3h
6h
12h
24h
48h
```

Store:

```text
rainfall accumulation
maximum hourly intensity if available
precipitation probability if available
forecast issue time
forecast validity window
provider
zone
freshness
```

Unknown values remain null.

---

# 4. Weather provider adapter

Implement:

```php
WeatherProviderInterface
```

Provider-specific API format must not leak into UI or warning logic.

At minimum provide:

```text
VerifiedWeatherProvider
MockWeatherProvider
```

Use a real provider class name after provider research determines the production provider.

---

# 5. Forecast collector

Create:

```bash
php cron/collect_weather_forecast.php
```

Responsibilities:

1. acquire execution lock,
2. load enabled forecast zones,
3. request forecast,
4. validate response,
5. normalize data,
6. store snapshot,
7. calculate weather state,
8. run WeatherRiskEngine,
9. update provider health,
10. log result,
11. release lock.

A weather API failure must not interrupt water-level monitoring.

---

# 6. Weather Risk engine

Create:

```php
WeatherRiskEngine
```

Input:

```text
forecast accumulations
hourly intensity
forecast zone relevance
recent observed rain if available
forecast freshness
provider health
official weather-warning metadata if available
```

Output:

```php
[
    'severity' => 'unknown|low|moderate|high|very_high',
    'reason_codes' => [...],
    'message' => '...',
    'zones' => [...],
    'forecast_window' => '...',
]
```

---

# 7. Threshold configuration

No authoritative rainfall thresholds are assumed in this mission.

Create configurable values.

Example config keys:

```text
weather.6h.moderate_mm
weather.6h.high_mm
weather.6h.very_high_mm

weather.12h.moderate_mm
weather.12h.high_mm
weather.12h.very_high_mm

weather.24h.moderate_mm
weather.24h.high_mm
weather.24h.very_high_mm

weather.48h.moderate_mm
weather.48h.high_mm
weather.48h.very_high_mm

weather.hourly.high_mm
```

Seed values, if used, must be labelled:

```text
DEVELOPMENT ONLY — UNVERIFIED
```

---

# 8. Advisory engine

Create:

```php
AdvisoryEngine
```

Inputs:

```text
River Risk
Weather Risk
river trend
upstream trend
forecast horizon
data freshness
```

Output should provide concise guidance.

Examples:

### Normal river + high rainfall forecast

```text
Conditions may deteriorate during the next 12–24 hours.
Heavy rainfall is forecast in configured upstream areas.
```

### River watch + high rainfall forecast

```text
Elevated flood potential.
Upstream levels are rising and heavy rainfall is forecast.
```

### Critical river state

```text
Flood risk is critical based on measured river level.
```

Do not weaken a measured critical river warning because forecast rain is low.

---

# 9. UI

Add to Home:

```text
Weather outlook

HIGH RAINFALL RISK

Next 6h        14 mm
Next 12h       38 mm
Next 24h       72 mm
Next 48h       96 mm

Forecast updated 05:45
```

Then show:

```text
River
NORMAL

Weather
HIGH

Combined advisory
Conditions may deteriorate during the next 12–24 hours.
```

The main water-level card remains the dominant component.

---

# 10. Notifications

Weather-only notification example:

```text
WEATHER WATCH

Heavy rainfall is forecast in upstream areas during the next 12 hours.
River status is currently NORMAL.
```

Combined example:

```text
ELEVATED FLOOD POTENTIAL

River: WATCH
Weather: HIGH

P.67 is rising and heavy upstream rainfall is forecast.
```

Use notification suppression rules from the main alert engine.

---

# 11. Stale forecast handling

If forecast is stale:

```text
WEATHER DATA STALE
```

Weather Risk should become `UNKNOWN` unless a deliberately documented policy says otherwise.

Do not silently retain a stale `LOW` state.

---

# 12. Radar

Radar is **not required for stable V1**.

Architecture may prepare for it, but radar UI/data ingestion belongs to V1.1 unless implementation is trivial after V1 is complete.

---

# 13. Production safety wording

This application is an information and early-awareness tool.

Never generate:

```text
Evacuate now
Safe to remain
Flood guaranteed
P.1 will definitely reach X m
```

unless that exact meaning is supplied by a verified official source or a separately verified hydrological prediction product.

Forecast wording should use:

```text
forecast
potential
may
could
increased risk
monitor
```

when uncertainty exists.

---

# Definition of Done

- [ ] Real or explicitly mocked weather provider implemented.
- [ ] Forecast zones configurable.
- [ ] 1h/3h/6h/12h/24h/48h forecast storage implemented.
- [ ] Weather Risk implemented.
- [ ] River Risk and Weather Risk remain separate.
- [ ] Combined advisory implemented.
- [ ] Weather data freshness implemented.
- [ ] Home UI shows weather outlook.
- [ ] Weather provider failure is handled.
- [ ] Forecast cannot create fake future river levels.
- [ ] Documentation and tests completed.
