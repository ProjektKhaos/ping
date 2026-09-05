<?php

declare(strict_types=1);

namespace PingFloodWatch\Repositories;

use PDO;

final class StationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function allEnabled(): array
    {
        return $this->db->query(
            'SELECT * FROM stations WHERE enabled = 1 ORDER BY sort_order, provider_station_code'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function byCode(string $code): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM stations WHERE provider_station_code = :code AND enabled = 1 LIMIT 1'
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function primary(): ?array
    {
        $row = $this->db->query(
            'SELECT * FROM stations WHERE enabled = 1 AND is_primary = 1 ORDER BY sort_order LIMIT 1'
        )->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,array<string,mixed>> */
    public function thresholdsForStation(int $stationId): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM station_thresholds WHERE station_id = :station_id AND active = 1 ORDER BY value_m'
        );
        $statement->execute(['station_id' => $stationId]);
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(string) $row['threshold_type']] = $row;
        }
        return $result;
    }

    /** @return list<string> */
    public function validCodes(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['provider_station_code'],
            $this->allEnabled()
        );
    }
}
