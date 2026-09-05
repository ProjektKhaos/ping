<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;

class NotificationOutboxRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string,mixed> $payload */
    public function enqueue(int $alertId, int $eventId, string $eventType, string $severity, array $payload, string $now): int
    {
        $maxSubscriptionId = (int) $this->db->query(
            'SELECT COALESCE(MAX(id), 0) FROM push_subscriptions WHERE disabled_at IS NULL'
        )->fetchColumn();
        $statement = $this->db->prepare(
            "INSERT INTO notification_outbox
                (alert_id, alert_event_id, event_type, severity, payload_json, subscription_max_id, status, available_at, created_at, updated_at)
             VALUES (:alert_id, :event_id, :event_type, :severity, :payload, :subscription_max_id, 'pending', :available_at, :created_at, :updated_at)"
        );
        $statement->execute([
            'alert_id' => $alertId,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'severity' => $severity,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subscription_max_id' => $maxSubscriptionId,
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function recoverAbandoned(int $timeoutSeconds, ?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $cutoff = $now->modify('-' . max(1, $timeoutSeconds) . ' seconds')->format('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            "UPDATE notification_outbox SET status = 'pending', claim_token = NULL, claimed_at = NULL,
                    available_at = LEAST(available_at, :available_at), updated_at = :updated_at
             WHERE status = 'processing' AND claimed_at < :cutoff"
        );
        $nowString = $now->format('Y-m-d H:i:s');
        $statement->execute(['available_at' => $nowString, 'updated_at' => $nowString, 'cutoff' => $cutoff]);
        return $statement->rowCount();
    }

    /** @return array<string,mixed>|null */
    public function claimNext(string $token, ?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowString = $now->format('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "SELECT * FROM notification_outbox
                 WHERE status = 'pending' AND available_at <= :now
                 ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED"
            );
            $statement->execute(['now' => $nowString]);
            $row = $statement->fetch();
            if (!is_array($row)) {
                $this->db->commit();
                return null;
            }
            $update = $this->db->prepare(
                "UPDATE notification_outbox SET status = 'processing', claim_token = :token, claimed_at = :claimed_at,
                        last_attempt_at = :last_attempt_at, attempt_count = attempt_count + 1, updated_at = :updated_at WHERE id = :id"
            );
            $update->execute(['token' => $token, 'claimed_at' => $nowString, 'last_attempt_at' => $nowString,
                'updated_at' => $nowString, 'id' => $row['id']]);
            $this->db->commit();
            $row['claim_token'] = $token;
            $row['claimed_at'] = $nowString;
            $row['attempt_count'] = (int) $row['attempt_count'] + 1;
            $row['payload'] = json_decode((string) $row['payload_json'], true) ?: [];
            return $row;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $outbox */
    public function isSuperseded(array $outbox): bool
    {
        if ((string) $outbox['event_type'] === 'cleared') {
            $statement = $this->db->prepare(
                "SELECT COUNT(*) FROM alert_events e
                 WHERE e.id > :event_id AND e.event_type = 'opened' AND e.alert_id <> :alert_id"
            );
        } else {
            $statement = $this->db->prepare(
                "SELECT COUNT(*) FROM alert_events e
                 WHERE e.alert_id = :alert_id AND e.id > :event_id
                   AND e.event_type IN ('escalated', 'deescalated', 'cleared')"
            );
        }
        $statement->execute(['event_id' => $outbox['alert_event_id'], 'alert_id' => $outbox['alert_id']]);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $outbox */
    public function isExpired(array $outbox, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $ttl = (string) $outbox['event_type'] === 'cleared'
            ? (int) Config::get('push.cleared_ttl_seconds', 7200)
            : (int) Config::get('push.active_ttl_seconds', 1800);
        $created = new DateTimeImmutable((string) $outbox['created_at'] . ' UTC');
        return $created->getTimestamp() + $ttl <= $now->getTimestamp();
    }

    /** @param array<string,mixed> $outbox */
    public function createDeliveries(array $outbox, ?string $now = null): void
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            "INSERT IGNORE INTO push_deliveries
                (outbox_id, alert_event_id, subscription_id, status, available_at, created_at, updated_at)
             SELECT :outbox_id, :event_id, s.id, 'pending', :available_at, :created_at, :updated_at
             FROM push_subscriptions s
             WHERE s.disabled_at IS NULL AND s.id <= :subscription_max_id"
        );
        $statement->execute([
            'outbox_id' => $outbox['id'],
            'event_id' => $outbox['alert_event_id'],
            'subscription_max_id' => $outbox['subscription_max_id'],
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function dueDeliveries(int $outboxId, ?string $now = null): array
    {
        $statement = $this->db->prepare(
            "SELECT d.*, s.endpoint, s.endpoint_hash, s.p256dh, s.auth, s.content_encoding, s.language
             FROM push_deliveries d JOIN push_subscriptions s ON s.id = d.subscription_id
             WHERE d.outbox_id = :outbox_id AND d.status IN ('pending', 'temporary_failure')
               AND d.available_at <= :now AND s.disabled_at IS NULL ORDER BY d.id"
        );
        $statement->execute(['outbox_id' => $outboxId, 'now' => $now ?? gmdate('Y-m-d H:i:s')]);
        return $statement->fetchAll();
    }

    public function deliverySuccess(int $deliveryId, ?string $now = null, ?int $httpStatus = null): void
    {
        $statement = $this->db->prepare(
            "UPDATE push_deliveries SET status = 'delivered', attempt_count = attempt_count + 1,
                    http_status = :http_status, error_code = NULL, attempted_at = :attempted_at,
                    delivered_at = :delivered_at, updated_at = :updated_at
             WHERE id = :id"
        );
        $now ??= gmdate('Y-m-d H:i:s');
        $statement->execute(['id' => $deliveryId, 'attempted_at' => $now, 'delivered_at' => $now,
            'updated_at' => $now, 'http_status' => $httpStatus]);
    }

    public function deliveryPermanentFailure(int $deliveryId, string $errorCode, ?int $httpStatus = null, ?string $now = null): void
    {
        $statement = $this->db->prepare(
            "UPDATE push_deliveries SET status = 'permanent_failure', attempt_count = attempt_count + 1,
                    http_status = :http_status, error_code = :error_code, attempted_at = :attempted_at, updated_at = :updated_at
             WHERE id = :id"
        );
        $now ??= gmdate('Y-m-d H:i:s');
        $statement->execute([
            'id' => $deliveryId, 'attempted_at' => $now, 'updated_at' => $now,
            'http_status' => $httpStatus, 'error_code' => substr($errorCode, 0, 64),
        ]);
    }

    public function deliveryTemporaryFailure(
        int $deliveryId,
        int $attemptNumber,
        string $errorCode,
        ?int $httpStatus = null,
        ?DateTimeImmutable $now = null
    ): void {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $maxAttempts = (int) Config::get('push.max_attempts', 5);
        $terminal = $attemptNumber >= $maxAttempts;
        $delays = Config::get('push.retry_delays_seconds', [60, 120, 300, 600]);
        $delayIndex = max(0, $attemptNumber - 1);
        $delay = is_array($delays) && isset($delays[$delayIndex]) ? (int) $delays[$delayIndex] : 600;
        $statement = $this->db->prepare(
            'UPDATE push_deliveries SET status = :status, attempt_count = :attempt_count,
                    available_at = :available_at, http_status = :http_status, error_code = :error_code,
                    attempted_at = :attempted_at, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $deliveryId,
            'status' => $terminal ? 'failed' : 'temporary_failure',
            'attempt_count' => $attemptNumber,
            'available_at' => $now->modify('+' . max(1, $delay) . ' seconds')->format('Y-m-d H:i:s'),
            'http_status' => $httpStatus,
            'error_code' => substr($errorCode, 0, 64),
            'attempted_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function terminal(int $outboxId, string $status, ?string $now = null): void
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $timeColumn = $status === 'superseded' ? 'superseded_at' : ($status === 'expired' ? 'expired_at' : 'completed_at');
        $statement = $this->db->prepare(
            "UPDATE notification_outbox SET status = :status, claim_token = NULL, claimed_at = NULL,
                    {$timeColumn} = :terminal_at, completed_at = COALESCE(completed_at, :completed_at),
                    updated_at = :updated_at WHERE id = :id"
        );
        $statement->execute(['id' => $outboxId, 'status' => $status, 'terminal_at' => $now,
            'completed_at' => $now, 'updated_at' => $now]);
        if (in_array($status, ['superseded', 'expired'], true)) {
            $deliveries = $this->db->prepare(
                "UPDATE push_deliveries SET status = :status, updated_at = :now
                 WHERE outbox_id = :id AND status IN ('pending', 'temporary_failure')"
            );
            $deliveries->execute(['id' => $outboxId, 'status' => $status, 'now' => $now]);
        }
    }

    public function finishOrReschedule(int $outboxId, ?string $now = null): string
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $disabled = $this->db->prepare(
            "UPDATE push_deliveries d JOIN push_subscriptions s ON s.id = d.subscription_id
             SET d.status = 'permanent_failure', d.error_code = 'SUBSCRIPTION_DISABLED', d.updated_at = :now
             WHERE d.outbox_id = :id AND d.status IN ('pending', 'temporary_failure') AND s.disabled_at IS NOT NULL"
        );
        $disabled->execute(['id' => $outboxId, 'now' => $now]);
        $statement = $this->db->prepare(
            "SELECT COUNT(*) total,
                    SUM(status = 'delivered') delivered,
                    SUM(status IN ('pending', 'temporary_failure')) retryable,
                    MIN(CASE WHEN status IN ('pending', 'temporary_failure') THEN available_at END) next_at
             FROM push_deliveries WHERE outbox_id = :id"
        );
        $statement->execute(['id' => $outboxId]);
        $summary = $statement->fetch() ?: [];
        if ((int) ($summary['retryable'] ?? 0) > 0) {
            $update = $this->db->prepare(
                "UPDATE notification_outbox SET status = 'pending', available_at = :available_at,
                        claim_token = NULL, claimed_at = NULL, updated_at = :now WHERE id = :id"
            );
            $update->execute(['id' => $outboxId, 'available_at' => $summary['next_at'] ?: $now, 'now' => $now]);
            return 'pending';
        }
        $status = (int) ($summary['total'] ?? 0) === 0 || (int) ($summary['delivered'] ?? 0) > 0 ? 'delivered' : 'failed';
        $this->terminal($outboxId, $status, $now);
        return $status;
    }
}
