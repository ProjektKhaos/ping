<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\Database;
use PingFloodWatch\Repositories\ForecastRepository;
use PingFloodWatch\Services\DashboardService;

require __DIR__ . '/_bootstrap.php';
api_run(function (): void {
    $language = Api::language(isset($_GET['lang']) ? (string) $_GET['lang'] : null);
    $code = isset($_GET['zone']) ? (string) $_GET['zone'] : 'P.67';
    if (!in_array($code, ['P.67', 'P.103', 'P.1'], true)) {
        Api::error('INVALID_ZONE', t('error.api.invalid_zone', [], $language));
    }
    $hours = Api::period(isset($_GET['period']) ? (string) $_GET['period'] : '24h', ['24h', '48h'], $language);
    $db = Database::connection();
    $zone = (new ForecastRepository($db))->zoneByCode($code);
    if ($zone === null) {
        Api::error('ZONE_NOT_FOUND', t('error.api.zone_not_found', [], $language), 404);
    }
    $points = array_map(static fn (array $point): array => [
        'valid_from' => $point['valid_from'], 'valid_to' => $point['valid_to'],
        'rainfall_mm' => is_numeric($point['rainfall_mm']) ? (float) $point['rainfall_mm'] : null,
        'rainfall_probability_pct' => is_numeric($point['rainfall_probability_pct']) ? (float) $point['rainfall_probability_pct'] : null,
        'weather_code' => $point['weather_code'],
    ], (new DashboardService($db))->forecast($code, $hours));
    Api::success(['zone' => $code, 'period' => $hours . 'h', 'points' => $points], [
        'generated_at' => gmdate('Y-m-d H:i:s'), 'timezone' => 'UTC', 'attribution' => 'Weather data by Open-Meteo.com',
    ]);
});
