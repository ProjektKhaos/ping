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
