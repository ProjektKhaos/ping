# Detail Mission — iOS/iPadOS & Android Push QA

---

# 1. iOS installation guidance

Detect capabilities progressively.

If browser notifications are not available in the current iOS context but the app can support them as an installed Home Screen PWA, display:

```text
Add Ping Flood Watch to your Home Screen to enable flood alerts.
```

Do not show an endless permission button that cannot work.

---

# 2. Permission flow

State examples:

```text
unsupported
install_required
not_requested
granted
denied
subscribed
subscription_error
```

UI must present the correct next action.

---

# 3. Real-device manual QA

V1.1 push is not accepted based only on automated browser tests.

Manually verify at least:

## Android

```text
Chrome
installed PWA or browser context as supported
```

Verify:

- subscribe,
- notification while app closed/backgrounded,
- click opens/focuses Alerts,
- escalation replaces/updates sensibly,
- unsubscribe.

## iPhone

```text
Safari
Add to Home Screen
Home Screen PWA
```

Verify:

- permission after user action,
- subscription,
- notification received,
- notification tap opens/focuses app,
- no silent push handling,
- unsubscribe/disable.

Record OS/browser versions in TEST_REPORT.

---

# 4. Localization

Push content must use the subscription/user language when known.

At minimum test:

```text
English
Thai
```

Fallback safely to English if unsupported language metadata is missing.

---

# 5. Test notification

Use CLI-only test notification.

Example:

```bash
php cron/test_push.php --subscription=<safe-test-id>
```

The generated notification must clearly contain:

```text
TEST
```

and must not be stored as a real flood incident.
