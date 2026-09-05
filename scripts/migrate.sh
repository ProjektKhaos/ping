#!/usr/bin/env bash
set -euo pipefail
if [[ ${EUID} -ne 0 ]]; then echo "Run this script as root." >&2; exit 1; fi
project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
database_name="${PFW_MIGRATION_DATABASE:-ping_flood_watch}"
if [[ ! "$database_name" =~ ^[A-Za-z0-9_]+$ ]]; then echo "Invalid database name." >&2; exit 1; fi

if ! mariadb -NBe "SELECT 1 FROM information_schema.tables WHERE table_schema='${database_name}' AND table_name='schema_migrations'" | grep -q 1; then
  mariadb "$database_name" < "$project_dir/sql/schema.sql"
else
  for migration in "$project_dir"/sql/migrations/*.sql; do
    version="$(basename "$migration" .sql)"
    if [[ "$(mariadb -NBe "SELECT COUNT(*) FROM ${database_name}.schema_migrations WHERE version='${version}'")" == "0" ]]; then
      mariadb "$database_name" < "$migration"
      mariadb -e "INSERT INTO ${database_name}.schema_migrations (version) VALUES ('${version}')"
    fi
  done
fi
mariadb "$database_name" < "$project_dir/sql/seed.sql"
echo "Ping Flood Watch migrations and seed applied to ${database_name}."
