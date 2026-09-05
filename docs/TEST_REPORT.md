# Test report

Date: 2026-08-21 UTC  
Environment: Ubuntu server, Apache 2.4.58, `ping.aberg.online` / `85.190.102.171`  
PHP: 8.3.6  
Database: MariaDB 10.11.14  
Browser tooling: Node 22.23.2, Playwright 1.62.1, Chromium 1234, axe-core 4.13.0  
Providers: ThaiWater, CMFlood / Chiang Mai University and Open-Meteo production APIs  

## V1.2 station and theme extension

- Exact north-to-south order deployed: **P.67 → P.103 → CMI01 → CMI02 → P.1 → FBP.2 → CMI03 → FBP.3 → P.104**.
- P.1 remains the single primary flood-risk reference and the default on a device without a stored preference. A visitor can select another home station; the choice is local-only and changes the home reading/chart, never the alert or risk authority.
- CMFlood adapter contract covers three public source shapes, original/local/UTC timestamps, gauge-to-MSL metadata, response validation and station-specific plausibility gates. Downstream high/overflow signals are explicitly excluded from upstream River Risk.
- Production 72-hour collection processed 2,540 changed records (2,506 inserted, 34 source revisions). All nine stations then reported `live`; ThaiWater, CMFlood and Open-Meteo provider health had zero consecutive failures.
- PHPUnit unit/integration: **84 passed**, 252 assertions. Live-provider smoke: **3 passed**, 215 assertions. Playwright/axe: **57 passed** across 360×800, 390×844 and 430×932, including exact order, P.1 fallback, preference persistence, selected-station chart, dark-theme persistence, 44 px controls and overflow/accessibility checks.
- Composer audit: 0 known advisories. npm audit: 0 vulnerabilities. Production `/api/health.php` returned HTTP 200 `ok` after migration and collection.
- App assets and service-worker shell use cache version `1.2.0`; theme selection is applied before the stylesheet to avoid a light-mode flash.
- Pre-migration code and SQL backups, with SHA-256 hashes, are stored under `/var/backups/ping-flood-watch/pre-station-release/`.

## V1.2.3 street map and dark-mode polish

- The non-functional key-dependent map was replaced with self-hosted **Leaflet 1.9.4** and **Leaflet.markercluster 1.5.3**, using the standard OpenStreetMap raster tile service without an API key.
- The map initializes only after a visitor selects Map. Tiles are neither prefetched nor stored by the service worker, browser caching is left at its normal settings, attribution is permanently visible, and Apache CSP permits only `tile.openstreetmap.org` as the remote image source.
- All nine ordered stations are plotted on real streets. Nearby markers cluster at low zoom, expand at street-level zoom, expose accessible 44 px controls and retain the selected home station locally.
- Normal, watch, warning, critical and unknown Combined Advisory cards now retain readable foreground, icon and background contrast in dark mode.
- The shared footer places `Developed by Hans Åberg` below the decision-support notice and provider attributions on every public page.
- PHPUnit unit/integration: **84 passed**, 252 assertions. Playwright/axe: **60 passed** across 360×800, 390×844 and 430×932. Composer and npm audits report no known advisories or vulnerabilities.
- App assets and service-worker shell use cache version `1.2.3`; production health returned HTTP 200 `ok` after deployment.

## Automated V1.1 results

- PHPUnit unit/integration: **79 passed**, 234 assertions, 0 failed.
- Opt-in live-provider smoke: **2 passed**, 201 assertions, 0 failed.
- Playwright: **48 passed** across 360×800, 390×844 and 430×932, 0 failed.
- axe: no critical or serious violations on the home view at all three target viewports.
- Composer audit: 0 known security advisories. npm audit: 0 vulnerabilities.
- PHP syntax, JavaScript syntax, manifest JSON and Composer metadata validation: passed.
- Isolated staging live collectors: 72-hour water backfill processed 210 records (207 inserted, 3 revisions); weather stored 144 hourly points; health returned HTTP 200 `ok`.
- Hung health integration: `/api/health.php` returned 503 while `/api/status.php` remained 200, both reporting water `hung` with a ten-minute runtime.
- Staging isolation: `push.enabled=false`, no VAPID keys and zero subscription/outbox/delivery rows after collection.

During V1.1 integration, native PDO exposed reused named parameters in claim and subscription updates (`HY093`). Parameters were made unique and the new concurrency/recovery tests then passed. Nullable browser formatting also incorrectly converted `null` to zero; level/rain/trend/capacity formatting now preserves em dash.

The browser suite now covers overlapping refresh prevention, 60-second resume, independent current/history failures, stale/offline/cache distinctions, Alerts patching, EN/TH, permission gestures, iOS install guidance, service-worker push/click scope validation, forced colors, 44 px targets and 11 px minimum secondary typography.

## Acceptance A–P

| Area | Result | Evidence |
|---|---|---|
| A. Installation | Pass | Fresh production/test schemas imported; seed is idempotent; empty-database dashboard returns Unknown without fatal error; runtime secret exists only in `/etc/ping-flood-watch/config.php`. |
| B. Portability | Pass | `app.base_url` drives links/assets/API calls; manifest and service-worker paths are scope-relative. Production is verified at `/`; subdirectory behavior was reviewed through the configured base-path implementation. |
| C. Collector | Pass | CLI backfill inserted 204 rows and preserved 3 source revisions; subsequent cron runs deduplicated. Invalid provider schemas are rejected; health/failure paths preserve stored history. |
| D. Units | Pass | MSL and gauge fields are distinct; `GaugeConverter` refuses missing offsets; P.1 rules compare gauge against gauge thresholds. |
| E. Trends | Pass | 1/3/6/12/24-hour calculations and ±10-minute nearest-sample tolerance tested; missing comparisons return `null`. |
| F. Warning engine | Pass | Normal, upstream watch, configured fast-rise, warning, critical, missing-threshold, stale/failure, escalation, 20-minute clear and spam suppression tested. Production rise thresholds remain `null`. |
| G. Mobile UI | Pass | No horizontal overflow, 44 px navigation targets, primary reading/status, charts, station list, safe-area bottom navigation and readable EN/TH checked at all target viewports. |
| H. Figma fidelity | Pass | Compared to verified node `1:2`: 390 px composition, 430 px max shell, spacing, rounded cards, palette, level hierarchy and bottom navigation retained. Intentional additions are language switching, dynamic Chart.js and mandatory weather/advisory sections. |
| I. Stale/offline | Pass | LIVE/DELAYED/STALE boundaries enforced; failed providers cannot create false Normal; service worker caches no API response; last local home snapshot is explicitly marked `OFFLINE / showing last stored data`. |
| J. API | Pass | All ten read/write/status endpoints return JSON envelopes/no-store; valid responses and stable validation/method/origin errors checked, including Thai localization. |
| K. PWA | Pass | Valid scoped manifest, 192/512 icons, secure service-worker registration, shell/offline fallback and local snapshot verified. API requests are network-only. |
| L. Security | Pass | Native PDO prepared statements, output escaping, raw payload isolation, redacted logs, external secret, least-privilege runtime DB grants, CSP/HSTS/nosniff/frame/referrer/permissions headers and blocked private paths verified. |
| M. Weather forecast | Pass | Interface/collector/run/point storage, SHA-256 dedup, validity windows, 1/3/6/12/24/48 accumulations, null handling, timezone, freshness and independent provider-failure path verified. Provider issue time stays null because the response does not supply one. |
| N. Weather Risk | Pass | LOW/MODERATE/HIGH/VERY HIGH and UNKNOWN boundaries tested; configuration-driven TMD thresholds; no future river level is invented. |
| O. Combined advisory | Pass | Matrix tests cover normal+low, normal+high, watch+high, warning+very-high, critical+unknown and unknown inputs; language remains conditional and never asserts evacuation or deterministic flooding. |
| P. Mobile weather UI | Pass | Separate Weather Risk plus 6/12/24/48 point ranges, maximum hourly intensity/probability, forecast time, stale state and em-dash nulls fit all target screens. |

## V1.1 Hardening acceptance A–O

| Area | Result | Evidence |
|---|---|---|
| A. Outbox transaction | Pass | Alert event and one neutral outbox row commit atomically; injected outbox failure rolls both back; external sender is used only by dispatcher after commit. |
| B. Dispatcher concurrency | Pass | Two native-PDO claimers cannot claim one row; five-minute abandoned claims recover; delivery uniqueness survives repeated fan-out. |
| C. Superseded notifications | Pass | Persisted later escalation/downgrade/clear supersedes old active notices; a new incident supersedes old clear; checks run before each delivery attempt. |
| D. Expiry / TTL | Pass | Active 30-minute and clear 2-hour expiry tested; remaining TTL, normal/high urgency, private base36 topic/tag verified. |
| E. Composer/runtime | Pass | PHP 8.3.6 with mbstring/OpenSSL; Web Push 11 plus Guzzle PSR-18; no async adapter; Composer/npm audits clean. |
| F. iPhone/iPad | **Manual pending** | Automated capability, install-required, user-gesture permission, localized push and safe click pass. Real Home Screen PWA receipt/click/unsubscribe awaits the checklist below. |
| G. Android | **Manual pending** | Automated subscribe/background worker/click/unsubscribe logic passes. Real Android Chrome background/closed receipt awaits the checklist below. |
| H. Upstream semantics | Pass | Positive numeric trend remains informational with null thresholds; configured Watch/Warning reason codes tested; upstream never creates Critical/arrival prediction. |
| I. Hung collectors | Pass | Running+recent prior success, water/weather runtime limits, hung, missing, failed and stale covered; HTTP 503/200 endpoint split verified. |
| J. Push endpoint security | Pass | Method/content type/origin, strict structure, HTTPS/length/key/language, HMAC rate limit and retry-after covered; redaction excludes endpoint/key/UA secrets. |
| K. CLI test push | Automated pass / device pending | CLI-only guard, explicit `--confirm-test`, TEST payload, no incident creation path and optional disable implemented; real send follows device checklist. |
| L. Migrations | Pass | `002_v1_1_push_and_health.sql` applied and rerun safely; fresh schema matches; rollback retains tables. |
| M. Staging isolation | Pass | Dedicated test DB, push false, empty keys and cleared push tables; dispatcher exits without delivery. |
| N. PWA rollback | Pass | Rollback package uses cache `v1.1.0-rollback.1`; activation removes V1.1 cache; rollback smoke/API/collector procedure documented and tested locally. |
| O. Release artifact | Pass | Versioned ZIP plus SHA-256 outside web tree; vendor/docs/tests/migration/locks included; secrets, node_modules, logs and runtime/test caches excluded and audited. |

## Required real-device completion checklist

V1.1 is deployed but is **not Definition-of-Done complete** until both device rows have actual OS/browser versions and every item is reported as passed.

### Android Chrome

- [ ] Record phone model, Android version and Chrome version.
- [ ] Open/install `https://ping.aberg.online`, Alerts → Enable, and confirm subscribed state.
- [ ] Use `sudo -u www-data php cron/test_push.php --subscription-id=ID --confirm-test` and confirm the visible title starts with TEST.
- [ ] Repeat with the PWA/browser backgrounded, then fully closed; confirm receipt in both required scenarios supported by the OS.
- [ ] Tap notification; confirm the existing app focuses or Alerts opens on the same origin.
- [ ] Disable in Alerts; confirm subscribed state is removed and no further test notification is delivered.

### iPhone Safari Home Screen PWA

- [ ] Record iPhone model, iOS/iPadOS version and Safari version.
- [ ] In Safari (not installed), confirm Alerts shows Add to Home Screen guidance and no Enable button.
- [ ] Add to Home Screen, launch from its icon, Alerts → Enable, and grant permission only after tapping.
- [ ] Run the CLI test and confirm TEST notification while app is backgrounded/closed.
- [ ] Tap notification; confirm the Home Screen PWA focuses/opens Alerts, with no silent background-only behavior.
- [ ] Disable in Alerts; confirm unsubscribe and no further test delivery.

Record results and timestamps here before changing F/G/K to Pass and declaring Definition of Done.

## Production verification

- DNS A record: `ping.aberg.online → 85.190.102.171`.
- HTTP returns `301 Location: https://ping.aberg.online/...`.
- TLS subject/SAN: `DNS:ping.aberg.online`; certificate valid 2026-08-20 through 2026-11-18.
- `certbot renew --dry-run --cert-name ping.aberg.online`: successful.
- Migration `002_v1_1_push_and_health` applied at 02:43 UTC; all four new tables and migration record verified.
- External config remains `0640 root:www-data`; push is enabled with one non-empty VAPID pair, correct subject and a non-empty HMAC key. Secret values were never printed or copied to staging/release.
- Provider health after V1.1 deployment: ThaiWater `ok`, Open-Meteo `ok`; collectors reported `ok` with observed runtimes about 0.1 and 0 minutes.
- Push cron ran successfully each minute at 02:45 and 02:46 UTC with zero subscribers/jobs; water and weather cron both completed successfully after deployment.
- Production browser gate exercised all 48 cases. The first run passed 45/48; the only failures were a test expecting `INVALID_SUBSCRIPTION` for an empty Playwright body that correctly returned `INVALID_JSON`. The environment-aware assertion was corrected and all three viewport reruns passed.
- Push API production checks: 405 method, 415 content type, 403 origin, 413 body size and 400 invalid subscription, with stable codes.
- V1 rollback passed 45 PHPUnit/97 assertions, live water/weather collection, API smoke and 15 Playwright cases against the migrated schema using cache `v1.1.0-rollback.1`.
- Release `/var/backups/ping-flood-watch/releases/ping-flood-watch-v1.1.0.zip` (3.7 MB) and rollback ZIP (2.6 MB) both pass their adjacent SHA-256 files. Archive inspection found required vendor/docs/tests/migration/lockfiles and no node_modules, env/config secrets, logs, locks or runtime/test caches.
- Compressed backup created and passed `gzip -t`; logrotate configuration passed dry-run inspection.
- Apache no longer falls back to `www.aberg.online` for this hostname.

## Known limitations

- Open-Meteo use assumes the selected non-commercial deployment and is subject to its attribution and free-tier terms.
- Forecasts are point values at P.67/P.103, not a catchment model or uncertainty band.
- ThaiWater timing/availability is upstream-controlled; displayed freshness may be DELAYED between source publications.
- No official production rise-rate threshold was verified, so rate rules are disabled rather than guessed.
- Open-Meteo does not supply a distinct issue time in the selected response; receipt time and hourly validity are stored, `issued_at` is null.
- Web Push delivery is at-least-once around a crash immediately after provider acceptance; topic/tag makes any visible duplicate replaceable.
- Real iPhone and Android results remain pending, so V1.1 must not yet be marked Definition-of-Done complete.
- Radar, hydrological level prediction and claims about official evacuation decisions are outside V1.1.

Recommended next actions: complete both device checklists; obtain TMD NWP OAuth access for an official alternative adapter; verify official P.1/P.67/P.103 rate-of-rise thresholds; perform periodic source/threshold revalidation.
