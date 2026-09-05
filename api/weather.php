<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\ApiPresenter;
use PingFloodWatch\Database;
use PingFloodWatch\Services\DashboardService;
use PingFloodWatch\Services\WeatherRiskEngine;
use PingFloodWatch\Services\HealthService;

require __DIR__ . '/_bootstrap.php';
api_run(function (): void {
    $language = Api::language(isset($_GET['lang']) ? (string) $_GET['lang'] : null);
    $db = Database::connection();
    $service = new DashboardService($db);
    $states = $service->weatherStates();
    $risk = (new WeatherRiskEngine())->evaluate($states, (new HealthService($db))->provider('weather'));
    Api::success([
        'zones' => array_map(static fn (array $state): array => ApiPresenter::weatherState($state, $language), $states),
        'risk' => ApiPresenter::risk($risk, $language),
    ], ['generated_at' => gmdate('Y-m-d H:i:s'), 'attribution' => 'Weather data by Open-Meteo.com']);
});
