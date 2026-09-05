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
