<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use PDO;

final class AlertRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function active(bool $lock = false): ?array
    {
        $sql = "SELECT * FROM alerts WHERE status = 'active' ORDER BY id DESC LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql)->fetch();
        return is_array($row) ? $this->decode($row) : null;
    }

    /** @param array<string,mixed> $risk */
    public function create(array $risk, string $now): int
    {
        $statement = $this->db->prepare(
            "INSERT INTO alerts (severity, status, title_key, message_key, reason_codes_json, context_json, triggered_at, last_seen_at)
             VALUES (:severity, 'active', :title_key, :message_key, :reasons, :context, :triggered_at, :last_seen_at)"
        );
        $statement->execute([
            'severity' => $risk['severity'], 'title_key' => 'alert.title.' . $risk['severity'],
            'message_key' => $risk['message_key'],
            'reasons' => json_encode($risk['reason_codes'], JSON_THROW_ON_ERROR),
            'context' => json_encode($risk['context'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'triggered_at' => $now, 'last_seen_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $risk */
    public function update(int $id, array $risk, ?string $pendingSince, string $now): void
    {
        $statement = $this->db->prepare(
            'UPDATE alerts SET severity = :severity, title_key = :title_key, message_key = :message_key,
                reason_codes_json = :reasons, context_json = :context, last_seen_at = :now,
                pending_since = :pending_since WHERE id = :id'
        );
        $statement->execute([
            'id' => $id, 'severity' => $risk['severity'], 'title_key' => 'alert.title.' . $risk['severity'],
            'message_key' => $risk['message_key'],
            'reasons' => json_encode($risk['reason_codes'], JSON_THROW_ON_ERROR),
            'context' => json_encode($risk['context'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'now' => $now, 'pending_since' => $pendingSince,
        ]);
    }

    public function setPending(int $id, string $pendingSince, string $now): void
    {
        $statement = $this->db->prepare('UPDATE alerts SET pending_since = :pending, last_seen_at = :now WHERE id = :id');
        $statement->execute(['id' => $id, 'pending' => $pendingSince, 'now' => $now]);
    }

    public function touch(int $id, string $now, bool $clearPending = true): void
    {
        $statement = $this->db->prepare(
            'UPDATE alerts SET last_seen_at = :now' . ($clearPending ? ', pending_since = NULL' : '') . ' WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'now' => $now]);
    }

    public function clear(int $id, string $now): void
    {
        $statement = $this->db->prepare(
            "UPDATE alerts SET status = 'cleared', cleared_at = :cleared_at, last_seen_at = :last_seen_at, pending_since = NULL WHERE id = :id"
        );
        $statement->execute(['id' => $id, 'cleared_at' => $now, 'last_seen_at' => $now]);
    }

    /** @param list<string> $reasons */
    public function event(int $id, string $type, ?string $from, ?string $to, array $reasons, string $now): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO alert_events (alert_id, event_type, from_severity, to_severity, reason_codes_json, occurred_at)
             VALUES (:id, :type, :from_severity, :to_severity, :reasons, :now)'
        );
        $statement->execute([
            'id' => $id, 'type' => $type, 'from_severity' => $from, 'to_severity' => $to,
            'reasons' => json_encode($reasons, JSON_THROW_ON_ERROR), 'now' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param list<int> $stationIds */
    public function attachStations(int $alertId, array $stationIds): void
    {
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO alert_stations (alert_id, station_id, measurement_id)
             SELECT :alert_id, s.id, ss.latest_measurement_id FROM stations s
             LEFT JOIN station_state ss ON ss.station_id = s.id WHERE s.id = :station_id'
        );
        foreach (array_unique($stationIds) as $stationId) {
            $statement->execute(['alert_id' => $alertId, 'station_id' => $stationId]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        $statement = $this->db->prepare('SELECT * FROM alerts ORDER BY triggered_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn (array $row): array => $this->decode($row), $statement->fetchAll());
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decode(array $row): array
    {
        $row['reason_codes'] = json_decode((string) $row['reason_codes_json'], true) ?: [];
        $row['context'] = json_decode((string) $row['context_json'], true) ?: [];
        unset($row['reason_codes_json'], $row['context_json']);
        return $row;
    }
}
