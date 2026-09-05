<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

use DateTimeImmutable;
use DateTimeZone;
use PingFloodWatch\Config;
use PingFloodWatch\HttpClient;

final class CmfloodProvider implements WaterProviderInterface
{
    public function __construct(private readonly HttpClient $http = new HttpClient())
    {
    }

    public function getName(): string
    {
        return 'cmflood';
    }

    public function fetchLatestMeasurements(array $stations): array
    {
        $points = [];
        foreach ($stations as $station) {
            $rows = $this->request($station, null);
            $row = $rows[0] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $point = $this->normalize($row, $station, 'current');
            if ($point !== null) {
                $points[] = $point;
            }
        }
        return $points;
    }

    public function fetchHistory(array $station, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $points = [];
        foreach ($this->request($station, $from) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $point = $this->normalize($row, $station, 'historical');
            if ($point === null) {
                continue;
            }
            $measured = new DateTimeImmutable($point['measured_at'] . ' UTC');
            if ($measured >= $from && $measured <= $to) {
                $points[] = $point;
            }
        }
        usort($points, static fn (array $a, array $b): int => $a['measured_at'] <=> $b['measured_at']);
        return $points;
    }

    /** @param array<string,mixed> $station @return list<array<string,mixed>> */
    private function request(array $station, ?DateTimeImmutable $from): array
    {
        [$kind, $sourceId] = $this->source($station);
        $base = rtrim((string) Config::get('providers.cmflood.url'), '/');
        $limit = $from === null ? 1 : 1000;
        $fromFilter = $from === null ? '' : $this->sourceClock($from);
        $query = match ($kind) {
            'disaster' => 'disaster_level?code=eq.' . rawurlencode($sourceId)
                . '&water_level=not.is.null&recorded_at=not.is.null'
                . ($fromFilter !== '' ? '&recorded_at=gte.' . rawurlencode($fromFilter) : '')
                . '&select=water_level,recorded_at&order=recorded_at.' . ($from === null ? 'desc' : 'asc') . '.nullslast&limit=' . $limit,
            'water_levels' => 'water_levels?station_id=eq.' . rawurlencode($sourceId)
                . '&water_level=not.is.null&log_datetime=not.is.null'
                . ($fromFilter !== '' ? '&log_datetime=gte.' . rawurlencode($fromFilter) : '')
                . '&select=water_level,log_datetime&order=log_datetime.' . ($from === null ? 'desc' : 'asc') . '.nullslast&limit=' . $limit,
            'flagship' => 'flagship_hydro?station=eq.' . rawurlencode($sourceId)
                . '&level=not.is.null&date=not.is.null&time=not.is.null'
                . ($from !== null ? '&date=gte.' . rawurlencode($from->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Y-m-d')) : '')
                . '&select=level,date,time,created_at&order=date.' . ($from === null ? 'desc' : 'asc')
                . ',time.' . ($from === null ? 'desc' : 'asc') . '&limit=' . $limit,
            default => throw new ProviderException('CMFlood station source is unsupported.', 'CMFLOOD_STATION_INVALID'),
        };

        $key = (string) Config::get('providers.cmflood.anon_key', '');
        if ($base === '' || $key === '') {
            throw new ProviderException('CMFlood provider configuration is incomplete.', 'CMFLOOD_CONFIG_MISSING');
        }
        try {
            $payload = $this->http->getJsonWithHeaders(
                $base . '/' . $query,
                (int) Config::get('providers.cmflood.connect_timeout', 5),
                (int) Config::get('providers.cmflood.timeout', 15),
                (int) Config::get('providers.cmflood.max_bytes', 2_000_000),
                ['apikey: ' . $key, 'Authorization: Bearer ' . $key]
            );
        } catch (\Throwable $error) {
            throw new ProviderException($error->getMessage(), 'CMFLOOD_REQUEST_FAILED');
        }
        if (!array_is_list($payload)) {
            throw new ProviderException('CMFlood response must be a JSON list.', 'CMFLOOD_SCHEMA_INVALID');
        }
        return $payload;
    }

    /** @param array<string,mixed> $station @return array{string,string} */
    private function source(array $station): array
    {
        $parts = explode(':', (string) ($station['provider_station_id'] ?? ''), 2);
        if (count($parts) !== 2 || !in_array($parts[0], ['disaster', 'water_levels', 'flagship'], true)
            || !preg_match('/^[A-Za-z0-9.]+$/', $parts[1])) {
            throw new ProviderException('CMFlood station source is invalid.', 'CMFLOOD_STATION_INVALID');
        }
        return [$parts[0], $parts[1]];
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $station @return array<string,mixed>|null */
    private function normalize(array $row, array $station, string $status): ?array
    {
        [$kind] = $this->source($station);
        $value = match ($kind) {
            'disaster', 'water_levels' => $row['water_level'] ?? null,
            'flagship' => $row['level'] ?? null,
            default => null,
        };
        $sourceTime = match ($kind) {
            'disaster' => $row['recorded_at'] ?? null,
            'water_levels' => $row['log_datetime'] ?? null,
            'flagship' => isset($row['date'], $row['time']) ? $row['date'] . 'T' . $row['time'] : null,
            default => null,
        };
        if (!is_numeric($value) || !is_string($sourceTime)) {
            return null;
        }
        $gauge = (float) $value;
        $code = (string) ($station['provider_station_code'] ?? '');
        if ($gauge < 0 || $gauge > 20 || ($code === 'CMI01' && $gauge > 4) || ($code === 'CMI02' && $gauge < 2)) {
            return null;
        }
        $measured = $this->parseSourceClock($sourceTime);
        if ($measured > new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC'))) {
            throw new ProviderException('CMFlood timestamp is unexpectedly in the future.', 'CMFLOOD_TIMESTAMP_FUTURE');
        }

        $zero = is_numeric($station['gauge_zero_msl'] ?? null) ? (float) $station['gauge_zero_msl'] : null;
        $bank = is_numeric($station['bank_level_msl'] ?? null) ? (float) $station['bank_level_msl'] : null;
        $capacity = $zero !== null && $bank !== null && $bank > $zero ? $gauge / ($bank - $zero) * 100 : null;
        $situation = $capacity === null ? 'unknown' : ($capacity > 100 ? 'overflow' : ($capacity > 70 ? 'high' : 'normal'));

        return [
            'provider' => $this->getName(),
            'provider_record_id' => hash('sha256', $code . '|' . $sourceTime),
            'station_code' => $code,
            'measured_at' => $measured->format('Y-m-d H:i:s'),
            'source_measured_at' => $sourceTime,
            'water_level_gauge_m' => $gauge,
            'water_level_msl_m' => $zero !== null ? $zero + $gauge : null,
            'discharge_m3s' => null,
            'rainfall_mm' => null,
            'capacity_percent' => $capacity,
            'source_situation' => $situation,
            'source_status' => 'cmflood_' . $status,
            'raw_payload' => $row,
        ];
    }

    private function parseSourceClock(string $value): DateTimeImmutable
    {
        $clock = str_replace('T', ' ', substr($value, 0, 19));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $clock, new DateTimeZone('Asia/Bangkok'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ProviderException('CMFlood returned an invalid timestamp.', 'CMFLOOD_TIMESTAMP_INVALID');
        }
        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function sourceClock(DateTimeImmutable $utc): string
    {
        return $utc->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Y-m-d\TH:i:s') . '+00:00';
    }
}
