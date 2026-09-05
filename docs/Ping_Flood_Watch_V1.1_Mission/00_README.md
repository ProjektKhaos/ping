# Ping Flood Watch — V1.1 Improvement Mission Package

**Mission version:** 1.00  
**Target:** Codex  
**Project:** Ping Flood Watch  
**Source reviewed:** `ping.zip`  
**Live site:** https://ping.aberg.online/  
**Scope:** Improve the existing application. Do **not** rebuild working V1 functionality.

## Purpose

This package contains a focused V1.1 mission based on review of the current Ping Flood Watch codebase.

The current architecture is good and should be preserved. The mission focuses on the most useful gaps and polish items:

1. real Web Push notifications,
2. upstream station auto-refresh on Home,
3. Station Detail and Alerts auto-refresh,
4. upstream rapid-rise early-warning logic,
5. configuration-driven freshness instead of hardcoded SQL values,
6. collector/cron watchdog in health status,
7. remove hardcoded provider names from risk/dashboard logic,
8. preserve query parameters when changing language,
9. separate current-data refresh from chart-history refresh,
10. distinguish offline/network failure from backend/API failure,
11. increase the smallest UI fonts,
12. make bottom-navigation icons more colorful, polished and easier to scan,
13. improve Open-Meteo response/unit validation,
14. refresh immediately when the app returns from background.

## Important rule

Do not redesign or replace the existing application architecture.

Keep:

- PHP 8.2+,
- MariaDB/MySQL,
- PDO,
- current repository/service/provider structure,
- Chart.js,
- PWA/service worker,
- English + Thai localization,
- portable `BASE_URL` behavior,
- current River Risk / Weather Risk / Combined Advisory separation.

## Mission files

- `01_MASTER_MISSION.md` — main implementation mission
- `02_PUSH_NOTIFICATIONS.md` — Web Push implementation
- `03_REFRESH_RESILIENCE.md` — auto-refresh, offline/error handling and visibility refresh
- `04_UPSTREAM_EARLY_WARNING.md` — P.67/P.103 rapid-rise signal
- `05_UI_TYPOGRAPHY_NAV.md` — larger small fonts and improved colorful navigation
- `06_HEALTH_CONFIG_PROVIDER.md` — freshness, health watchdog and provider abstraction
- `07_MISC_FIXES.md` — language URL, chart/current decoupling and provider validation
- `08_TEST_ACCEPTANCE.md` — required tests and Definition of Done

## Recommended execution

Give Codex `01_MASTER_MISSION.md` first.

The detailed files exist so Codex can inspect exact requirements when implementing each section.
