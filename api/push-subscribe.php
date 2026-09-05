<?php

declare(strict_types=1);

use PingFloodWatch\Api;
use PingFloodWatch\Config;
use PingFloodWatch\Database;
use PingFloodWatch\Repositories\PushSubscriptionRepository;
use PingFloodWatch\Services\PushSubscriptionValidator;
use PingFloodWatch\Services\RateLimiter;

require __DIR__ . '/_bootstrap.php';

api_run(function (): void {
    Api::requirePostJsonSameOrigin();
    if (!(bool) Config::get('push.enabled', false)) {
        Api::error('PUSH_DISABLED', t('error.api.push_disabled'), 503);
    }
    $db = Database::connection();
    $limit = (new RateLimiter($db))->consume(
        'push-subscribe', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        (int) Config::get('security.push_subscribe_limit', 10),
        (int) Config::get('security.push_rate_window_seconds', 600)
    );
    if (!$limit['allowed']) {
        header('Retry-After: ' . $limit['retry_after']);
        Api::error('RATE_LIMITED', t('error.api.rate_limited'), 429);
    }
    try {
        $subscription = (new PushSubscriptionValidator())->subscribe(Api::jsonBody());
    } catch (InvalidArgumentException $error) {
        $code = $error->getMessage() === 'INVALID_LANGUAGE' ? 'INVALID_LANGUAGE' : 'INVALID_SUBSCRIPTION';
        Api::error($code, t('error.api.' . strtolower($code)));
    }
    (new PushSubscriptionRepository($db))->upsert(
        $subscription['endpoint'], $subscription['p256dh'], $subscription['auth'],
        $subscription['content_encoding'], $subscription['language'],
        PushSubscriptionValidator::clientClass((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))
    );
    Api::success(['subscribed' => true]);
});
