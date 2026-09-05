# Ping Flood Watch V1.1 — Reliability Hardening Codex Master Addendum

**Use together with the existing V1.1 Codex mission/implementation plan.**


---

# FILE: 00_README.md

# Ping Flood Watch V1.1 — Reliability Hardening Addendum

**Mission type:** Addendum / patch mission  
**Target:** Codex  
**Applies to:** Existing V1.1 implementation plan for `ping.aberg.online`  
**Do not restart or rewrite the V1.1 work.**

## Purpose

This package contains additional requirements that strengthen the already-approved V1.1 proposal.

The addendum focuses on operational reliability, especially around Web Push, collector health, upstream early-warning semantics and rollback safety.

## Important

This mission is **not** a replacement for the existing V1.1 mission.

Codex should:

1. keep the existing V1.1 plan,
2. merge these requirements into that plan,
3. avoid undoing already-correct design decisions,
4. implement these items before V1.1 is considered production complete.

## Main additions

- explicit notification outbox,
- superseded/expired push handling,
- Web Push TTL, urgency and stable topics/tags,
- iPhone/iPad Home Screen PWA handling,
- explicit Android + iPhone manual push QA,
- upstream `rising` information separate from configured warning thresholds,
- hung collector detection,
- stronger push endpoint hardening,
- migration files instead of schema-only changes,
- staging push isolation,
- rollback-safe service-worker/cache strategy,
- release ZIP outside the web tree.

## Files

- `01_MASTER_ADDENDUM.md`
- `02_PUSH_OUTBOX_AND_DELIVERY.md`
- `03_IOS_ANDROID_PUSH_QA.md`
- `04_UPSTREAM_SIGNAL_SEMANTICS.md`
- `05_HEALTH_AND_HUNG_COLLECTORS.md`
- `06_SECURITY_MIGRATION_ROLLBACK.md`
- `07_ACCEPTANCE_TESTS.md`

---

# FILE: 01_MASTER_ADDENDUM.md

# Mission Addendum: Ping Flood Watch V1.1 — Reliability Hardening

**Mission ID:** PFW-V1.1-HARDEN-001  
**Priority:** High  
**Scope:** Amend the existing V1.1 plan. Do not rebuild the app.

---

# 1. Objective

Strengthen the V1.1 implementation proposal in the areas where operational edge cases could otherwise produce:

- stale or out-of-order push notifications,
- misleading upstream status,
- collectors stuck forever in `running`,
- staging accidentally notifying production users,
- incomplete rollback after service-worker changes,
- weak push endpoint abuse resistance.

---

# 2. Web Push outbox must be explicit

The existing proposal says that `AlertManager` creates alert events and outbox entries atomically, but the actual persistence model must be defined explicitly.

Add a dedicated table:

```text
notification_outbox
```

Suggested fields:

```text
id
alert_id
alert_event_id
event_type
severity
payload_json
status
available_at
attempt_count
last_attempt_at
last_error_code
created_at
updated_at
completed_at
superseded_at
expired_at
```

Suggested statuses:

```text
pending
processing
delivered
failed
superseded
expired
```

Use one logical outbox item per notification-worthy alert event.

The alert event and outbox row must be created in the same DB transaction.

---

# 3. Push dispatch must survive process interruption

Create a dispatcher that processes outbox items after the transaction commits.

Preferred:

```text
cron/dispatch_push.php
```

or equivalent CLI worker.

Recommended schedule:

```cron
* * * * *
```

The dispatcher must:

1. lock/claim pending work safely,
2. send to active subscriptions,
3. create/update `push_deliveries`,
4. handle partial delivery success,
5. retry temporary failures,
6. disable permanently invalid subscriptions,
7. mark outbox completed when appropriate,
8. resume safely after interruption.

Do not rely on a request/collector process remaining alive long enough to finish all push delivery.

---

# 4. Prevent old notifications arriving after newer states

Before dispatching a pending notification, check whether a newer alert event already exists for the same alert.

Example:

```text
05:00 WATCH notification fails
05:04 CRITICAL event created
05:05 CRITICAL notification succeeds
05:07 old WATCH retry must NOT be delivered
```

The old WATCH item should become:

```text
superseded
```

Likewise, an old warning waiting for retry should not arrive after the alert has already been cleared.

---

# 5. Web Push delivery metadata

Use appropriate Web Push options:

```text
TTL
urgency
topic
```

Guidance:

```text
WATCH       → urgency normal
WARNING     → urgency high
CRITICAL    → urgency high
CLEARED     → urgency normal
```

Use a relatively short TTL for active flood-state notifications so stale warnings do not arrive many hours late.

Use a stable topic/tag per alert so newer state can replace older state where browser/platform behavior supports it.

Do not hardcode a TTL without documenting why it was chosen.

---

# 6. iOS/iPadOS Web Push behavior

The UI must handle the case where Web Push requires the app to be installed as a Home Screen web app.

If notification support is unavailable in the current browser context but may work after installation, show helpful guidance instead of a generic unsupported error.

Example:

```text
Add Ping Flood Watch to your Home Screen to enable flood alerts.
```

Do not repeatedly request permission.

Push testing must include real-device manual verification, not only Playwright.

---

# 7. Upstream status semantics

Separate:

```text
positive trend information
```

from:

```text
configured risk threshold crossing
```

Even when production upstream rise thresholds remain `null`, the UI may still display a factual trend:

```text
Rising
+8 cm / 1h
```

This is informational.

Only use:

```text
Rising fast
WATCH
WARNING
```

when the relevant configured threshold is enabled and crossed.

Do not create a hidden arbitrary threshold just to make the feature active.

---

# 8. Health must detect hung collectors

In addition to last-run age, add max collector runtime.

Suggested config:

```php
'health' => [
    'water_collector_max_age_minutes' => 15,
    'weather_collector_max_age_minutes' => 45,
    'water_collector_max_runtime_minutes' => 10,
    'weather_collector_max_runtime_minutes' => 20,
],
```

Exact defaults should match actual cron cadence and observed runtime.

Collector health states should support:

```text
ok
stale
failed
hung
missing
```

A collector left in `running` longer than max runtime must become `hung`.

---

# 9. Push endpoints need stronger abuse resistance

Keep same-origin `Origin` validation but do not treat it as authentication.

Add:

- request rate limiting,
- strict content-type validation,
- strict body-size limit,
- strict endpoint length limits,
- strict `p256dh` / `auth` length/format validation,
- no subscription secrets in logs,
- no list/read endpoint exposing subscriptions,
- generic error responses where appropriate.

Avoid storing full User-Agent unless needed. Prefer coarse client metadata or a shortened/anonymized value.

---

# 10. Test push must be CLI-only

Do not create a public production endpoint such as:

```text
/api/test-push.php
```

Preferred:

```bash
php cron/test_push.php
```

or:

```bash
php bin/test_push.php
```

It must:

- require explicit CLI execution,
- target an explicitly selected test subscription,
- clearly label the notification as TEST,
- never create a production flood alert,
- leave an audit log entry,
- support safe cleanup/disable of the test subscription.

---

# 11. Database changes must use migrations

Do not rely only on editing `sql/schema.sql`.

Create a versioned migration such as:

```text
sql/migrations/002_v1_1_push_and_health.sql
```

Use the project's existing migration/version mechanism if one exists.

Migration must be idempotent or migration-runner safe.

Keep `schema.sql` updated for fresh installs as well.

---

# 12. Staging must not notify production users

Staging must use one of:

```text
push.enabled = false
```

or separate staging VAPID keys and isolated staging subscriptions.

Never copy production `push_subscriptions` into staging.

If the staging DB is cloned from production, subscription data must be removed/disabled before staging processes run.

---

# 13. Rollback must include service worker/cache behavior

Code rollback alone is insufficient after a PWA asset/cache version change.

Rollback procedure must include:

1. restore previous compatible application code,
2. deploy a service worker with a **new** rollback cache version,
3. remove incompatible V1.1 caches,
4. verify clients receive the rollback worker/assets,
5. confirm stale V1.1 JS is not left controlling the app.

Document this in deployment instructions.

---

# 14. Release archives

Prefer release artifacts outside the live web tree.

Recommended:

```text
/var/backups/ping-flood-watch/releases/
```

Example:

```text
ping-flood-watch-v1.1.0.zip
```

Release package should exclude:

```text
secrets
node_modules
runtime logs
lock files
test caches
```

Whether `vendor/` is included remains a documented deployment choice.

---

# Definition of Done

This addendum is complete only when every applicable requirement in `07_ACCEPTANCE_TESTS.md` is satisfied and documented.

---

# FILE: 02_PUSH_OUTBOX_AND_DELIVERY.md

# Detail Mission — Push Outbox & Delivery Ordering

---

# 1. Required data model

Add:

```text
notification_outbox
push_subscriptions
push_deliveries
```

`push_deliveries` remains unique on:

```text
(alert_event_id, subscription_id)
```

Outbox must be unique enough to prevent duplicate logical jobs for the same alert event.

---

# 2. Transaction boundary

Correct:

```text
BEGIN
  persist alert event
  persist notification_outbox
COMMIT

dispatch later
```

Incorrect:

```text
BEGIN
  persist alert event
  send external Web Push
COMMIT
```

Never perform external push delivery inside the DB transaction.

---

# 3. Job claiming

Avoid two dispatcher processes sending the same job.

Use a safe claim mechanism such as:

- DB row lock with `FOR UPDATE SKIP LOCKED` if supported,
- atomic status update from `pending` to `processing`,
- worker token + claimed timestamp.

Recover jobs stuck in `processing` after a configurable timeout.

---

# 4. Retry

Temporary failures may retry with exponential backoff.

Example schedule concept:

```text
1 min
2 min
5 min
10 min
20 min
```

Do not require these exact values if library/backend behavior suggests a better sequence.

Maximum attempts:

```text
5
```

After maximum attempts:

```text
failed
```

---

# 5. Superseding logic

Before each retry:

- find latest event for same alert,
- compare event ordering,
- if a newer event makes this message obsolete, set `superseded`,
- do not send.

Examples:

```text
WATCH superseded by WARNING
WARNING superseded by CRITICAL
WATCH/WARNING/CRITICAL superseded by CLEARED
```

A `CLEARED` message itself is not superseded unless a new alert lifecycle has started and event identity proves it belongs to an older incident.

---

# 6. Expiry

Outbox items older than their safe notification age should become:

```text
expired
```

Expiry must be severity/event aware if useful.

Do not deliver an old flood warning just because network connectivity returns many hours later.

---

# 7. Push options

Configure:

```text
TTL
urgency
topic
```

Topic should be stable and privacy-safe.

Do not put private subscription values in topic/tag.

---

# 8. Logging

Log:

```text
alert_event_id
outbox_id
delivery status
subscription hash/id
HTTP result class
error code
attempt count
```

Never log:

```text
endpoint
p256dh
auth
private VAPID key
```

---

# FILE: 03_IOS_ANDROID_PUSH_QA.md

# Detail Mission — iOS/iPadOS & Android Push QA

---

# 1. iOS installation guidance

Detect capabilities progressively.

If browser notifications are not available in the current iOS context but the app can support them as an installed Home Screen PWA, display:

```text
Add Ping Flood Watch to your Home Screen to enable flood alerts.
```

Do not show an endless permission button that cannot work.

---

# 2. Permission flow

State examples:

```text
unsupported
install_required
not_requested
granted
denied
subscribed
subscription_error
```

UI must present the correct next action.

---

# 3. Real-device manual QA

V1.1 push is not accepted based only on automated browser tests.

Manually verify at least:

## Android

```text
Chrome
installed PWA or browser context as supported
```

Verify:

- subscribe,
- notification while app closed/backgrounded,
- click opens/focuses Alerts,
- escalation replaces/updates sensibly,
- unsubscribe.

## iPhone

```text
Safari
Add to Home Screen
Home Screen PWA
```

Verify:

- permission after user action,
- subscription,
- notification received,
- notification tap opens/focuses app,
- no silent push handling,
- unsubscribe/disable.

Record OS/browser versions in TEST_REPORT.

---

# 4. Localization

Push content must use the subscription/user language when known.

At minimum test:

```text
English
Thai
```

Fallback safely to English if unsupported language metadata is missing.

---

# 5. Test notification

Use CLI-only test notification.

Example:

```bash
php cron/test_push.php --subscription=<safe-test-id>
```

The generated notification must clearly contain:

```text
TEST
```

and must not be stored as a real flood incident.

---

# FILE: 04_UPSTREAM_SIGNAL_SEMANTICS.md

# Detail Mission — Upstream Signal Semantics

---

# 1. Goal

Make upstream monitoring useful even while official/configured rapid-rise thresholds remain disabled.

---

# 2. Separate factual trend from risk classification

Trend:

```text
falling
stable
rising
unknown
```

Risk signal:

```text
normal
rising_fast
watch
warning
unknown
```

A factual positive change may produce:

```text
rising
```

without producing:

```text
watch
```

---

# 3. Suggested factual trend rule

Do not invent a flood threshold.

A factual trend may be determined using measurement direction with a very small noise/deadband rule already used elsewhere in the app, if available.

If no safe existing deadband exists, display numerical change and arrow without assigning a named `rising` classification.

Example:

```text
↑ +8 cm / 1h
```

is always preferable to inventing:

```text
RISING FAST
```

without configured criteria.

---

# 4. Threshold-based early warning

Only enable:

```text
RISING FAST
WATCH
WARNING
```

when corresponding configured upstream thresholds are non-null.

`null` means disabled.

---

# 5. UI

Examples:

```text
P.67
0.81 m
↑ +8 cm / 1h
Rising
```

With configured threshold crossed:

```text
P.67
0.99 m
↑ +24 cm / 1h
Rising fast
```

Use color plus text/icon.

---

# 6. No arrival-time claims

Do not display:

```text
will reach P.1 in 6 hours
```

unless a future verified model explicitly supports that prediction.

---

# FILE: 05_HEALTH_AND_HUNG_COLLECTORS.md

# Detail Mission — Collector Health & Hung Detection

---

# 1. Required states

Collector health:

```text
ok
stale
failed
hung
missing
```

---

# 2. Definitions

## ok

Recent successful/valid run and not exceeding runtime.

## stale

No sufficiently recent completed run.

## failed

Latest completed run ended failed.

## hung

Collector status remains running beyond configured max runtime.

## missing

No collector-run history exists.

---

# 3. Config

Add:

```php
'health' => [
    'water_collector_max_age_minutes' => 15,
    'weather_collector_max_age_minutes' => 45,
    'water_collector_max_runtime_minutes' => 10,
    'weather_collector_max_runtime_minutes' => 20,
],
```

Codex should inspect real collector duration and cadence before finalizing defaults.

---

# 4. Running collector nuance

A collector that started 30 seconds ago should not make health degraded if:

- previous successful data is still within freshness limits,
- runtime is below max runtime.

A collector that has been running beyond max runtime becomes:

```text
hung
```

---

# 5. API

`/api/health.php` should expose sanitized collector state.

Example:

```json
{
  "name": "water",
  "status": "hung",
  "started_at": "...",
  "age_minutes": 14,
  "max_runtime_minutes": 10
}
```

Do not expose filesystem paths or command lines.

---

# FILE: 06_SECURITY_MIGRATION_ROLLBACK.md

# Detail Mission — Security, Migration, Staging & Rollback

---

# 1. Push endpoint rate limiting

Apply a conservative limit to:

```text
POST api/push-subscribe.php
POST api/push-unsubscribe.php
```

A simple DB/file/cache-backed IP/request limiter is acceptable if it matches the current architecture.

Do not introduce Redis just for this feature.

---

# 2. Input limits

Validate:

```text
Content-Type: application/json
body <= 16 KiB
endpoint URL scheme = https
endpoint length <= documented safe max
p256dh length/encoding valid
auth length/encoding valid
language allowlist
```

Reject unknown nested structure where practical.

---

# 3. Secrets

Never log or expose:

```text
private VAPID key
subscription endpoint
p256dh
auth
```

Use endpoint hash or internal subscription ID in logs.

---

# 4. Migration

Create versioned migration:

```text
sql/migrations/002_v1_1_push_and_health.sql
```

or next correct number according to existing project history.

Migration should create/update:

```text
push_subscriptions
push_deliveries
notification_outbox
indexes
health/config support if DB-backed
```

Update `schema.sql` too for clean installs.

---

# 5. Staging isolation

Before staging workers run:

- set `push.enabled=false`, OR
- use staging-only VAPID keys and staging-only subscriptions.

If staging DB originates from production:

```text
TRUNCATE/disable push_subscriptions
clear pending notification_outbox
clear push_deliveries if appropriate
```

Do not send staging events to production subscribers.

---

# 6. Rollback plan

Document commands/steps to:

1. disable push,
2. stop/disable push dispatcher cron,
3. restore previous code,
4. deploy rollback service worker with a newer rollback cache identifier,
5. purge incompatible client caches through SW activation logic,
6. verify API/current UI compatibility,
7. verify water/weather collectors continue,
8. retain new DB tables unless they actively conflict.

Do not drop notification tables automatically during rollback.

---

# 7. Release storage

Preferred:

```text
/var/backups/ping-flood-watch/releases/
```

Example:

```text
ping-flood-watch-v1.1.0.zip
```

Generate SHA-256:

```text
ping-flood-watch-v1.1.0.zip.sha256
```

Recommended release contents:

```text
application code
vendor if deploy policy requires it
docs
tests
sql/migrations
composer lock
package lock
```

Exclude:

```text
node_modules
secrets
logs
locks
test caches
runtime cache
```

---

# FILE: 07_ACCEPTANCE_TESTS.md

# V1.1 Hardening Addendum — Acceptance Tests

Update `docs/TEST_REPORT.md`.

---

# A. Outbox transaction

- [ ] Alert event and outbox item are committed atomically.
- [ ] External push is not sent inside DB transaction.
- [ ] Process crash after commit leaves recoverable pending job.
- [ ] Dispatcher later resumes that job.

---

# B. Dispatcher concurrency

- [ ] Two dispatcher processes cannot deliver same job twice.
- [ ] Abandoned `processing` claim can recover after timeout.
- [ ] Per-subscription delivery dedupe still holds.

---

# C. Superseded notifications

- [ ] Failed WATCH retry is superseded by newer WARNING.
- [ ] Failed WARNING retry is superseded by newer CRITICAL.
- [ ] Pending active warning is superseded by CLEARED.
- [ ] Superseded item is not sent later.
- [ ] Event ordering is based on persisted event identity/time, not UI state guesswork.

---

# D. Expiry / TTL

- [ ] Push items have documented TTL policy.
- [ ] Old warning becomes expired rather than delivered dangerously late.
- [ ] WARNING/CRITICAL uses appropriate urgency.
- [ ] Stable topic/tag is used without leaking private data.

---

# E. Composer/runtime

- [ ] PHP version supports chosen Web Push package.
- [ ] `ext-mbstring` available.
- [ ] `ext-openssl` available.
- [ ] Composer dependency choice documented.
- [ ] Async Guzzle adapter added only if actually using pooled async delivery.

---

# F. iPhone/iPad

- [ ] Unsupported browser context does not show misleading Enable button.
- [ ] Home Screen installation guidance appears where needed.
- [ ] Permission requested only after user action.
- [ ] Real iPhone Home Screen PWA receives test push.
- [ ] Notification opens/focuses app.
- [ ] No silent push behavior.

---

# G. Android

- [ ] Real Android Chrome/PWA receives test push.
- [ ] Background/closed app scenario tested.
- [ ] Notification click opens/focuses app.
- [ ] Unsubscribe tested.

---

# H. Upstream semantics

- [ ] Numerical positive upstream trend can be shown without Watch.
- [ ] Null rapid-rise thresholds do not trigger Watch/Warning.
- [ ] Configured threshold crossing triggers correct reason code.
- [ ] `Rising fast` is never invented from an undocumented constant.
- [ ] No exact downstream arrival prediction is shown.

---

# I. Hung collectors

- [ ] Recent running collector below max runtime is not incorrectly degraded.
- [ ] Water collector over max runtime becomes `hung`.
- [ ] Weather collector over max runtime becomes `hung`.
- [ ] Missing collector becomes `missing`.
- [ ] Failed collector becomes `failed`.
- [ ] Old successful collector becomes `stale`.

---

# J. Push endpoint security

- [ ] Wrong method rejected.
- [ ] Wrong content type rejected.
- [ ] Body over 16 KiB rejected.
- [ ] Invalid endpoint rejected.
- [ ] Invalid key lengths/encoding rejected.
- [ ] Invalid language rejected.
- [ ] Rate limiting works.
- [ ] Logs contain no endpoint/p256dh/auth/private VAPID key.

---

# K. CLI test push

- [ ] Test push only available from CLI.
- [ ] Test message clearly says TEST.
- [ ] Test does not create production alert.
- [ ] Test subscription can be disabled/removed afterward.

---

# L. Migrations

- [ ] V1 database upgrades through migration.
- [ ] Re-running migration mechanism does not corrupt schema.
- [ ] Fresh install `schema.sql` includes same final structure.
- [ ] Rollback does not require dropping new tables.

---

# M. Staging isolation

- [ ] Staging cannot notify production subscriptions.
- [ ] Production subscription rows are absent/disabled in staging.
- [ ] Push dispatcher cannot accidentally run with production credentials on staging.

---

# N. PWA rollback

- [ ] V1.1 deploy updates service-worker/cache version.
- [ ] Simulated rollback deploys a newer rollback SW/cache ID.
- [ ] Old incompatible V1.1 cache is removed.
- [ ] Client returns to compatible JS/CSS after refresh/reopen.

---

# O. Release artifact

- [ ] Release ZIP stored outside web tree.
- [ ] ZIP filename includes version.
- [ ] SHA-256 generated.
- [ ] No secrets.
- [ ] No node_modules.
- [ ] No runtime logs/locks/test caches.
- [ ] Required migrations/docs included.

---

# Definition of Done

This hardening addendum is complete only when:

- push delivery ordering is safe,
- real iPhone and Android push tests pass,
- stale notification delivery is prevented,
- collector hung state is detected,
- staging push isolation is verified,
- rollback including service worker has been tested/documented.
