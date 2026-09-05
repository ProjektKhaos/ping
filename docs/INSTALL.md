# Installation and operations

Ping Flood Watch V1.1 targets PHP 8.3, MariaDB 10.11+, Apache 2.4, Composer 2, Node 22 and cron. PHP extensions include cURL, JSON, PDO MySQL, OpenSSL and mbstring. Production lives at `https://ping.aberg.online` with DocumentRoot `/var/www/abergonline/ping`. Composer installs `minishlink/web-push:^11` and the regular Guzzle PSR-18 client; dispatch is sequential, so no async adapter is installed.

## Fresh installation

Run from the project directory:

```bash
composer install --no-dev --classmap-authoritative
npm ci
./scripts/build-assets.sh
sudo ./scripts/provision-databases.sh
sudo ./scripts/install-ops.sh
sudo php scripts/configure-vapid.php
sudo -u www-data php cron/collect-water.php --backfill=72h
sudo -u www-data php cron/collect-weather.php
```

`provision-databases.sh` creates `ping_flood_watch` and a dedicated `pfw_runtime@localhost`. Runtime has only `SELECT`, `INSERT`, `UPDATE`, and `DELETE`; schema operations run as an administrator through `scripts/migrate.sh`. A random password is generated locally and written to `/etc/ping-flood-watch/config.php` (`root:www-data`, mode `0640`). No secret is stored below DocumentRoot.

`configure-vapid.php` creates one VAPID key pair and one HMAC rate-limit secret only when missing, writes them atomically to the same external configuration, sets subject `https://ping.aberg.online/`, and never prints secret values. Re-running it preserves the existing key pair.

For production Apache/TLS, install `config/apache/ping.aberg.online-http.conf`, issue the certificate with ACME webroot, then install `config/apache/ping.aberg.online.conf` and reload Apache. The live certificate is dedicated to `ping.aberg.online`; `certbot.timer` renews it.

## Configuration and portability

Copy `app/config.example.php` only for local development. Production always uses `/etc/ping-flood-watch/config.php`, or a path selected with `PFW_CONFIG_FILE`. Set `app.base_url` to `/` for the subdomain or `/some-subdirectory/` when mounted below another site. Server links, assets, browser API calls, manifest URLs and service-worker scope all resolve through this base.

## V1.0 to V1.1 deployment

Take code and database backups first. Then deploy in this order:

```bash
sudo PFW_MIGRATION_DATABASE=ping_flood_watch bash scripts/migrate.sh
# deploy V1.1 application and vendor, preserving storage
sudo php scripts/configure-vapid.php
sudo install -m 0644 config/cron/ping-flood-watch /etc/cron.d/ping-flood-watch
sudo systemctl reload cron
sudo -u www-data php cron/collect-water.php
sudo -u www-data php cron/collect-weather.php
sudo -u www-data php cron/dispatch_push.php
curl -fsS https://ping.aberg.online/api/health.php
curl -fsS https://ping.aberg.online/api/status.php
```

Migration `002_v1_1_push_and_health` is idempotently tracked and adds outbox, subscription, delivery and DB rate-limit tables. The final `schema.sql` contains the same structure for fresh installs. Notification tables are retained during rollback.

Staging uses the isolated `ping_flood_watch_test` database and `tests/staging.config.php`. Its `push.enabled` is always false, VAPID values are empty, and subscription/outbox/delivery tables are truncated before collectors or browser tests. Production subscriptions and credentials are never copied there.

## Scheduled work

- Water: every 5 minutes, protected by `flock`.
- Weather: every 15 minutes, protected by `flock`.
- Push outbox: every minute, protected by `flock` plus MariaDB row claims.
- Measurement/forecast retention: nightly, 12 months.
- Compressed MariaDB backup: nightly in `/var/backups/ping-flood-watch`, retained 30 days.
- Application logs: `/var/www/abergonline/ping/storage/logs`, rotated daily for 30 rotations.

Check operations with:

```bash
systemctl status apache2 mariadb cron certbot.timer
curl -fsS https://ping.aberg.online/api/health.php
sudo mariadb ping_flood_watch -e "SELECT * FROM provider_health"
sudo certbot renew --dry-run
sudo /var/www/abergonline/ping/scripts/backup.sh
sudo -u www-data php /var/www/abergonline/ping/cron/test_push.php --list
```

Apache must own only `storage/logs`, `storage/cache`, and `storage/locks`; source remains non-writable by `www-data`. The vhost denies application internals, documentation, tests, dependencies, configuration, SQL, storage, dotfiles and lock/log/Markdown files.

## Rollback

The tested rollback package is stored outside the web tree and carries service-worker cache ID `v1.1.0-rollback.1`, which is newer/different from both V1 and V1.1. To roll back:

1. Set `push.enabled=false` in the external config and remove/comment only the push-dispatch cron line.
2. Restore the V1 rollback artifact, preserving `storage` and `/etc/ping-flood-watch/config.php`.
3. Deploy its rollback worker; activation deletes every cache except `ping-flood-watch-shell-v1.1.0-rollback.1`.
4. Reload Apache/cron and refresh/reopen clients so the new worker controls them.
5. Verify `/`, `/api/current.php`, station/history pages and both collectors.
6. Leave `notification_outbox`, `push_subscriptions`, `push_deliveries` and `api_rate_limits` in place; do not drop them.

The release and rollback SHA-256 files must be verified with `sha256sum -c` before use.
