# Water providers

## Production: ThaiWater

Adapter: `ThaiWaterProvider`, behind `WaterProviderInterface`.

- Current: `https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_load`
- History: `https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_graph?station_type=tele_waterlevel&station_id={id}`
- Verified live on 2026-08-21.

Mapped fields are `station.tele_station_oldcode`, `waterlevel_datetime`, `waterlevel_msl`, `storage_percent`, `situation_level`, `discharge`, and the history fields `data.graph_data[].datetime/value/discharge`, `data.min_bank`, and `data.ground_level`. Current and graph water levels are metres MSL. Source timestamps are interpreted as `Asia/Bangkok`, retained verbatim, then normalized to UTC.

Station metadata:

| Code | Provider ID | Gauge zero MSL | Coordinates |
|---|---:|---:|---|
| P.67 | 3247 | 315.929993 m | 19.009850, 98.959740 |
| P.103 | 504679 | 300.890015 m | 18.866510, 98.978188 |
| P.1 | 3226 | 300.500000 m | 18.786961, 99.005089 |

Gauge is calculated only as `MSL − verified gauge zero`; otherwise it remains `null`. MSL and gauge stay separate in storage and API output. A changed value for an existing station/time creates `measurement_revisions` before replacement. Source hashes prevent silent duplicates.

ThaiWater capacity status is normalized as `normal`, `high`, or `overflow`; low statuses are preserved but do not imply flood risk. Invalid JSON, content type, schema, timestamp, unit or response size is rejected. Failure updates configured provider health, logs a sanitized error, preserves prior measurements and forces `unknown` instead of false normal when no elevated risk is already visible. Health and freshness provider names/limits come only from `Config`; collector states are `ok`, `stale`, `failed`, `hung` or `missing`.

Sanitized contract fixtures are in `docs/provider_samples/`. They contain public, reduced fields only; application APIs never expose raw payloads.

## Production: CMFlood / Chiang Mai University

Adapter: `CmfloodProvider`, also behind `WaterProviderInterface`. The public source is the [CMFlood station warning page](https://watercenter.cmu.ac.th/chiangmai/cmflood/warning/station), whose browser client reads public station observations from its Supabase REST API. Verified live on 2026-08-21.

| Code | Public source mapping | Gauge zero MSL | River role |
|---|---|---:|---|
| CMI01 | `disaster_level / CMI01` | 300.300 m | Upstream |
| CMI02 | `disaster_level / CMI02` | 300.231 m | Upstream |
| FBP.2 | `water_levels / 22` | 294.200 m | Downstream |
| CMI03 | `disaster_level / CMI03` | 299.620 m | Downstream |
| FBP.3 | `water_levels / 21` | 292.930 m | Downstream |
| P.104 | `flagship_hydro / ST.16` | 294.050 m | Downstream |

CMFlood values are gauge metres. MSL is derived only from the source's station metadata. The source currently serializes Bangkok local wall-clock timestamps with a `+00:00` suffix; the adapter preserves the original value, interprets its clock portion as `Asia/Bangkok`, and stores normalized UTC. Station-specific plausibility checks used by CMFlood's own browser client are retained for CMI01 (`≤4 m`) and CMI02 (`≥2 m`), in addition to the general `0–20 m` validation.

The combined north-to-south display order is P.67, P.103, CMI01, CMI02, P.1, FBP.2, CMI03, FBP.3 and P.104. P.1 remains the primary flood-risk reference. Downstream stations are informational and cannot escalate upstream risk. Failure of this secondary provider preserves existing values and is recorded separately; it does not stop ThaiWater collection.

## Station map

The Stations page uses self-hosted Leaflet 1.9.4 and Leaflet.markercluster 1.5.3 with the standard OpenStreetMap raster layer at `https://tile.openstreetmap.org/{z}/{x}/{y}.png`. No API key is required. The layer starts only after the visitor opens Map, requests only tiles needed for the visible viewport, uses normal browser caching and displays `© OpenStreetMap contributors` on the map.

Remote tiles are deliberately excluded from the service-worker shell and every offline snapshot. Bulk download, prefetch and offline tile storage are not implemented. If the layer is unavailable, the station list and stored readings remain usable. Opening Map sends the normal browser IP address and same-origin referrer to OpenStreetMap's tile service under its privacy and [tile usage policy](https://operations.osmfoundation.org/policies/tiles/). Leaflet and marker-cluster licence texts are shipped beside their self-hosted assets.

## Development: mock provider

`MockWaterProvider` is deterministic enough for local/UI work and marks records `demo`. Production configuration never selects it. Live smoke tests are opt-in so the normal suite is network-independent.
