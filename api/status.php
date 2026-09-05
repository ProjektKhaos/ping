<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\Database;
use PingFloodWatch\Services\HealthService;

require __DIR__ . '/_bootstrap.php';

api_run(function (): void {
    $health = (new HealthService(Database::connection()))->evaluate();
    $generatedAt = $health['generated_at'];
    unset($health['generated_at']);
    Api::success($health, ['generated_at' => $generatedAt]);
});
