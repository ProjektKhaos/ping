# River risk, alerts and Web Push

Risk engines return deterministic `severity`, stable `reason_codes`, `message_key`, context values and affected station IDs. Translation occurs only in the presentation and notification-payload layers.

River Risk requires a numeric, non-stale primary reading. ThaiWater `high` produces Watch; primary `overflow` produces Warning. P.1 compares gauge datum with sourced thresholds:

- Warning/overflow: 3.70 m, [Royal Irrigation Department](https://www.hydro-1.net/Data/STATION/P.1.html).
- App critical: 4.20 m, [Chiang Mai provincial action levels](https://www.chiangmai.go.th/managing/public/M1/D24Nov2025204507.pdf).

The UI may show a numeric positive P.67/P.103 one-hour change with an arrow. `Rising fast`, Watch or Warning requires an explicitly configured upstream threshold. Production upstream thresholds remain `null`; upstream data alone cannot make Critical or an arrival-time forecast. No hydrological future-level prediction or evacuation decision is produced.

Combined Advisory keeps River and Weather Risk separate. High weather can raise a normal river situation to Watch; high weather plus River Watch raises Warning; severe rainfall plus River Warning raises Critical. Any unknown input makes the combined result unknown, preventing false reassurance.

## Alert lifecycle and atomic outbox

`AlertManager` serializes lifecycle changes using a MariaDB named lock and permits only one active incident. Escalation is immediate. A downgrade or clear begins `pending_since` and occurs only after 20 continuous minutes. Unknown, stale data or provider failure never clears or downgrades an active incident.

Only `opened`, `escalated` and `cleared` create notification jobs. The alert event and exactly one language-neutral `notification_outbox` row commit in the same transaction; no external request occurs inside that transaction. Equal severity, pending downgrade and completed downgrade create no push job.

`cron/dispatch_push.php` runs every minute with both a filesystem lock and MariaDB `FOR UPDATE SKIP LOCKED`. A 32-character claim token owns work; processing claims older than five minutes return to pending. Unique `(alert_event_id, subscription_id)` delivery rows prevent parallel duplicate fan-out. Subscribers are bounded to IDs existing when the event was committed and must still be active at delivery time.

Transient delivery gets at most five total attempts: immediate, then after 1, 2, 5 and 10 minutes. HTTP 404/410 or the Web Push library's expired signal permanently disables the subscription. Before every delivery attempt, persisted later events are checked. Old Watch/Warning/Critical messages become `superseded` after escalation, downgrade or clear; an old clear is superseded after a new incident opens.

Active notifications expire after 30 minutes and clear notifications after two hours. Web Push TTL never exceeds remaining validity. Watch and cleared use normal urgency; Warning and Critical use high. The privacy-safe topic is `pfw-a-{base36 alert id}` and the notification tag is `pfw-alert-{base36 alert id}`. Delivery is at-least-once around a process crash immediately after provider acceptance; topic/tag makes a visible duplicate replaceable.

## Keys, libraries and user flow

Production uses `minishlink/web-push` 11 with the regular Guzzle PSR-18 client in controlled sequential batches; no async adapter is installed. One VAPID key pair and the rate-limit HMAC secret live only in `/etc/ping-flood-watch/config.php`. VAPID subject is `https://ping.aberg.online/`.

The Alerts page represents unsupported, install-required, not-requested, denied, subscribed and error states. Permission is requested only after the user taps Enable. iPhone/iPad Safari outside an installed Home Screen PWA shows installation guidance instead of a non-working button. Subscription language is `en` or `th`; the worker displays already-localized content and falls back safely.

The service worker accepts only a same-origin target within its current scope. An invalid or external target opens Alerts. It uses notification display only—there is no silent background processing.

The only direct test sender is CLI-only:

```bash
sudo -u www-data php cron/test_push.php --list
sudo -u www-data php cron/test_push.php --subscription-id=123 --confirm-test --disable-after
```

The message is visibly marked TEST, does not create a flood incident and logs only internal ID plus a shortened endpoint hash.
