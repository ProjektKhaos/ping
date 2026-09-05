#!/usr/bin/env php
<?php

declare(strict_types=1);

use PingFloodWatch\Config;
use PingFloodWatch\Database;
use PingFloodWatch\Logger;
use PingFloodWatch\Providers\MockWeatherProvider;
use PingFloodWatch\Providers\OpenMeteoProvider;
use PingFloodWatch\Providers\ProviderException;
use PingFloodWatch\Repositories\ForecastRepository;
use PingFloodWatch\Repositories\ProviderHealthRepository;
use PingFloodWatch\Services\CollectorLock;
use PingFloodWatch\Services\CollectorRun;
use PingFloodWatch\Services\RiskCoordinator;

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$lock = new CollectorLock();
if (!$lock->acquire('weather-collector')) {
    fwrite(STDERR, "Weather collector is already running.\n");
    exit(75);
}

$db = Database::connection();
$run = new CollectorRun($db, 'weather');
$health = new ProviderHealthRepository($db);
$forecasts = new ForecastRepository($db);
$providerName = (string) Config::get('providers.weather', 'openmeteo');
$provider = $providerName === 'mock'
    ? new MockWeatherProvider()
    : new OpenMeteoProvider();

try {
    $forecast = $provider->fetchForecast($forecasts->zones());
    $result = $forecasts->storeRun($provider->getName(), $forecast);
    $health->success($provider->getName(), 'weather');
    (new RiskCoordinator($db))->calculate();
    $run->finish($result['status'] === 'inserted' ? count($forecast['points']) : 0, 0, 'Forecast ' . $result['status'] . '.');
    Logger::info('weather_collector_success', ['status' => $result['status'], 'points' => count($forecast['points'])]);
    fwrite(STDOUT, sprintf("Weather collection complete: %s, %d points.\n", $result['status'], count($forecast['points'])));
} catch (Throwable $error) {
    $code = $error instanceof ProviderException ? $error->providerCode : 'WEATHER_COLLECTOR_FAILED';
    $health->failure($provider->getName(), 'weather', $code, $error->getMessage());
    try {
        (new RiskCoordinator($db))->calculate();
    } catch (Throwable $secondary) {
        Logger::error('risk_recalculation_failed', ['message' => $secondary->getMessage()]);
    }
    $run->fail($code, $error->getMessage());
    Logger::error('weather_collector_failed', ['code' => $code, 'message' => $error->getMessage()]);
    fwrite(STDERR, $code . ': ' . $error->getMessage() . "\n");
    exit(1);
}
