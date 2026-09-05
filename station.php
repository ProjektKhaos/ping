<?php

declare(strict_types=1);

use PingFloodWatch\Database;
use PingFloodWatch\Config;
use PingFloodWatch\Services\DashboardService;
use PingFloodWatch\View;

require __DIR__ . '/app/bootstrap.php';

$code = (string) ($_GET['code'] ?? 'P.1');
if (!in_array($code, Config::get('stations.codes', []), true)) {
    http_response_code(400);
    $code = 'P.1';
    $invalidStation = true;
}
try {
    $station = (new DashboardService(Database::connection()))->station($code, 72);
    $loadError = $station === null ? new RuntimeException('Station unavailable') : null;
} catch (Throwable $error) {
    $station = null;
    $loadError = $error;
}

View::render('station', [
    'pageTitle' => $code . ' · ' . t('app.name'), 'activeNav' => 'stations', 'station' => $station,
    'stationCode' => $code, 'loadError' => $loadError, 'invalidStation' => $invalidStation ?? false,
    'pageScripts' => ['assets/vendor/chart.umd.min.js', 'assets/js/station.js'],
]);
