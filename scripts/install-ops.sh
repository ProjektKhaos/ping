#!/usr/bin/env bash
set -euo pipefail
if [[ ${EUID} -ne 0 ]]; then echo "Run this script as root." >&2; exit 1; fi
project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
install -m 0644 "$project_dir/config/cron/ping-flood-watch" /etc/cron.d/ping-flood-watch
install -m 0644 "$project_dir/config/logrotate/ping-flood-watch" /etc/logrotate.d/ping-flood-watch
install -d -m 0750 -o root -g www-data "$project_dir/storage"
install -d -m 0770 -o www-data -g www-data "$project_dir/storage/logs" "$project_dir/storage/cache" "$project_dir/storage/locks"
find "$project_dir" -path "$project_dir/storage" -prune -o -type d -exec chmod 0755 {} +
find "$project_dir" -path "$project_dir/storage" -prune -o -type f -exec chmod 0644 {} +
chmod 0755 "$project_dir/cron/collect-water.php" "$project_dir/cron/collect-weather.php" "$project_dir/cron/retention.php" "$project_dir/scripts/backup.sh"
echo "Cron, logrotate, and permissions installed."
