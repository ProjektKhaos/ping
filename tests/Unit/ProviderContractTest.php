<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Providers\MockWaterProvider;
use PingFloodWatch\Providers\MockWeatherProvider;

final class ProviderContractTest extends TestCase
{
    public function testMockWaterContractAndUnits(): void
    {
        $station = ['provider_station_code' => 'P.1', 'gauge_zero_msl' => 300.5];
        $point = (new MockWaterProvider())->fetchLatestMeasurements([$station])[0];
        foreach (['station_code', 'measured_at', 'source_measured_at', 'water_level_gauge_m', 'water_level_msl_m', 'raw_payload'] as $field) {
            self::assertArrayHasKey($field, $point);
        }
        self::assertEqualsWithDelta(300.5 + $point['water_level_gauge_m'], $point['water_level_msl_m'], 0.0001);
    }

    public function testMockWeatherContractHas48HoursPerZone(): void
    {
        $zone = ['code' => 'P.67', 'latitude' => 19.00985, 'longitude' => 98.95974];
        $forecast = (new MockWeatherProvider())->fetchForecast([$zone]);
        self::assertCount(48, $forecast['points']);
        self::assertSame('P.67', $forecast['points'][0]['zone_code']);
        self::assertGreaterThan((new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-2 hours')->getTimestamp(), strtotime($forecast['points'][0]['valid_from'] . ' UTC'));
    }
}
