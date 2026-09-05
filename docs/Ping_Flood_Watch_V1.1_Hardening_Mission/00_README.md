# Ping Flood Watch V1.1 — Reliability Hardening Addendum

**Mission type:** Addendum / patch mission  
**Target:** Codex  
**Applies to:** Existing V1.1 implementation plan for `ping.aberg.online`  
**Do not restart or rewrite the V1.1 work.**

## Purpose

This package contains additional requirements that strengthen the already-approved V1.1 proposal.

The addendum focuses on operational reliability, especially around Web Push, collector health, upstream early-warning semantics and rollback safety.

## Important

This mission is **not** a replacement for the existing V1.1 mission.

Codex should:

1. keep the existing V1.1 plan,
2. merge these requirements into that plan,
3. avoid undoing already-correct design decisions,
4. implement these items before V1.1 is considered production complete.

## Main additions

- explicit notification outbox,
- superseded/expired push handling,
- Web Push TTL, urgency and stable topics/tags,
- iPhone/iPad Home Screen PWA handling,
- explicit Android + iPhone manual push QA,
- upstream `rising` information separate from configured warning thresholds,
- hung collector detection,
- stronger push endpoint hardening,
- migration files instead of schema-only changes,
- staging push isolation,
- rollback-safe service-worker/cache strategy,
- release ZIP outside the web tree.

## Files

- `01_MASTER_ADDENDUM.md`
- `02_PUSH_OUTBOX_AND_DELIVERY.md`
- `03_IOS_ANDROID_PUSH_QA.md`
- `04_UPSTREAM_SIGNAL_SEMANTICS.md`
- `05_HEALTH_AND_HUNG_COLLECTORS.md`
- `06_SECURITY_MIGRATION_ROLLBACK.md`
- `07_ACCEPTANCE_TESTS.md`
