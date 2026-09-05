#!/usr/bin/env php
<?php

declare(strict_types=1);

use PingFloodWatch\Config;
use PingFloodWatch\Database;
use PingFloodWatch\Logger;
use PingFloodWatch\Repositories\PushSubscriptionRepository;
use PingFloodWatch\Services\WebPushSender;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repository = new PushSubscriptionRepository(Database::connection());
if (in_array('--list', $argv, true)) {
    foreach ($repository->recentSafe() as $row) {
        fwrite(STDOUT, sprintf(
            "%d  %s  %s  %s  %s\n",
            $row['id'], substr((string) $row['endpoint_hash'], 0, 12), $row['language'],
            $row['client_class'] ?: 'unknown', $row['disabled_at'] ? 'disabled' : 'active'
        ));
    }
    exit(0);
}

$subscriptionId = null;
foreach ($argv as $argument) {
    if (preg_match('/^--subscription-id=(\d+)$/', $argument, $matches)) {
        $subscriptionId = (int) $matches[1];
    }
}
if ($subscriptionId === null || !in_array('--confirm-test', $argv, true)) {
    fwrite(STDERR, "Usage: php cron/test_push.php --subscription-id=ID --confirm-test [--disable-after]\n");
    exit(64);
}
if (!(bool) Config::get('push.enabled', false)) {
    fwrite(STDERR, "Push is disabled in this environment.\n");
    exit(78);
}
$subscription = $repository->findActive($subscriptionId);
if ($subscription === null) {
    fwrite(STDERR, "The selected test subscription is not active.\n");
    exit(66);
}

$language = in_array((string) $subscription['language'], ['en', 'th'], true) ? (string) $subscription['language'] : 'en';
$payload = json_encode([
    'title' => 'TEST — Ping Flood Watch',
    'body' => $language === 'th' ? 'ทดสอบการแจ้งเตือนเท่านั้น ไม่มีเหตุการณ์น้ำท่วมจริง' : 'Test notification only. No real flood incident was created.',
    'severity' => 'normal', 'alert_id' => 0, 'event_type' => 'test',
    'url' => url('alerts.php?lang=' . $language), 'tag' => 'pfw-test-' . $subscriptionId, 'timestamp' => gmdate('c'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$result = (new WebPushSender())->send($subscription, $payload, ['TTL' => 300, 'urgency' => 'normal', 'topic' => 'pfw-test-' . base_convert((string) $subscriptionId, 10, 36)]);
Logger::info('test_push_result', [
    'subscription_id' => $subscriptionId, 'endpoint_hash' => substr((string) $subscription['endpoint_hash'], 0, 12),
    'success' => $result->success, 'http_status' => $result->httpStatus, 'error_code' => $result->errorCode,
]);
if (!$result->success) {
    fwrite(STDERR, "TEST push failed: {$result->errorCode}\n");
    exit(1);
}
if (in_array('--disable-after', $argv, true)) {
    $repository->disableById($subscriptionId);
}
fwrite(STDOUT, "TEST push sent to subscription {$subscriptionId}.\n");
