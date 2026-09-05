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
