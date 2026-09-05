<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;
use PingFloodWatch\Logger;
use PingFloodWatch\Repositories\NotificationOutboxRepository;
use PingFloodWatch\Repositories\PushSubscriptionRepository;

final class NotificationDispatcher
{
    private NotificationOutboxRepository $outbox;
    private PushSubscriptionRepository $subscriptions;
    private NotificationPayloadFactory $payloads;

    public function __construct(private readonly PDO $db, private readonly PushSenderInterface $sender)
    {
        $this->outbox = new NotificationOutboxRepository($db);
        $this->subscriptions = new PushSubscriptionRepository($db);
        $this->payloads = new NotificationPayloadFactory();
    }

    /** @return array{processed:int,delivered:int,failed:int,superseded:int,expired:int} */
    public function dispatch(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $result = ['processed' => 0, 'delivered' => 0, 'failed' => 0, 'superseded' => 0, 'expired' => 0];
        $this->outbox->recoverAbandoned((int) Config::get('push.claim_timeout_seconds', 300), $now);
        $limit = max(1, (int) Config::get('push.batch_size', 20));

        for ($index = 0; $index < $limit; $index++) {
            $token = bin2hex(random_bytes(16));
            $item = $this->outbox->claimNext($token, $now);
            if ($item === null) {
                break;
            }
            $result['processed']++;
            if ($this->outbox->isSuperseded($item)) {
                $this->outbox->terminal((int) $item['id'], 'superseded', $now->format('Y-m-d H:i:s'));
                $result['superseded']++;
                continue;
            }
            if ($this->outbox->isExpired($item, $now)) {
                $this->outbox->terminal((int) $item['id'], 'expired', $now->format('Y-m-d H:i:s'));
                $result['expired']++;
                continue;
            }

            $this->outbox->createDeliveries($item, $now->format('Y-m-d H:i:s'));
            foreach ($this->outbox->dueDeliveries((int) $item['id'], $now->format('Y-m-d H:i:s')) as $delivery) {
                if ($this->outbox->isSuperseded($item)) {
                    $this->outbox->terminal((int) $item['id'], 'superseded', $now->format('Y-m-d H:i:s'));
                    $result['superseded']++;
                    continue 2;
                }
                if ($this->outbox->isExpired($item, $now)) {
                    $this->outbox->terminal((int) $item['id'], 'expired', $now->format('Y-m-d H:i:s'));
                    $result['expired']++;
                    continue 2;
                }
                $payload = json_encode(
                    $this->payloads->create($item, (string) ($delivery['language'] ?? 'en')),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                $ttl = $this->remainingTtl($item, $now);
                $sendResult = $this->sender->send($delivery, $payload, [
                    'TTL' => $ttl,
                    'urgency' => in_array((string) $item['severity'], ['warning', 'critical'], true) ? 'high' : 'normal',
                    'topic' => 'pfw-a-' . base_convert((string) $item['alert_id'], 10, 36),
                ]);
                $attempt = (int) $delivery['attempt_count'] + 1;
                if ($sendResult->success) {
                    $this->outbox->deliverySuccess((int) $delivery['id'], $now->format('Y-m-d H:i:s'), $sendResult->httpStatus);
                    $this->subscriptions->markSuccess((int) $delivery['subscription_id'], $now->format('Y-m-d H:i:s'));
                    $result['delivered']++;
                } elseif ($sendResult->permanentFailure) {
                    $this->outbox->deliveryPermanentFailure((int) $delivery['id'], $sendResult->errorCode, $sendResult->httpStatus, $now->format('Y-m-d H:i:s'));
                    $this->subscriptions->disableById((int) $delivery['subscription_id'], $now->format('Y-m-d H:i:s'));
                    $result['failed']++;
                } else {
                    $this->outbox->deliveryTemporaryFailure((int) $delivery['id'], $attempt, $sendResult->errorCode, $sendResult->httpStatus, $now);
                    $this->subscriptions->markFailure((int) $delivery['subscription_id'], $now->format('Y-m-d H:i:s'));
                    $result['failed']++;
                }
                Logger::info('push_delivery_result', [
                    'alert_event_id' => (int) $item['alert_event_id'],
                    'outbox_id' => (int) $item['id'],
                    'subscription_id' => (int) $delivery['subscription_id'],
                    'endpoint_hash' => substr((string) $delivery['endpoint_hash'], 0, 12),
                    'success' => $sendResult->success,
                    'http_status' => $sendResult->httpStatus,
                    'error_code' => $sendResult->errorCode,
                    'attempt_count' => $attempt,
                ]);
            }
            $this->outbox->finishOrReschedule((int) $item['id'], $now->format('Y-m-d H:i:s'));
        }
        return $result;
    }

    /** @param array<string,mixed> $item */
    private function remainingTtl(array $item, DateTimeImmutable $now): int
    {
        $ttl = (string) $item['event_type'] === 'cleared'
            ? (int) Config::get('push.cleared_ttl_seconds', 7200)
            : (int) Config::get('push.active_ttl_seconds', 1800);
        $created = new DateTimeImmutable((string) $item['created_at'] . ' UTC');
        return max(1, $created->getTimestamp() + $ttl - $now->getTimestamp());
    }
}
