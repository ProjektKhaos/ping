<?php

declare(strict_types=1);

use PingFloodWatch\Database;
use PingFloodWatch\Services\DashboardService;
use PingFloodWatch\View;

require __DIR__ . '/app/bootstrap.php';

try {
    $alerts = (new DashboardService(Database::connection()))->alerts();
    $loadError = null;
} catch (Throwable $error) {
    $alerts = [];
    $loadError = $error;
}
View::render('alerts', [
    'pageTitle' => t('alerts.title') . ' · ' . t('app.name'), 'activeNav' => 'alerts',
    'alerts' => $alerts, 'loadError' => $loadError, 'pageScripts' => ['assets/js/push.js', 'assets/js/alerts.js'],
]);
