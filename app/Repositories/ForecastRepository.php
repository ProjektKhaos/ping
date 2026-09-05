<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;

final class ForecastRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function zones(): array
    {
        return $this->db->query(
            'SELECT * FROM forecast_zones WHERE enabled = 1 ORDER BY sort_order, code'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function zoneByCode(string $code): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM forecast_zones WHERE code = :code AND enabled = 1');
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array{issued_at:?string,points:list<array<string,mixed>>,raw_payload:mixed} $forecast
     * @return array{status:'inserted'|'unchanged',run_id:int}
     */
    public function storeRun(string $provider, array $forecast): array
    {
        $canonicalPoints = $forecast['points'];
        usort($canonicalPoints, static fn (array $a, array $b): int =>
            [$a['zone_code'], $a['valid_from']] <=> [$b['zone_code'], $b['valid_from']]
        );
        $canonical = array_map(static fn (array $point): array => [
            'zone_code' => $point['zone_code'],
            'valid_from' => $point['valid_from'],
            'valid_to' => $point['valid_to'],
            'rainfall_mm' => $point['rainfall_mm'],
            'rainfall_probability_pct' => $point['rainfall_probability_pct'],
            'weather_code' => $point['weather_code'],
            'source_status' => $point['source_status'],
        ], $canonicalPoints);
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $receivedAt = gmdate('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $select = $this->db->prepare('SELECT id FROM forecast_runs WHERE provider = :provider AND payload_hash = :hash');
            $select->execute(['provider' => $provider, 'hash' => $hash]);
            $existing = $select->fetch();
            if (is_array($existing)) {
                $runId = (int) $existing['id'];
                $this->db->commit();
                foreach ($this->zones() as $zone) {
                    $this->refreshState((int) $zone['id'], $runId, $receivedAt);
                }
                return ['status' => 'unchanged', 'run_id' => $runId];
            }

            $insertRun = $this->db->prepare(
                'INSERT INTO forecast_runs (provider, issued_at, received_at, payload_hash, raw_payload_json)
                 VALUES (:provider, :issued_at, :received_at, :hash, :raw_payload)'
            );
            $insertRun->execute([
                'provider' => $provider,
                'issued_at' => $forecast['issued_at'],
                'received_at' => $receivedAt,
                'hash' => $hash,
                'raw_payload' => json_encode($forecast['raw_payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            $runId = (int) $this->db->lastInsertId();
            $zoneMap = [];
            foreach ($this->zones() as $zone) {
                $zoneMap[(string) $zone['code']] = (int) $zone['id'];
            }
            $insertPoint = $this->db->prepare(
                'INSERT INTO weather_forecast_points
                    (forecast_run_id, forecast_zone_id, valid_from, valid_to, rainfall_mm,
                     rainfall_probability_pct, weather_code, source_status)
                 VALUES (:run_id, :zone_id, :valid_from, :valid_to, :rainfall, :probability, :weather_code, :status)'
            );
            foreach ($forecast['points'] as $point) {
                $code = (string) $point['zone_code'];
                if (!isset($zoneMap[$code])) {
                    continue;
                }
                $insertPoint->execute([
                    'run_id' => $runId,
                    'zone_id' => $zoneMap[$code],
                    'valid_from' => $point['valid_from'],
                    'valid_to' => $point['valid_to'],
                    'rainfall' => $point['rainfall_mm'],
                    'probability' => $point['rainfall_probability_pct'],
                    'weather_code' => $point['weather_code'],
                    'status' => $point['source_status'],
                ]);
            }
            $this->db->commit();
            foreach ($this->zones() as $zone) {
                $this->refreshState((int) $zone['id'], $runId, $receivedAt);
            }
            return ['status' => 'inserted', 'run_id' => $runId];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function refreshState(int $zoneId, int $runId, ?string $receivedAt = null, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($receivedAt === null) {
            $statement = $this->db->prepare('SELECT received_at FROM forecast_runs WHERE id = :id');
            $statement->execute(['id' => $runId]);
            $receivedAt = (string) ($statement->fetchColumn() ?: '');
        }
        $points = $this->pointsForRun($zoneId, $runId, 48, $now);
        $sums = [];
        foreach ([1, 3, 6, 12, 24, 48] as $hours) {
            $slice = array_slice($points, 0, $hours);
            $hasMissing = count($slice) < $hours || array_filter(
                $slice,
                static fn (array $point): bool => !is_numeric($point['rainfall_mm'])
            ) !== [];
            $sums[$hours] = $hasMissing ? null : array_sum(array_map(
                static fn (array $point): float => (float) $point['rainfall_mm'], $slice
            ));
        }
        $maxHourly = null;
        $maxProbability = null;
        foreach ($points as $point) {
            if (is_numeric($point['rainfall_mm'])) {
                $maxHourly = max($maxHourly ?? 0.0, (float) $point['rainfall_mm']);
            }
            if (is_numeric($point['rainfall_probability_pct'])) {
                $maxProbability = max($maxProbability ?? 0.0, (float) $point['rainfall_probability_pct']);
            }
        }
        $freshness = 'offline';
        if ($receivedAt !== '') {
            $age = max(0, ($now->getTimestamp() - (new DateTimeImmutable($receivedAt . ' UTC'))->getTimestamp()) / 60);
            $freshness = $age <= (int) Config::get('freshness.weather_current_minutes')
                ? 'current'
                : ($age <= (int) Config::get('freshness.weather_aging_minutes') ? 'aging' : 'stale');
        }
        $statement = $this->db->prepare(
            'INSERT INTO weather_state
                (forecast_zone_id, latest_forecast_run_id, latest_forecast_received_at, rain_1h_mm, rain_3h_mm,
                 rain_6h_mm, rain_12h_mm, rain_24h_mm, rain_48h_mm, max_hourly_rain_mm,
                 max_probability_pct, freshness_status, updated_at)
             VALUES (:zone_id, :run_id, :received_at, :r1, :r3, :r6, :r12, :r24, :r48, :max_hour, :max_prob, :freshness, :updated_at)
             ON DUPLICATE KEY UPDATE latest_forecast_run_id = VALUES(latest_forecast_run_id),
                latest_forecast_received_at = VALUES(latest_forecast_received_at), rain_1h_mm = VALUES(rain_1h_mm),
                rain_3h_mm = VALUES(rain_3h_mm), rain_6h_mm = VALUES(rain_6h_mm),
                rain_12h_mm = VALUES(rain_12h_mm), rain_24h_mm = VALUES(rain_24h_mm),
                rain_48h_mm = VALUES(rain_48h_mm), max_hourly_rain_mm = VALUES(max_hourly_rain_mm),
                max_probability_pct = VALUES(max_probability_pct), freshness_status = VALUES(freshness_status),
                updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'zone_id' => $zoneId, 'run_id' => $runId, 'received_at' => $receivedAt ?: null,
            'r1' => $sums[1], 'r3' => $sums[3], 'r6' => $sums[6], 'r12' => $sums[12],
            'r24' => $sums[24], 'r48' => $sums[48], 'max_hour' => $maxHourly, 'max_prob' => $maxProbability,
            'freshness' => $freshness, 'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function states(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $currentCutoff = $now->modify('-' . (int) Config::get('freshness.weather_current_minutes', 90) . ' minutes')->format('Y-m-d H:i:s');
        $agingCutoff = $now->modify('-' . (int) Config::get('freshness.weather_aging_minutes', 180) . ' minutes')->format('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            "SELECT z.*, ws.latest_forecast_run_id, ws.latest_forecast_received_at, ws.rain_1h_mm,
                    ws.rain_3h_mm, ws.rain_6h_mm, ws.rain_12h_mm, ws.rain_24h_mm, ws.rain_48h_mm,
                    ws.max_hourly_rain_mm, ws.max_probability_pct,
                    CASE WHEN ws.latest_forecast_received_at IS NULL THEN 'offline'
                         WHEN ws.latest_forecast_received_at >= :current_cutoff THEN 'current'
                         WHEN ws.latest_forecast_received_at >= :aging_cutoff THEN 'aging'
                         ELSE 'stale' END AS freshness_status,
                    ws.updated_at AS state_updated_at
             FROM forecast_zones z LEFT JOIN weather_state ws ON ws.forecast_zone_id = z.id
             WHERE z.enabled = 1 ORDER BY z.sort_order, z.code"
        );
        $statement->execute(['current_cutoff' => $currentCutoff, 'aging_cutoff' => $agingCutoff]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function forecast(int $zoneId, int $hours): array
    {
        $statement = $this->db->prepare(
            'SELECT p.valid_from, p.valid_to, p.rainfall_mm, p.rainfall_probability_pct, p.weather_code,
                    r.received_at, r.issued_at
             FROM weather_state ws
             JOIN forecast_runs r ON r.id = ws.latest_forecast_run_id
             JOIN weather_forecast_points p ON p.forecast_run_id = r.id AND p.forecast_zone_id = ws.forecast_zone_id
             WHERE ws.forecast_zone_id = :zone_id AND p.valid_to > UTC_TIMESTAMP()
             ORDER BY p.valid_from LIMIT :hours'
        );
        $statement->bindValue(':zone_id', $zoneId, PDO::PARAM_INT);
        $statement->bindValue(':hours', $hours, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    private function pointsForRun(int $zoneId, int $runId, int $hours, DateTimeImmutable $now): array
    {
        $statement = $this->db->prepare(
            'SELECT rainfall_mm, rainfall_probability_pct FROM weather_forecast_points
             WHERE forecast_zone_id = :zone_id AND forecast_run_id = :run_id AND valid_to > :now
             ORDER BY valid_from LIMIT :hours'
        );
        $statement->bindValue(':zone_id', $zoneId, PDO::PARAM_INT);
        $statement->bindValue(':run_id', $runId, PDO::PARAM_INT);
        $statement->bindValue(':now', $now->format('Y-m-d H:i:s'));
        $statement->bindValue(':hours', $hours, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
