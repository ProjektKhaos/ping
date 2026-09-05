<?php

declare(strict_types=1);

use PingFloodWatch\Database;
use PingFloodWatch\Services\DashboardService;
use PingFloodWatch\View;

require __DIR__ . '/app/bootstrap.php';

try {
    $dashboard = (new DashboardService(Database::connection()))->home();
    $error = null;
} catch (Throwable $exception) {
    $dashboard = ['stations' => [], 'primary' => null, 'thresholds' => [], 'weather_states' => [],
        'risks' => [
            'river' => ['severity' => 'unknown', 'message_key' => 'risk.river.unknown', 'context' => []],
            'weather' => ['severity' => 'unknown', 'message_key' => 'risk.weather.unknown', 'context' => []],
            'combined' => ['severity' => 'unknown', 'message_key' => 'risk.combined.unknown', 'context' => []],
        ], 'generated_at' => gmdate('Y-m-d H:i:s')];
    $error = $exception;
}

View::render('home', [
    'pageTitle' => t('app.name'), 'activeNav' => 'home', 'dashboard' => $dashboard, 'loadError' => $error,
    'pageScripts' => ['assets/vendor/chart.umd.min.js', 'assets/js/home.js'],
]);
