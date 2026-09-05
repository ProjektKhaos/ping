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
    $stations = (new DashboardService(Database::connection()))->stations();
    Api::success(array_map(static fn (array $station): array => ApiPresenter::station($station, $language), $stations), [
        'generated_at' => gmdate('Y-m-d H:i:s'), 'timezone' => Config::get('app.timezone'), 'language' => $language,
    ]);
});
