<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\Logger;

require dirname(__DIR__) . '/app/bootstrap.php';

/** @param callable():void $callback */
function api_run(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        Logger::error('api_request_failed', ['path' => $_SERVER['REQUEST_URI'] ?? '', 'message' => $error->getMessage()]);
        Api::error('INTERNAL_ERROR', t('error.api.internal'), 500);
    }
}
