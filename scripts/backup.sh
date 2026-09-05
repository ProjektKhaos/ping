#!/usr/bin/env bash
set -euo pipefail
if [[ ${EUID} -ne 0 ]]; then echo "Run this script as root." >&2; exit 1; fi
backup_dir="/var/backups/ping-flood-watch"
install -d -m 0700 -o root -g root "$backup_dir"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
temporary_file="$(mktemp "$backup_dir/.ping-flood-watch-${timestamp}.XXXXXX.sql.gz")"
trap 'rm -f "$temporary_file"' EXIT
mariadb-dump --single-transaction --quick --routines --events ping_flood_watch | gzip -9 > "$temporary_file"
final_file="$backup_dir/ping-flood-watch-${timestamp}.sql.gz"
mv "$temporary_file" "$final_file"
chmod 0600 "$final_file"
trap - EXIT
find "$backup_dir" -maxdepth 1 -type f -name 'ping-flood-watch-*.sql.gz' -mtime +30 -delete
echo "Backup written to $final_file"
