#!/usr/bin/env bash
set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Run this script as root." >&2
  exit 1
fi

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
config_dir="/etc/ping-flood-watch"
runtime_config="$config_dir/config.php"
test_env="$config_dir/test.env"
install -d -m 0750 -o root -g www-data "$config_dir"

if [[ ! -f "$runtime_config" ]]; then
  runtime_password="$(openssl rand -hex 24)"
  test_password="$(openssl rand -hex 24)"
  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS ping_flood_watch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ping_flood_watch_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pfw_runtime'@'localhost' IDENTIFIED BY '${runtime_password}';
ALTER USER 'pfw_runtime'@'localhost' IDENTIFIED BY '${runtime_password}';
GRANT SELECT, INSERT, UPDATE, DELETE ON ping_flood_watch.* TO 'pfw_runtime'@'localhost';
CREATE USER IF NOT EXISTS 'pfw_test'@'localhost' IDENTIFIED BY '${test_password}';
ALTER USER 'pfw_test'@'localhost' IDENTIFIED BY '${test_password}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES ON ping_flood_watch_test.* TO 'pfw_test'@'localhost';
FLUSH PRIVILEGES;
SQL
  umask 0027
  printf '%s\n' '<?php' '' 'declare(strict_types=1);' '' 'return [' \
    "    'db' => [" \
    "        'dsn' => 'mysql:host=localhost;dbname=ping_flood_watch;charset=utf8mb4'," \
    "        'username' => 'pfw_runtime'," \
    "        'password' => '${runtime_password}'," \
    '    ],' \
    "    'app' => ['base_url' => '/', 'debug' => false]," \
    "    'providers' => ['water' => 'thaiwater', 'weather' => 'openmeteo']," \
    '];' > "$runtime_config"
  chown root:www-data "$runtime_config"
  chmod 0640 "$runtime_config"
  printf '%s\n' \
    "PFW_TEST_DSN='mysql:host=localhost;dbname=ping_flood_watch_test;charset=utf8mb4'" \
    "PFW_TEST_DB_USER='pfw_test'" \
    "PFW_TEST_DB_PASSWORD='${test_password}'" > "$test_env"
  chown root:root "$test_env"
  chmod 0600 "$test_env"
else
  mariadb -e "CREATE DATABASE IF NOT EXISTS ping_flood_watch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS ping_flood_watch_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
fi

mariadb ping_flood_watch < "$project_dir/sql/schema.sql"
mariadb ping_flood_watch < "$project_dir/sql/seed.sql"
mariadb ping_flood_watch_test < "$project_dir/sql/schema.sql"
echo "Databases, runtime credentials, schema, and seed are ready."
