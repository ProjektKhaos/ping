<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\Config;
use PingFloodWatch\Database;
use PingFloodWatch\Services\DashboardService;

require __DIR__ . '/_bootstrap.php';
api_run(function (): void {
    $language = Api::language(isset($_GET['lang']) ? (string) $_GET['lang'] : null);
    $code = isset($_GET['station']) ? (string) $_GET['station'] : 'P.1';
    if (!in_array($code, Config::get('stations.codes', []), true)) {
        Api::error('INVALID_STATION', t('error.api.invalid_station', [], $language));
    }
    $hours = Api::period(isset($_GET['period']) ? (string) $_GET['period'] : '24h', ['24h', '48h', '72h'], $language);
    $station = (new DashboardService(Database::connection()))->station($code, $hours);
    if ($station === null) {
        Api::error('STATION_NOT_FOUND', t('error.api.station_not_found', [], $language), 404);
    }
    $points = array_map(static fn (array $point): array => [
        'measured_at' => $point['measured_at'], 'source_measured_at' => $point['source_measured_at'],
        'water_level_gauge_m' => is_numeric($point['water_level_gauge_m']) ? (float) $point['water_level_gauge_m'] : null,
        'water_level_msl_m' => is_numeric($point['water_level_msl_m']) ? (float) $point['water_level_msl_m'] : null,
        'discharge_m3s' => is_numeric($point['discharge_m3s']) ? (float) $point['discharge_m3s'] : null,
        'capacity_percent' => is_numeric($point['capacity_percent']) ? (float) $point['capacity_percent'] : null,
        'source_situation' => $point['source_situation'], 'revision_count' => (int) $point['revision_count'],
    ], $station['history']);
    Api::success(['station' => $code, 'period' => $hours . 'h', 'points' => $points], [
        'generated_at' => gmdate('Y-m-d H:i:s'), 'timezone' => 'UTC', 'gauge_datum' => 'station gauge zero',
    ]);
});
