<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\ApiPresenter;
use PingFloodWatch\Config;
use PingFloodWatch\Database;
use PingFloodWatch\Services\DashboardService;

require __DIR__ . '/_bootstrap.php';

api_run(function (): void {
    $language = Api::language(isset($_GET['lang']) ? (string) $_GET['lang'] : null);
    $alerts = (new DashboardService(Database::connection()))->alerts();
    Api::success([
        'alerts' => array_map(static fn (array $alert): array => ApiPresenter::alert($alert, $language), $alerts),
    ], ['generated_at' => gmdate('Y-m-d H:i:s'), 'timezone' => Config::get('app.timezone'), 'language' => $language]);
});
