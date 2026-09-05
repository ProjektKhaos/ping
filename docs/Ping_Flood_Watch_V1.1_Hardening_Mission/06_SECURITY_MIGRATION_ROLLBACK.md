# Detail Mission — Security, Migration, Staging & Rollback

---

# 1. Push endpoint rate limiting

Apply a conservative limit to:

```text
POST api/push-subscribe.php
POST api/push-unsubscribe.php
```

A simple DB/file/cache-backed IP/request limiter is acceptable if it matches the current architecture.

Do not introduce Redis just for this feature.

---

# 2. Input limits

Validate:

```text
Content-Type: application/json
body <= 16 KiB
endpoint URL scheme = https
endpoint length <= documented safe max
p256dh length/encoding valid
auth length/encoding valid
language allowlist
```

Reject unknown nested structure where practical.

---

# 3. Secrets

Never log or expose:

```text
private VAPID key
subscription endpoint
p256dh
auth
```

Use endpoint hash or internal subscription ID in logs.

---

# 4. Migration

Create versioned migration:

```text
sql/migrations/002_v1_1_push_and_health.sql
```

or next correct number according to existing project history.

Migration should create/update:

```text
push_subscriptions
push_deliveries
notification_outbox
indexes
health/config support if DB-backed
```

Update `schema.sql` too for clean installs.

---

# 5. Staging isolation

Before staging workers run:

- set `push.enabled=false`, OR
- use staging-only VAPID keys and staging-only subscriptions.

If staging DB originates from production:

```text
TRUNCATE/disable push_subscriptions
clear pending notification_outbox
clear push_deliveries if appropriate
```

Do not send staging events to production subscribers.

---

# 6. Rollback plan

Document commands/steps to:

1. disable push,
2. stop/disable push dispatcher cron,
3. restore previous code,
4. deploy rollback service worker with a newer rollback cache identifier,
5. purge incompatible client caches through SW activation logic,
6. verify API/current UI compatibility,
7. verify water/weather collectors continue,
8. retain new DB tables unless they actively conflict.

Do not drop notification tables automatically during rollback.

---

# 7. Release storage

Preferred:

```text
/var/backups/ping-flood-watch/releases/
```

Example:

```text
ping-flood-watch-v1.1.0.zip
```

Generate SHA-256:

```text
ping-flood-watch-v1.1.0.zip.sha256
```

Recommended release contents:

```text
application code
vendor if deploy policy requires it
docs
tests
sql/migrations
composer lock
package lock
```

Exclude:

```text
node_modules
secrets
logs
locks
test caches
runtime cache
```
