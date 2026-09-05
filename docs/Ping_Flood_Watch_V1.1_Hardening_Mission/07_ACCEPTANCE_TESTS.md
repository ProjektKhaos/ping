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
