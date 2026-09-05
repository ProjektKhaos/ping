<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

use DateTimeImmutable;
use DateTimeZone;

final class MockWaterProvider implements WaterProviderInterface
{
    public function getName(): string
    {
        return 'mock-water';
    }

    public function fetchLatestMeasurements(array $stations): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $levels = ['P.67' => 0.81, 'P.103' => 3.54, 'P.1' => 1.34];
        return array_map(fn (array $station): array => $this->point($station, $now, $levels[(string) $station['provider_station_code']] ?? 1.0), $stations);
    }

    public function fetchHistory(array $station, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $points = [];
        $cursor = $from;
        $base = ['P.67' => 0.81, 'P.103' => 3.54, 'P.1' => 1.34][(string) $station['provider_station_code']] ?? 1.0;
        while ($cursor <= $to) {
            $points[] = $this->point($station, $cursor, $base + sin((float) $cursor->format('G') / 4) * 0.04);
            $cursor = $cursor->modify('+1 hour');
        }
        return $points;
    }

    /** @param array<string,mixed> $station @return array<string,mixed> */
    private function point(array $station, DateTimeImmutable $time, float $gauge): array
    {
        $zero = is_numeric($station['gauge_zero_msl'] ?? null) ? (float) $station['gauge_zero_msl'] : null;
        return [
            'provider' => $this->getName(), 'provider_record_id' => null,
            'station_code' => (string) $station['provider_station_code'],
            'measured_at' => $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'source_measured_at' => $time->format(DATE_ATOM),
            'water_level_msl_m' => $zero !== null ? $zero + $gauge : null,
            'water_level_gauge_m' => $gauge, 'discharge_m3s' => null, 'rainfall_mm' => null,
            'capacity_percent' => 50.0, 'source_situation' => 'normal', 'source_status' => 'demo',
            'raw_payload' => ['demo' => true],
        ];
    }
}

