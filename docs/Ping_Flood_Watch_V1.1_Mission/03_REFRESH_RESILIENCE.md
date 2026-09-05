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
