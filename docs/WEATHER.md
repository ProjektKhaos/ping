# Weather forecast and risk

## Provider and geography

Production uses `OpenMeteoProvider` behind `WeatherProviderInterface`, calling `https://api.open-meteo.com/v1/forecast` without a key for the selected non-commercial deployment. Requests use a 48-hour hourly forecast in `Asia/Bangkok` with `precipitation` (mm), `precipitation_probability` (%) and WMO weather code. Attribution is visible in the UI and API metadata under the [Open-Meteo licence](https://open-meteo.com/en/license) and [free API terms](https://open-meteo.com/en/terms).

V1.1 validates response location count, timezone metadata, hourly unit metadata, equal array lengths, strict local timestamps, non-negative precipitation and probabilities in 0–100. Missing unit/timezone metadata is rejected instead of guessed. A provider error retains the previous forecast and makes risk Unknown once health/freshness is no longer acceptable.

P.67 and P.103 are upstream point forecasts and affect Weather Risk. P.1 is stored as a city reference but excluded from upstream risk. Displayed min–max values are geographic ranges between the two upstream points, never uncertainty margins.

Each run stores receipt time, nullable provider issue time, raw server-side payload and hourly validity windows in UTC. Open-Meteo does not expose a distinct model issue timestamp in this response, so `issued_at` remains `null` rather than being invented. Identical normalized forecasts deduplicate by SHA-256. Any missing hourly rainfall makes its affected accumulation `null`, not zero.

## Accumulations and freshness

The collector calculates 1, 3, 6, 12, 24 and 48-hour accumulation, maximum hourly intensity and maximum probability for every point. `CURRENT` is at most 90 minutes old, `AGING` is at most 3 hours old, then the forecast is `STALE` and Weather Risk is `UNKNOWN`.

## TMD categories

Only verified 24-hour and one-hour thresholds affect V1 risk. Other horizons are informative.

| Risk | 24-hour total | One-hour intensity |
|---|---:|---:|
| Low | ≤ 10.0 mm | ≤ 5.0 mm |
| Moderate | 10.1–35.0 mm | 5.1–25.0 mm |
| High | 35.1–90.0 mm | 25.1–50.0 mm |
| Very high | ≥ 90.1 mm | ≥ 50.1 mm |

Source: [Thai Meteorological Department rainfall categories](https://tmd.go.th/info/%E0%B8%81%E0%B8%B2%E0%B8%A3%E0%B8%A7%E0%B8%B1%E0%B8%94%E0%B8%9B%E0%B8%A3%E0%B8%B4%E0%B8%A1%E0%B8%B2%E0%B8%93%E0%B8%99%E0%B9%89%E0%B8%B3%E0%B8%9F%E0%B9%89%E0%B8%B2%E0%B8%AB%E0%B8%A3%E0%B8%B7%E0%B8%AD%E0%B8%AB%E0%B8%A2%E0%B8%B2%E0%B8%94%E0%B8%99%E0%B9%89%E0%B8%B3%E0%B8%9F%E0%B9%89%E0%B8%B2). Thresholds are configuration-driven. TMD’s OAuth-protected NWP API remains a future official adapter. Radar and hydrological level forecasting are outside V1.1.
