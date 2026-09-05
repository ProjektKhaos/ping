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
