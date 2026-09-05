# Mission Detail — Real Web Push Notifications

**Priority:** P0

---

# 1. Problem

The current app creates and stores alerts and alert events, but users receive no notification if the PWA/browser is closed.

The app must be able to actively warn an opted-in user.

---

# 2. Architecture

Use this flow:

```text
RiskCoordinator
    ↓
AlertManager
    ↓
persisted alert / alert_event
    ↓
NotificationDispatcher
    ↓
Web Push provider
    ↓
Service Worker
    ↓
Mobile notification
```

Do not send push directly from `WarningEngine` or provider code.

---

# 3. User opt-in

Add a clear user-controlled notification setting.

Example UI:

```text
Flood alerts
[ Enable notifications ]
```

After enabled:

```text
Flood alerts
✓ Notifications enabled
[ Disable ]
```

Rules:

- never request browser permission immediately on first page load,
- permission request must follow a user gesture,
- show useful text explaining what will be sent,
- handle denied permission gracefully,
- do not nag after denial.

---

# 4. Service worker

Extend `sw.js` with:

```javascript
self.addEventListener('push', ...)
self.addEventListener('notificationclick', ...)
```

Notification payload should support:

```text
title
body
severity
alert_id
event_type
url
tag
timestamp
```

Use a stable notification `tag` to prevent duplicates where appropriate.

Click should open/focus:

```text
alerts.php
```

or the appropriate alert URL within the current PWA scope.

Must remain portable under a subdirectory `BASE_URL`.

---

# 5. Server-side Web Push

Use a maintained PHP Web Push implementation.

Preferred:

```text
minishlink/web-push
```

if compatible with the project's PHP version and dependency constraints.

Do not implement Web Push cryptography manually.

Store VAPID keys outside the repository.

Suggested config:

```php
'push' => [
    'enabled' => false,
    'subject' => 'mailto:...',
    'public_key' => '',
    'private_key' => '',
],
```

`config.example.php` must contain placeholders only.

---

# 6. Database

Add an idempotent `push_subscriptions` table.

Suggested fields:

```text
id
endpoint_hash
endpoint
p256dh
auth
content_encoding
language
user_agent
created_at
updated_at
last_success_at
failure_count
disabled_at
```

Unique:

```text
endpoint_hash
```

Do not log the full endpoint unnecessarily.

Add a delivery/deduplication table, for example:

```text
push_deliveries
```

Fields:

```text
id
alert_event_id
subscription_id
status
http_status
error_code
attempted_at
delivered_at
```

Unique pair:

```text
(alert_event_id, subscription_id)
```

This prevents repeat delivery.

---

# 7. Alert transitions that should notify

At minimum:

```text
opened
escalated
cleared
```

Optional:

```text
deescalated
```

Do not notify on every same-severity `touch/update`.

Recommended behavior:

- new WATCH → notify,
- WATCH → WARNING → notify,
- WARNING → CRITICAL → notify,
- CRITICAL → WARNING may optionally notify,
- cleared → notify once,
- normal collector refresh with no transition → no notification.

---

# 8. Notification wording

Use localized strings.

English examples:

```text
Ping Flood Watch — Watch
River/weather conditions need attention.
```

```text
Ping Flood Watch — Warning
Flood potential has increased. Open the app for current details.
```

```text
Ping Flood Watch — Critical
Critical river-risk conditions are being reported by the monitoring system.
```

Clear:

```text
Ping Flood Watch
The active warning has cleared.
```

Do not claim official evacuation instructions unless supplied by an official source.

---

# 9. Failed subscriptions

Handle Web Push responses indicating expired/invalid subscription.

For 404/410-style permanent failures:

- mark subscription disabled,
- do not keep retrying forever.

Temporary failure:

- increment failure count,
- retain subscription,
- log concise error.

---

# 10. API

Add protected-by-design same-origin endpoints:

```text
POST api/push-subscribe.php
POST api/push-unsubscribe.php
```

Validate JSON body.

Subscription endpoint should accept only expected fields.

Do not expose private VAPID key.

Provide public VAPID key to frontend via safe endpoint/config/rendered data.

---

# 11. Security

- HTTPS required in production.
- Validate subscription structure.
- Limit request body size.
- No SQL string interpolation.
- Escape UI.
- Do not expose other users' subscriptions.
- Do not expose endpoint lists publicly.
- Consider basic same-origin/CSRF protection appropriate to the current app architecture.

---

# 12. Tests

Required:

- subscribe,
- duplicate subscribe updates existing subscription,
- unsubscribe,
- invalid subscription rejected,
- event delivery recorded,
- same event cannot be delivered twice to same subscription,
- expired subscription disabled,
- no push on same-severity refresh,
- push on open/escalation/clear,
- service worker handles push payload,
- notification click opens correct in-scope URL.
