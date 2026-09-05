# Detail Mission — Push Outbox & Delivery Ordering

---

# 1. Required data model

Add:

```text
notification_outbox
push_subscriptions
push_deliveries
```

`push_deliveries` remains unique on:

```text
(alert_event_id, subscription_id)
```

Outbox must be unique enough to prevent duplicate logical jobs for the same alert event.

---

# 2. Transaction boundary

Correct:

```text
BEGIN
  persist alert event
  persist notification_outbox
COMMIT

dispatch later
```

Incorrect:

```text
BEGIN
  persist alert event
  send external Web Push
COMMIT
```

Never perform external push delivery inside the DB transaction.

---

# 3. Job claiming

Avoid two dispatcher processes sending the same job.

Use a safe claim mechanism such as:

- DB row lock with `FOR UPDATE SKIP LOCKED` if supported,
- atomic status update from `pending` to `processing`,
- worker token + claimed timestamp.

Recover jobs stuck in `processing` after a configurable timeout.

---

# 4. Retry

Temporary failures may retry with exponential backoff.

Example schedule concept:

```text
1 min
2 min
5 min
10 min
20 min
```

Do not require these exact values if library/backend behavior suggests a better sequence.

Maximum attempts:

```text
5
```

After maximum attempts:

```text
failed
```

---

# 5. Superseding logic

Before each retry:

- find latest event for same alert,
- compare event ordering,
- if a newer event makes this message obsolete, set `superseded`,
- do not send.

Examples:

```text
WATCH superseded by WARNING
WARNING superseded by CRITICAL
WATCH/WARNING/CRITICAL superseded by CLEARED
```

A `CLEARED` message itself is not superseded unless a new alert lifecycle has started and event identity proves it belongs to an older incident.

---

# 6. Expiry

Outbox items older than their safe notification age should become:

```text
expired
```

Expiry must be severity/event aware if useful.

Do not deliver an old flood warning just because network connectivity returns many hours later.

---

# 7. Push options

Configure:

```text
TTL
urgency
topic
```

Topic should be stable and privacy-safe.

Do not put private subscription values in topic/tag.

---

# 8. Logging

Log:

```text
alert_event_id
outbox_id
delivery status
subscription hash/id
HTTP result class
error code
attempt count
```

Never log:

```text
endpoint
p256dh
auth
private VAPID key
```
