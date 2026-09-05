<?php

declare(strict_types=1);

namespace PingFloodWatch;

final class Config
{
    /** @var array<string,mixed> */
    private static array $values = [];

    public static function load(string $root): void
    {
        $defaults = [
            'app' => [
                'name' => 'Ping Flood Watch',
                'base_url' => '/',
                'public_origin' => 'https://ping.aberg.online',
                'asset_version' => '1.2.4',
                'timezone' => 'Asia/Bangkok',
                'debug' => false,
                'default_language' => 'en',
                'supported_languages' => ['en', 'th'],
            ],
            'db' => [
                'dsn' => '',
                'username' => '',
                'password' => '',
            ],
            'providers' => [
                'water' => 'thaiwater',
                'weather' => 'openmeteo',
                'thaiwater' => [
                    'current_url' => 'https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_load',
                    'history_url' => 'https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_graph',
                    'connect_timeout' => 5,
                    'timeout' => 20,
                    'max_bytes' => 5_000_000,
                ],
                'openmeteo' => [
                    'url' => 'https://api.open-meteo.com/v1/forecast',
                    'connect_timeout' => 5,
                    'timeout' => 15,
                    'max_bytes' => 2_000_000,
                ],
                'cmflood' => [
                    'url' => 'https://uhsmuwbmimfkffkobunm.supabase.co/rest/v1',
                    'anon_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InVoc211d2JtaW1ma2Zma29idW5tIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTg3MjY1NzEsImV4cCI6MjA3NDMwMjU3MX0.fSdF9tOLPofcSkwCXhyRxFITDfleQPpNVp6_Bf6R2qw',
                    'connect_timeout' => 5,
                    'timeout' => 15,
                    'max_bytes' => 2_000_000,
                ],
            ],
            'stations' => [
                'default' => 'P.1',
                'codes' => ['P.67', 'P.103', 'CMI01', 'CMI02', 'P.1', 'FBP.2', 'CMI03', 'FBP.3', 'P.104'],
            ],
            'maps' => [
                'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                'attribution_url' => 'https://www.openstreetmap.org/copyright',
                'max_zoom' => 18,
            ],
            'freshness' => [
                'water_live_minutes' => 75,
                'water_delayed_minutes' => 120,
                'weather_current_minutes' => 90,
                'weather_aging_minutes' => 180,
            ],
            'trends' => [
                'tolerance_minutes' => 10,
            ],
            'alerts' => [
                'clear_delay_minutes' => 20,
                'rise_watch_cm_per_hour' => null,
                'rise_warning_cm_per_hour' => null,
                'rise_critical_cm_per_hour' => null,
                'upstream_rise_watch_cm_per_hour' => null,
                'upstream_rise_warning_cm_per_hour' => null,
                'upstream_min_stations_for_multi_rise' => 2,
            ],
            'health' => [
                'water_collector_max_age_minutes' => 15,
                'weather_collector_max_age_minutes' => 45,
                'water_collector_max_runtime_minutes' => 3,
                'weather_collector_max_runtime_minutes' => 5,
            ],
            'push' => [
                'enabled' => false,
                'subject' => 'https://ping.aberg.online/',
                'public_key' => '',
                'private_key' => '',
                'claim_timeout_seconds' => 300,
                'batch_size' => 20,
                'max_attempts' => 5,
                'retry_delays_seconds' => [60, 120, 300, 600],
                'active_ttl_seconds' => 1800,
                'cleared_ttl_seconds' => 7200,
            ],
            'security' => [
                'rate_limit_key' => '',
                'push_subscribe_limit' => 10,
                'push_unsubscribe_limit' => 20,
                'push_rate_window_seconds' => 600,
            ],
            'visitor_counter' => [
                'enabled' => true,
                'log_file' => $root . '/docs/VISITOR_LOG.md',
                'hmac_key' => '',
            ],
            'weather_risk' => [
                'hourly' => ['moderate' => 5.1, 'high' => 25.1, 'very_high' => 50.1],
                '24h' => ['moderate' => 10.1, 'high' => 35.1, 'very_high' => 90.1],
            ],
            'storage' => [
                'logs' => $root . '/storage/logs',
                'locks' => $root . '/storage/locks',
                'cache' => $root . '/storage/cache',
            ],
        ];

        $configFile = getenv('PFW_CONFIG_FILE') ?: '/etc/ping-flood-watch/config.php';
        if (!is_file($configFile)) {
            $configFile = $root . '/app/config.local.php';
        }

        $local = is_file($configFile) ? require $configFile : [];
        if (!is_array($local)) {
            throw new \RuntimeException('The local configuration file must return an array.');
        }

        self::$values = self::merge($defaults, $local);
        date_default_timezone_set((string) self::get('app.timezone', 'Asia/Bangkok'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @param array<string,mixed> $override */
    public static function overrideForTests(array $override): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new \LogicException('Runtime configuration overrides are CLI-only.');
        }
        self::$values = self::merge(self::$values, $override);
    }

    /** @param array<string,mixed> $base @param array<string,mixed> $override */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
