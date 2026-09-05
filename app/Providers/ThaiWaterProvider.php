<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

use DateTimeImmutable;
use DateTimeZone;
use PingFloodWatch\Config;
use PingFloodWatch\HttpClient;
use PingFloodWatch\Services\GaugeConverter;

final class ThaiWaterProvider implements WaterProviderInterface
{
    public function __construct(private readonly HttpClient $http = new HttpClient())
    {
    }

    public function getName(): string
    {
        return 'thaiwater';
    }

    public function fetchLatestMeasurements(array $stations): array
    {
        try {
            $payload = $this->http->getJson(
                (string) Config::get('providers.thaiwater.current_url'),
                (int) Config::get('providers.thaiwater.connect_timeout'),
                (int) Config::get('providers.thaiwater.timeout'),
                (int) Config::get('providers.thaiwater.max_bytes')
            );
        } catch (\Throwable $error) {
            throw new ProviderException($error->getMessage(), 'THAIWATER_REQUEST_FAILED');
        }

        $records = $payload['waterlevel_data']['data'] ?? null;
        if (!is_array($records)) {
            throw new ProviderException('ThaiWater response is missing waterlevel_data.data.', 'THAIWATER_SCHEMA_INVALID');
        }

        $wanted = [];
        foreach ($stations as $station) {
            $wanted[(string) $station['provider_station_code']] = $station;
        }

        $normalized = [];
        foreach ($records as $record) {
            if (!is_array($record) || !isset($record['station']) || !is_array($record['station'])) {
                continue;
            }
            $code = (string) ($record['station']['tele_station_oldcode'] ?? '');
            if (!isset($wanted[$code])) {
                continue;
            }
            $normalized[] = $this->normalize($record, $wanted[$code]);
        }

        return $normalized;
    }

    public function fetchHistory(array $station, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $query = http_build_query([
            'station_type' => 'tele_waterlevel',
            'station_id' => (string) $station['provider_station_id'],
        ]);
        try {
            $payload = $this->http->getJson(
                (string) Config::get('providers.thaiwater.history_url') . '?' . $query,
                (int) Config::get('providers.thaiwater.connect_timeout'),
                (int) Config::get('providers.thaiwater.timeout'),
                (int) Config::get('providers.thaiwater.max_bytes')
            );
        } catch (\Throwable $error) {
            throw new ProviderException($error->getMessage(), 'THAIWATER_HISTORY_FAILED');
        }

        $data = $payload['data'] ?? null;
        $points = is_array($data) ? ($data['graph_data'] ?? null) : null;
        if (($payload['result'] ?? null) !== 'OK' || !is_array($points)) {
            throw new ProviderException('ThaiWater history response is invalid.', 'THAIWATER_HISTORY_SCHEMA_INVALID');
        }

        $normalized = [];
        foreach ($points as $point) {
            if (!is_array($point) || !is_numeric($point['value'] ?? null) || !isset($point['datetime'])) {
                continue;
            }
            $measured = $this->parseBangkokTime((string) $point['datetime']);
            if ($measured < $from || $measured > $to) {
                continue;
            }
            $msl = (float) $point['value'];
            $zero = is_numeric($station['gauge_zero_msl'] ?? null) ? (float) $station['gauge_zero_msl'] : null;
            $bankMsl = is_numeric($data['min_bank'] ?? null) ? (float) $data['min_bank'] : null;
            $ground = is_numeric($data['ground_level'] ?? null) ? (float) $data['ground_level'] : null;
            $capacity = ($bankMsl !== null && $ground !== null && $bankMsl > $ground)
                ? (($msl - $ground) / ($bankMsl - $ground)) * 100
                : null;

            $normalized[] = [
                'provider' => $this->getName(),
                'provider_record_id' => null,
                'station_code' => (string) $station['provider_station_code'],
                'measured_at' => $measured->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'source_measured_at' => (string) $point['datetime'],
                'water_level_msl_m' => $msl,
                'water_level_gauge_m' => GaugeConverter::fromMsl($msl, $zero),
                'discharge_m3s' => is_numeric($point['discharge'] ?? null) ? (float) $point['discharge'] : null,
                'rainfall_mm' => null,
                'capacity_percent' => $capacity,
                'source_situation' => $this->situationFromCapacity($capacity),
                'source_status' => 'historical',
                'raw_payload' => $point,
            ];
        }

        return $normalized;
    }

    /** @param array<string,mixed> $record @param array<string,mixed> $station @return array<string,mixed> */
    private function normalize(array $record, array $station): array
    {
        $sourceTime = (string) ($record['waterlevel_datetime'] ?? '');
        if ($sourceTime === '') {
            throw new ProviderException('ThaiWater measurement is missing a timestamp.', 'THAIWATER_TIMESTAMP_MISSING');
        }
        $measured = $this->parseBangkokTime($sourceTime);
        if ($measured > new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC'))) {
            throw new ProviderException('ThaiWater measurement timestamp is unexpectedly in the future.', 'THAIWATER_TIMESTAMP_FUTURE');
        }
        if (!is_numeric($record['waterlevel_msl'] ?? null)) {
            throw new ProviderException('ThaiWater measurement is missing a numeric MSL value.', 'THAIWATER_LEVEL_INVALID');
        }

        $msl = (float) $record['waterlevel_msl'];
        $zero = is_numeric($station['gauge_zero_msl'] ?? null) ? (float) $station['gauge_zero_msl'] : null;
        $capacity = is_numeric($record['storage_percent'] ?? null) ? (float) $record['storage_percent'] : null;
        $situationLevel = is_numeric($record['situation_level'] ?? null) ? (int) $record['situation_level'] : null;

        return [
            'provider' => $this->getName(),
            'provider_record_id' => isset($record['id']) ? (string) $record['id'] : null,
            'station_code' => (string) $station['provider_station_code'],
            'measured_at' => $measured->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'source_measured_at' => $sourceTime,
            'water_level_msl_m' => $msl,
            'water_level_gauge_m' => GaugeConverter::fromMsl($msl, $zero),
            'discharge_m3s' => is_numeric($record['discharge'] ?? null) ? (float) $record['discharge'] : null,
            'rainfall_mm' => null,
            'capacity_percent' => $capacity,
            'source_situation' => $this->normalizeSituation($situationLevel, $capacity),
            'source_status' => 'current',
            'raw_payload' => $record,
        ];
    }

    private function parseBangkokTime(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value, new DateTimeZone('Asia/Bangkok'));
        } catch (\Throwable) {
            throw new ProviderException('ThaiWater returned an invalid timestamp.', 'THAIWATER_TIMESTAMP_INVALID');
        }
    }

    private function normalizeSituation(?int $level, ?float $capacity): string
    {
        if ($level === 5 || ($capacity !== null && $capacity > 100)) {
            return 'overflow';
        }
        if ($level === 4 || ($capacity !== null && $capacity > 70)) {
            return 'high';
        }
        if ($level === 1) {
            return 'low_critical';
        }
        if ($level === 2) {
            return 'low';
        }
        return 'normal';
    }

    private function situationFromCapacity(?float $capacity): string
    {
        if ($capacity === null) {
            return 'unknown';
        }
        if ($capacity > 100) {
            return 'overflow';
        }
        if ($capacity > 70) {
            return 'high';
        }
        return 'normal';
    }
}
