<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;
use PingFloodWatch\Repositories\AlertRepository;
use PingFloodWatch\Repositories\NotificationOutboxRepository;

final class AlertManager
{
    private NotificationOutboxRepository $outbox;

    public function __construct(
        private readonly PDO $db,
        private readonly AlertRepository $alerts,
        ?NotificationOutboxRepository $outbox = null
    ) {
        $this->outbox = $outbox ?? new NotificationOutboxRepository($db);
    }

    /** @param array<string,mixed> $risk @return array<string,mixed>|null */
    public function reconcile(array $risk, ?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowString = $now->format('Y-m-d H:i:s');
        $severity = (string) $risk['severity'];

        $lockStatement = $this->db->query("SELECT GET_LOCK('ping_flood_watch_alert_manager', 5)");
        if ((int) $lockStatement->fetchColumn() !== 1) {
            throw new \RuntimeException('Unable to acquire the alert lifecycle lock.');
        }
        try {
            $this->db->beginTransaction();
            try {
                $active = $this->alerts->active(true);
            if ($severity === 'unknown') {
                if ($active !== null) {
                    $this->alerts->touch((int) $active['id'], $nowString, false);
                }
                $this->db->commit();
                return $active;
            }

            if ($active === null) {
                if ($severity === 'normal') {
                    $this->db->commit();
                    return null;
                }
                $id = $this->alerts->create($risk, $nowString);
                $eventId = $this->alerts->event($id, 'opened', null, $severity, $risk['reason_codes'], $nowString);
                $this->enqueueNotification($id, $eventId, 'opened', $severity, $risk, $nowString);
                $this->alerts->attachStations($id, array_map('intval', $risk['context']['station_ids'] ?? []));
                $this->db->commit();
                return $this->alerts->active();
            }

            $current = (string) $active['severity'];
            if ($this->rank($severity) > $this->rank($current)) {
                $this->alerts->update((int) $active['id'], $risk, null, $nowString);
                $eventId = $this->alerts->event((int) $active['id'], 'escalated', $current, $severity, $risk['reason_codes'], $nowString);
                $this->enqueueNotification((int) $active['id'], $eventId, 'escalated', $severity, $risk, $nowString);
                $this->alerts->attachStations((int) $active['id'], array_map('intval', $risk['context']['station_ids'] ?? []));
                $this->db->commit();
                return $this->alerts->active();
            }
            if ($severity === $current) {
                $this->alerts->update((int) $active['id'], $risk, null, $nowString);
                $this->alerts->attachStations((int) $active['id'], array_map('intval', $risk['context']['station_ids'] ?? []));
                $this->db->commit();
                return $this->alerts->active();
            }

            $pendingSince = $active['pending_since'] ?: null;
            if ($pendingSince === null) {
                $this->alerts->setPending((int) $active['id'], $nowString, $nowString);
                $this->alerts->event((int) $active['id'], 'deescalation_pending', $current, $severity, $risk['reason_codes'], $nowString);
                $this->db->commit();
                return $this->alerts->active();
            }
            $elapsed = $now->getTimestamp() - (new DateTimeImmutable($pendingSince . ' UTC'))->getTimestamp();
            if ($elapsed < ((int) Config::get('alerts.clear_delay_minutes', 20) * 60)) {
                $this->alerts->touch((int) $active['id'], $nowString, false);
                $this->db->commit();
                return $this->alerts->active();
            }
            if ($severity === 'normal') {
                $this->alerts->clear((int) $active['id'], $nowString);
                $eventId = $this->alerts->event((int) $active['id'], 'cleared', $current, 'normal', $risk['reason_codes'], $nowString);
                $this->enqueueNotification((int) $active['id'], $eventId, 'cleared', 'normal', $risk, $nowString);
                $this->db->commit();
                return null;
            }
            $this->alerts->update((int) $active['id'], $risk, null, $nowString);
            $this->alerts->event((int) $active['id'], 'deescalated', $current, $severity, $risk['reason_codes'], $nowString);
            $this->db->commit();
                return $this->alerts->active();
            } catch (\Throwable $error) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $error;
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('ping_flood_watch_alert_manager')");
        }
    }

    private function rank(string $severity): int
    {
        return ['unknown' => -1, 'normal' => 0, 'watch' => 1, 'warning' => 2, 'critical' => 3][$severity] ?? -1;
    }

    /** @param array<string,mixed> $risk */
    private function enqueueNotification(
        int $alertId,
        int $eventId,
        string $eventType,
        string $severity,
        array $risk,
        string $now
    ): void {
        $this->outbox->enqueue($alertId, $eventId, $eventType, $severity, [
            'alert_id' => $alertId,
            'alert_event_id' => $eventId,
            'event_type' => $eventType,
            'severity' => $severity,
            'message_key' => (string) ($risk['message_key'] ?? 'risk.combined.unknown'),
            'url' => 'alerts.php',
            'created_at' => $now,
        ], $now);
    }
}
