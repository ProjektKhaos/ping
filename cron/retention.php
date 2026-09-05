#!/usr/bin/env php
<?php

declare(strict_types=1);

use PingFloodWatch\Database;
use PingFloodWatch\Logger;
use PingFloodWatch\Services\CollectorLock;
use PingFloodWatch\Services\RateLimiter;

require dirname(__DIR__) . '/app/bootstrap.php';
$lock = new CollectorLock();
if (!$lock->acquire('retention')) {
    exit(75);
}
try {
    $db = Database::connection();
    $forecasts = $db->exec("DELETE FROM forecast_runs WHERE received_at < UTC_TIMESTAMP() - INTERVAL 12 MONTH");
    $measurements = $db->exec("DELETE FROM measurements WHERE measured_at < UTC_TIMESTAMP() - INTERVAL 12 MONTH");
    $rateLimits = (new RateLimiter($db))->purgeExpired();
    Logger::info('retention_complete', [
        'forecast_runs_deleted' => $forecasts,
        'measurements_deleted' => $measurements,
        'rate_limits_deleted' => $rateLimits,
    ]);
    fwrite(STDOUT, "Retention complete.\n");
} catch (Throwable $error) {
    Logger::error('retention_failed', ['message' => $error->getMessage()]);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
