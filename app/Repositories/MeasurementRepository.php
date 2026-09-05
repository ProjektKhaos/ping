<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;
use PingFloodWatch\Services\TrendCalculator;

final class MeasurementRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string,mixed> $measurement @return 'inserted'|'updated'|'unchanged' */
    public function store(array $measurement, int $stationId): string
    {
        $canonical = [
            'provider_record_id' => $measurement['provider_record_id'] ?? null,
            'source_measured_at' => $measurement['source_measured_at'],
            'water_level_gauge_m' => $measurement['water_level_gauge_m'],
            'water_level_msl_m' => $measurement['water_level_msl_m'],
            'discharge_m3s' => $measurement['discharge_m3s'],
            'rainfall_mm' => $measurement['rainfall_mm'],
            'capacity_percent' => $measurement['capacity_percent'],
            'source_situation' => $measurement['source_situation'],
            'source_status' => $measurement['source_status'],
            'raw_payload' => $measurement['raw_payload'],
        ];
        $payload = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $payload);
        $receivedAt = gmdate('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $select = $this->db->prepare(
                'SELECT id, source_hash, raw_payload_json FROM measurements
                 WHERE station_id = :station_id AND measured_at = :measured_at FOR UPDATE'
            );
            $select->execute(['station_id' => $stationId, 'measured_at' => $measurement['measured_at']]);
            $existing = $select->fetch();
            if (!is_array($existing)) {
                $insert = $this->db->prepare(
                    'INSERT INTO measurements
                    (station_id, provider_record_id, measured_at, source_measured_at, water_level_gauge_m,
                     water_level_msl_m, discharge_m3s, rainfall_mm, capacity_percent, source_situation,
                     source_status, raw_payload_json, source_hash, received_at)
                    VALUES (:station_id, :provider_record_id, :measured_at, :source_measured_at, :gauge,
                     :msl, :discharge, :rainfall, :capacity, :situation, :status, :raw_payload, :source_hash, :received_at)'
                );
                $insert->execute($this->parameters($measurement, $stationId, $payload, $hash, $receivedAt));
                $this->db->commit();
                return 'inserted';
            }
            if (hash_equals((string) $existing['source_hash'], $hash)) {
                $this->db->commit();
                return 'unchanged';
            }

            $revision = $this->db->prepare(
                'INSERT INTO measurement_revisions
                    (measurement_id, previous_payload_json, replacement_payload_json, previous_hash, replacement_hash, revised_at)
                 VALUES (:measurement_id, :previous_payload, :replacement_payload, :previous_hash, :replacement_hash, :revised_at)'
            );
            $revision->execute([
                'measurement_id' => $existing['id'],
                'previous_payload' => $existing['raw_payload_json'],
                'replacement_payload' => json_encode($measurement['raw_payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'previous_hash' => $existing['source_hash'],
                'replacement_hash' => $hash,
                'revised_at' => $receivedAt,
            ]);
            $update = $this->db->prepare(
                'UPDATE measurements SET provider_record_id = :provider_record_id, source_measured_at = :source_measured_at,
                    water_level_gauge_m = :gauge, water_level_msl_m = :msl, discharge_m3s = :discharge,
                    rainfall_mm = :rainfall, capacity_percent = :capacity, source_situation = :situation,
                    source_status = :status, raw_payload_json = :raw_payload, source_hash = :source_hash,
                    received_at = :received_at, revised_at = :revised_at, revision_count = revision_count + 1
                 WHERE station_id = :station_id AND measured_at = :measured_at'
            );
            $updateParameters = $this->parameters($measurement, $stationId, $payload, $hash, $receivedAt);
            $updateParameters['revised_at'] = $receivedAt;
            $update->execute($updateParameters);
            $this->db->commit();
            return 'updated';
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function refreshState(int $stationId, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $statement = $this->db->prepare(
            'SELECT id, measured_at, water_level_gauge_m AS value
             FROM measurements WHERE station_id = :station_id ORDER BY measured_at DESC LIMIT 600'
        );
        $statement->execute(['station_id' => $stationId]);
        $rows = $statement->fetchAll();
        $latest = $rows[0] ?? null;
        $freshness = 'offline';
        if (is_array($latest)) {
            $age = max(0, ($now->getTimestamp() - (new DateTimeImmutable($latest['measured_at'] . ' UTC'))->getTimestamp()) / 60);
            $freshness = $age <= (int) Config::get('freshness.water_live_minutes')
                ? 'live'
                : ($age <= (int) Config::get('freshness.water_delayed_minutes') ? 'delayed' : 'stale');
        }
        $points = array_map(static fn (array $row): array => [
            'measured_at' => (string) $row['measured_at'],
            'value' => is_numeric($row['value']) ? (float) $row['value'] : null,
        ], $rows);
        $tolerance = (int) Config::get('trends.tolerance_minutes', 10);
        $changes = [];
        foreach ([1, 3, 6, 12, 24] as $hours) {
            $changes[$hours] = TrendCalculator::change($points, $hours, $tolerance);
        }
        $upsert = $this->db->prepare(
            'INSERT INTO station_state
                (station_id, latest_measurement_id, freshness_status, change_1h_m, change_3h_m, change_6h_m,
                 change_12h_m, change_24h_m, rate_1h_m_per_hour, updated_at)
             VALUES (:station_id, :measurement_id, :freshness, :c1, :c3, :c6, :c12, :c24, :rate, :updated_at)
             ON DUPLICATE KEY UPDATE latest_measurement_id = VALUES(latest_measurement_id),
                freshness_status = VALUES(freshness_status), change_1h_m = VALUES(change_1h_m),
                change_3h_m = VALUES(change_3h_m), change_6h_m = VALUES(change_6h_m),
                change_12h_m = VALUES(change_12h_m), change_24h_m = VALUES(change_24h_m),
                rate_1h_m_per_hour = VALUES(rate_1h_m_per_hour), updated_at = VALUES(updated_at)'
        );
        $upsert->execute([
            'station_id' => $stationId,
            'measurement_id' => $latest['id'] ?? null,
            'freshness' => $freshness,
            'c1' => $changes[1], 'c3' => $changes[3], 'c6' => $changes[6],
            'c12' => $changes[12], 'c24' => $changes[24], 'rate' => $changes[1],
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function stationsWithState(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $liveCutoff = $now->modify('-' . (int) Config::get('freshness.water_live_minutes', 75) . ' minutes')->format('Y-m-d H:i:s');
        $delayedCutoff = $now->modify('-' . (int) Config::get('freshness.water_delayed_minutes', 120) . ' minutes')->format('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            "SELECT s.*, CASE
                        WHEN m.measured_at IS NULL THEN 'offline'
                        WHEN m.measured_at >= :live_cutoff THEN 'live'
                        WHEN m.measured_at >= :delayed_cutoff THEN 'delayed'
                        ELSE 'stale' END AS freshness_status,
                    ss.change_1h_m, ss.change_3h_m, ss.change_6h_m,
                    ss.change_12h_m, ss.change_24h_m, ss.rate_1h_m_per_hour, ss.updated_at AS state_updated_at,
                    m.id AS measurement_id, m.measured_at, m.source_measured_at, m.water_level_gauge_m,
                    m.water_level_msl_m, m.discharge_m3s, m.rainfall_mm, m.capacity_percent,
                    m.source_situation, m.source_status, m.received_at
             FROM stations s
             LEFT JOIN station_state ss ON ss.station_id = s.id
             LEFT JOIN measurements m ON m.id = ss.latest_measurement_id
             WHERE s.enabled = 1 ORDER BY s.sort_order, s.provider_station_code"
        );
        $statement->execute(['live_cutoff' => $liveCutoff, 'delayed_cutoff' => $delayedCutoff]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function stationWithState(string $code): ?array
    {
        foreach ($this->stationsWithState() as $row) {
            if ($row['provider_station_code'] === $code) {
                return $row;
            }
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public function history(int $stationId, int $hours): array
    {
        $statement = $this->db->prepare(
            'SELECT measured_at, source_measured_at, water_level_gauge_m, water_level_msl_m,
                    discharge_m3s, capacity_percent, source_situation, revision_count
             FROM measurements
             WHERE station_id = :station_id AND measured_at >= UTC_TIMESTAMP() - INTERVAL :hours HOUR
             ORDER BY measured_at'
        );
        $statement->bindValue(':station_id', $stationId, PDO::PARAM_INT);
        $statement->bindValue(':hours', $hours, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $measurement @return array<string,mixed> */
    private function parameters(array $measurement, int $stationId, string $payload, string $hash, string $receivedAt): array
    {
        return [
            'station_id' => $stationId,
            'provider_record_id' => $measurement['provider_record_id'] ?? null,
            'measured_at' => $measurement['measured_at'],
            'source_measured_at' => $measurement['source_measured_at'],
            'gauge' => $measurement['water_level_gauge_m'],
            'msl' => $measurement['water_level_msl_m'],
            'discharge' => $measurement['discharge_m3s'],
            'rainfall' => $measurement['rainfall_mm'],
            'capacity' => $measurement['capacity_percent'],
            'situation' => $measurement['source_situation'],
            'status' => $measurement['source_status'],
            'raw_payload' => json_encode($measurement['raw_payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'source_hash' => $hash,
            'received_at' => $receivedAt,
        ];
    }
}
