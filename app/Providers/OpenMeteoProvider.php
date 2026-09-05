<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

use DateTimeImmutable;
use DateTimeZone;
use PingFloodWatch\Config;
use PingFloodWatch\HttpClient;

final class OpenMeteoProvider implements WeatherProviderInterface
{
    public function __construct(private readonly HttpClient $http = new HttpClient())
    {
    }

    public function getName(): string
    {
        return 'openmeteo';
    }

    public function fetchForecast(array $zones): array
    {
        if ($zones === []) {
            return ['issued_at' => null, 'points' => [], 'raw_payload' => []];
        }

        $query = http_build_query([
            'latitude' => implode(',', array_map(static fn (array $zone): string => (string) $zone['latitude'], $zones)),
            'longitude' => implode(',', array_map(static fn (array $zone): string => (string) $zone['longitude'], $zones)),
            'hourly' => 'precipitation,precipitation_probability,weather_code',
            'forecast_hours' => 48,
            'timezone' => (string) Config::get('app.timezone', 'Asia/Bangkok'),
        ]);

        try {
            $payload = $this->http->getJson(
                (string) Config::get('providers.openmeteo.url') . '?' . $query,
                (int) Config::get('providers.openmeteo.connect_timeout'),
                (int) Config::get('providers.openmeteo.timeout'),
                (int) Config::get('providers.openmeteo.max_bytes')
            );
        } catch (\Throwable $error) {
            throw new ProviderException($error->getMessage(), 'OPENMETEO_REQUEST_FAILED');
        }

        $responses = array_is_list($payload) ? $payload : [$payload];
        if (count($responses) !== count($zones)) {
            throw new ProviderException('Open-Meteo returned an unexpected number of locations.', 'OPENMETEO_SCHEMA_INVALID');
        }

        $points = [];
        $expectedTimezone = (string) Config::get('app.timezone', 'Asia/Bangkok');
        $bangkok = new DateTimeZone($expectedTimezone);
        $utc = new DateTimeZone('UTC');
        foreach ($responses as $index => $response) {
            if (!is_array($response) || !isset($response['hourly']) || !is_array($response['hourly'])) {
                throw new ProviderException('Open-Meteo response is missing hourly data.', 'OPENMETEO_SCHEMA_INVALID');
            }
            if (!isset($response['hourly_units']) || !is_array($response['hourly_units'])) {
                throw new ProviderException('Open-Meteo hourly units metadata is missing or malformed.', 'OPENMETEO_UNIT_INVALID');
            }
            if (($response['hourly_units']['precipitation'] ?? null) !== 'mm') {
                throw new ProviderException('Open-Meteo precipitation unit is not mm.', 'OPENMETEO_UNIT_INVALID');
            }
            if (($response['hourly_units']['precipitation_probability'] ?? null) !== '%') {
                throw new ProviderException('Open-Meteo precipitation probability unit is not percent.', 'OPENMETEO_UNIT_INVALID');
            }
            if (!isset($response['timezone']) || !is_string($response['timezone'])) {
                throw new ProviderException('Open-Meteo timezone metadata is missing.', 'OPENMETEO_TIMEZONE_INVALID');
            }
            if ((string) $response['timezone'] !== $expectedTimezone) {
                throw new ProviderException('Open-Meteo returned an unexpected timezone.', 'OPENMETEO_TIMEZONE_INVALID');
            }
            $hourly = $response['hourly'];
            $times = $hourly['time'] ?? [];
            $rain = $hourly['precipitation'] ?? [];
            $probability = $hourly['precipitation_probability'] ?? [];
            $weatherCodes = $hourly['weather_code'] ?? [];
            if (!is_array($times) || !is_array($rain) || $times === [] || count($times) !== count($rain)) {
                throw new ProviderException('Open-Meteo hourly arrays have inconsistent lengths.', 'OPENMETEO_SCHEMA_INVALID');
            }
            foreach (['precipitation_probability' => $probability, 'weather_code' => $weatherCodes] as $field => $values) {
                if (!is_array($values) || ($values !== [] && count($values) !== count($times))) {
                    throw new ProviderException('Open-Meteo ' . $field . ' array has an inconsistent length.', 'OPENMETEO_SCHEMA_INVALID');
                }
            }

            foreach ($times as $pointIndex => $time) {
                $validFrom = $this->parseLocalTime($time, $bangkok);
                $rainValue = $rain[$pointIndex] ?? null;
                if ($rainValue !== null && (!is_numeric($rainValue) || (float) $rainValue < 0)) {
                    throw new ProviderException('Open-Meteo returned invalid precipitation.', 'OPENMETEO_VALUE_INVALID');
                }
                $probabilityValue = $probability[$pointIndex] ?? null;
                if ($probabilityValue !== null
                    && (!is_numeric($probabilityValue) || (float) $probabilityValue < 0 || (float) $probabilityValue > 100)) {
                    throw new ProviderException('Open-Meteo returned invalid precipitation probability.', 'OPENMETEO_VALUE_INVALID');
                }
                $points[] = [
                    'zone_code' => (string) $zones[$index]['code'],
                    'valid_from' => $validFrom->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'valid_to' => $validFrom->modify('+1 hour')->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'rainfall_mm' => $rainValue !== null ? (float) $rainValue : null,
                    'rainfall_probability_pct' => $probabilityValue !== null ? (float) $probabilityValue : null,
                    'weather_code' => isset($weatherCodes[$pointIndex]) ? (string) $weatherCodes[$pointIndex] : null,
                    'source_status' => 'ok',
                    'requested_latitude' => (float) $zones[$index]['latitude'],
                    'requested_longitude' => (float) $zones[$index]['longitude'],
                    'grid_latitude' => is_numeric($response['latitude'] ?? null) ? (float) $response['latitude'] : null,
                    'grid_longitude' => is_numeric($response['longitude'] ?? null) ? (float) $response['longitude'] : null,
                ];
            }
        }

        return ['issued_at' => null, 'points' => $points, 'raw_payload' => $payload];
    }

    private function parseLocalTime(mixed $value, DateTimeZone $timezone): DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            throw new ProviderException('Open-Meteo returned an invalid timestamp.', 'OPENMETEO_SCHEMA_INVALID');
        }
        foreach (['!Y-m-d\\TH:i', '!Y-m-d\\TH:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $expected = str_ends_with($format, ':s') ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
                if ($parsed->format($expected) === $value) {
                    return $parsed;
                }
            }
        }
        throw new ProviderException('Open-Meteo returned an invalid timestamp.', 'OPENMETEO_SCHEMA_INVALID');
    }
}
