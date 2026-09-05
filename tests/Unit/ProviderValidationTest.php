<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PingFloodWatch\HttpClient;
use PingFloodWatch\Providers\CmfloodProvider;
use PingFloodWatch\Providers\OpenMeteoProvider;
use PingFloodWatch\Providers\ProviderException;
use PingFloodWatch\Providers\ThaiWaterProvider;

final class ProviderValidationTest extends TestCase
{
    public function testThaiWaterRejectsMalformedSchema(): void
    {
        $client = new class extends HttpClient {
            public function getJson(string $url, int $connectTimeout, int $timeout, int $maxBytes): array
            {
                return ['unexpected' => true];
            }
        };
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('missing waterlevel_data.data');
        (new ThaiWaterProvider($client))->fetchLatestMeasurements([]);
    }

    public function testOpenMeteoRejectsInconsistentUnitsAndArrays(): void
    {
        $client = new class extends HttpClient {
            public function getJson(string $url, int $connectTimeout, int $timeout, int $maxBytes): array
            {
                return ['hourly' => ['time' => ['2026-08-21T07:00'], 'precipitation' => ['not-a-number']]];
            }
        };
        try {
            (new OpenMeteoProvider($client))->fetchForecast([['code' => 'P.67', 'latitude' => 19.0, 'longitude' => 99.0]]);
            self::fail('Malformed precipitation must be rejected.');
        } catch (ProviderException $error) {
            self::assertSame('OPENMETEO_UNIT_INVALID', $error->providerCode);
        }
    }

    public function testOpenMeteoValidatesMetadataAndNormalizesBangkokTime(): void
    {
        $forecast = (new OpenMeteoProvider($this->client($this->validWeatherPayload())))
            ->fetchForecast([['code' => 'P.67', 'latitude' => 19.0, 'longitude' => 99.0]]);
        self::assertCount(2, $forecast['points']);
        self::assertSame('2026-08-21 00:00:00', $forecast['points'][0]['valid_from']);
        self::assertSame(1.5, $forecast['points'][0]['rainfall_mm']);
        self::assertSame(80.0, $forecast['points'][0]['rainfall_probability_pct']);
    }

    public function testOpenMeteoRejectsContradictoryTimezone(): void
    {
        $payload = $this->validWeatherPayload();
        $payload['timezone'] = 'UTC';
        $this->expectProviderCode('OPENMETEO_TIMEZONE_INVALID', $payload);
    }

    public function testOpenMeteoRejectsContradictoryUnits(): void
    {
        $payload = $this->validWeatherPayload();
        $payload['hourly_units']['precipitation'] = 'inch';
        $this->expectProviderCode('OPENMETEO_UNIT_INVALID', $payload);
    }

    public function testOpenMeteoRejectsArrayLengthMismatch(): void
    {
        $payload = $this->validWeatherPayload();
        array_pop($payload['hourly']['precipitation_probability']);
        $this->expectProviderCode('OPENMETEO_SCHEMA_INVALID', $payload);
    }

    public function testOpenMeteoRejectsInvalidDatesAndValues(): void
    {
        $invalidDate = $this->validWeatherPayload();
        $invalidDate['hourly']['time'][0] = '21/08/2026';
        $this->expectProviderCode('OPENMETEO_SCHEMA_INVALID', $invalidDate);

        $negativeRain = $this->validWeatherPayload();
        $negativeRain['hourly']['precipitation'][0] = -0.1;
        $this->expectProviderCode('OPENMETEO_VALUE_INVALID', $negativeRain);

        $invalidProbability = $this->validWeatherPayload();
        $invalidProbability['hourly']['precipitation_probability'][0] = 101;
        $this->expectProviderCode('OPENMETEO_VALUE_INVALID', $invalidProbability);
    }

    public function testCmfloodNormalizesBangkokClockAndGaugeMetadata(): void
    {
        $sourceTime = (new \DateTimeImmutable('-1 hour', new \DateTimeZone('Asia/Bangkok')))->format('Y-m-d\TH:i:s') . '+00:00';
        $client = new class([['water_level' => 2.63, 'recorded_at' => $sourceTime]]) extends HttpClient {
            public function __construct(private readonly array $payload) {}
            public function getJsonWithHeaders(string $url, int $connectTimeout, int $timeout, int $maxBytes, array $headers): array
            {
                return $this->payload;
            }
        };
        $station = [
            'provider_station_id' => 'disaster:CMI02', 'provider_station_code' => 'CMI02',
            'gauge_zero_msl' => 300.231, 'bank_level_msl' => 306.099,
        ];
        $points = (new CmfloodProvider($client))->fetchLatestMeasurements([$station]);
        self::assertCount(1, $points);
        self::assertSame('CMI02', $points[0]['station_code']);
        self::assertSame(2.63, $points[0]['water_level_gauge_m']);
        self::assertEqualsWithDelta(302.861, $points[0]['water_level_msl_m'], 0.00001);
        self::assertSame(
            \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', str_replace('T', ' ', substr($sourceTime, 0, 19)), new \DateTimeZone('Asia/Bangkok'))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $points[0]['measured_at']
        );
    }

    public function testCmfloodRejectsStationSpecificInvalidReading(): void
    {
        $sourceTime = (new \DateTimeImmutable('-1 hour', new \DateTimeZone('Asia/Bangkok')))->format('Y-m-d\TH:i:s') . '+00:00';
        $client = new class([['water_level' => 1.99, 'recorded_at' => $sourceTime]]) extends HttpClient {
            public function __construct(private readonly array $payload) {}
            public function getJsonWithHeaders(string $url, int $connectTimeout, int $timeout, int $maxBytes, array $headers): array
            {
                return $this->payload;
            }
        };
        $points = (new CmfloodProvider($client))->fetchLatestMeasurements([[
            'provider_station_id' => 'disaster:CMI02', 'provider_station_code' => 'CMI02',
            'gauge_zero_msl' => 300.231, 'bank_level_msl' => 306.099,
        ]]);
        self::assertSame([], $points);
    }

    /** @param array<string,mixed> $payload */
    private function expectProviderCode(string $expected, array $payload): void
    {
        try {
            (new OpenMeteoProvider($this->client($payload)))
                ->fetchForecast([['code' => 'P.67', 'latitude' => 19.0, 'longitude' => 99.0]]);
            self::fail('Provider payload should have been rejected.');
        } catch (ProviderException $error) {
            self::assertSame($expected, $error->providerCode);
        }
    }

    /** @param array<string,mixed> $payload */
    private function client(array $payload): HttpClient
    {
        return new class($payload) extends HttpClient {
            /** @param array<string,mixed> $payload */
            public function __construct(private readonly array $payload) {}
            public function getJson(string $url, int $connectTimeout, int $timeout, int $maxBytes): array
            {
                return $this->payload;
            }
        };
    }

    /** @return array<string,mixed> */
    private function validWeatherPayload(): array
    {
        return [
            'latitude' => 19.0, 'longitude' => 99.0, 'timezone' => 'Asia/Bangkok',
            'hourly_units' => ['time' => 'iso8601', 'precipitation' => 'mm', 'precipitation_probability' => '%', 'weather_code' => 'wmo code'],
            'hourly' => [
                'time' => ['2026-08-21T07:00', '2026-08-21T08:00'],
                'precipitation' => [1.5, 0], 'precipitation_probability' => [80, 20], 'weather_code' => [61, 3],
            ],
        ];
    }
}
