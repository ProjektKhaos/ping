# JSON API

All endpoints are same-origin and `Cache-Control: no-store`. Success is `{ "ok": true, "data": ..., "meta": ... }`; errors are `{ "ok": false, "error": { "code": "...", "message": "..." } }`. Times are UTC database timestamps; display clients convert to `Asia/Bangkok`.

| Endpoint | Method / parameters | Purpose |
|---|---|---|
| `/api/current.php` | GET `lang=en|th` | Stations plus River, Weather and Combined risks |
| `/api/stations.php` | GET `lang=en|th` | Station metadata, latest readings and trends |
| `/api/history.php` | GET `station=P.67|P.103|P.1`, `period=24h|48h|72h` | Gauge/MSL history |
| `/api/weather.php` | GET `lang=en|th` | Forecast aggregates and Weather Risk |
| `/api/forecast.php` | GET `zone=P.67|P.103|P.1`, `period=24h|48h` | Hourly rain/probability |
| `/api/alerts.php` | GET `lang=en|th` | Sanitized, localized alert history |
| `/api/push-subscribe.php` | POST JSON | Upsert an active browser subscription |
| `/api/push-unsubscribe.php` | POST JSON | Disable a browser subscription by endpoint |
| `/api/status.php` | GET | Shared provider/collector evaluation, always HTTP 200 |
| `/api/health.php` | GET | Same evaluation, HTTP 503 when degraded |

`status`/`health` expose only sanitized provider and collector records. Collector states are `ok`, `stale`, `failed`, `hung` and `missing`. A currently running collector is okay only below its configured 3-minute water or 5-minute weather runtime and while its preceding success remains within 15 or 45 minutes.

## Push request contract

Both write endpoints require POST, an `application/json` content type, a body no larger than 16 KiB and an `Origin` exactly matching configured public origin. Origin is an additional same-origin check, not authentication. Limits use an HMAC of the actual `REMOTE_ADDR`; proxy-forwarded client headers are ignored. Fixed ten-minute limits are 10 subscribe and 20 unsubscribe requests. HTTP 429 includes `Retry-After`.

Subscribe accepts exactly:

```json
{
  "endpoint": "https://push-service.example/...",
  "expirationTime": null,
  "keys": {"p256dh": "base64url", "auth": "base64url"},
  "contentEncoding": "aes128gcm",
  "language": "en"
}
```

Endpoint must be HTTPS and at most 2048 characters. Decoded `p256dh` must be exactly 65 bytes, `auth` exactly 16, encoding is `aes128gcm|aesgcm`, and language is `en|th`. Unknown top-level or nested keys are rejected. Unsubscribe accepts only `{"endpoint":"https://..."}`.

Stable push error codes include `METHOD_NOT_ALLOWED`, `INVALID_CONTENT_TYPE`, `INVALID_ORIGIN`, `REQUEST_TOO_LARGE`, `INVALID_JSON`, `INVALID_SUBSCRIPTION`, `INVALID_LANGUAGE`, `RATE_LIMITED` and `PUSH_DISABLED`.

Invalid station/zone/period/language values return HTTP 400. Missing seeded records return HTTP 404. Unexpected errors return HTTP 500 `INTERNAL_ERROR` without stack traces, SQL or credentials. Provider payloads, subscription endpoints/keys, VAPID private key, configuration and detailed upstream errors are never returned.
