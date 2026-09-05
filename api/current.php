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
    $dashboard = (new DashboardService(Database::connection()))->home();
    $data = [
        'stations' => array_map(static fn (array $station): array => ApiPresenter::station($station, $language), $dashboard['stations']),
        'risks' => [
            'river' => ApiPresenter::risk($dashboard['risks']['river'], $language),
            'weather' => ApiPresenter::risk($dashboard['risks']['weather'], $language),
            'combined' => ApiPresenter::risk($dashboard['risks']['combined'], $language),
        ],
        'weather' => array_map(static fn (array $state): array => ApiPresenter::weatherState($state, $language), $dashboard['weather_states']),
    ];
    Api::success($data, ['generated_at' => $dashboard['generated_at'], 'timezone' => Config::get('app.timezone'), 'language' => $language]);
});
