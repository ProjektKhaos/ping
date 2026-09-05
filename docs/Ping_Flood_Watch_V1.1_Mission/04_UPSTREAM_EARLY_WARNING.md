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
