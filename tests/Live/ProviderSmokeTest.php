<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Live;

use PHPUnit\Framework\TestCase;
use PingFloodWatch\Providers\CmfloodProvider;
use PingFloodWatch\Providers\OpenMeteoProvider;
use PingFloodWatch\Providers\ThaiWaterProvider;

final class ProviderSmokeTest extends TestCase
{
    public function testThaiWaterLiveContract(): void
    {
        $stations = [
            ['provider_station_code' => 'P.67', 'provider_station_id' => '3247', 'gauge_zero_msl' => 315.929993],
            ['provider_station_code' => 'P.103', 'provider_station_id' => '504679', 'gauge_zero_msl' => 300.890015],
            ['provider_station_code' => 'P.1', 'provider_station_id' => '3226', 'gauge_zero_msl' => 300.5],
        ];
        $points = (new ThaiWaterProvider())->fetchLatestMeasurements($stations);
        self::assertCount(3, $points);
        self::assertEqualsCanonicalizing(['P.67', 'P.103', 'P.1'], array_column($points, 'station_code'));
        foreach ($points as $point) {
            self::assertIsFloat($point['water_level_msl_m']);
            self::assertIsFloat($point['water_level_gauge_m']);
        }
    }

    public function testOpenMeteoLiveContract(): void
    {
        $zones = [
            ['code' => 'P.67', 'latitude' => 19.00985, 'longitude' => 98.95974],
            ['code' => 'P.103', 'latitude' => 18.86651, 'longitude' => 98.978188],
        ];
        $forecast = (new OpenMeteoProvider())->fetchForecast($zones);
        self::assertCount(96, $forecast['points']);
        foreach ($forecast['points'] as $point) {
            self::assertContains($point['zone_code'], ['P.67', 'P.103']);
            self::assertTrue($point['rainfall_mm'] === null || is_float($point['rainfall_mm']));
        }
    }

    public function testCmfloodLiveContract(): void
    {
        $stations = [
            ['provider_station_code' => 'CMI01', 'provider_station_id' => 'disaster:CMI01', 'gauge_zero_msl' => 300.3, 'bank_level_msl' => 308.831],
            ['provider_station_code' => 'CMI02', 'provider_station_id' => 'disaster:CMI02', 'gauge_zero_msl' => 300.231, 'bank_level_msl' => 306.099],
            ['provider_station_code' => 'FBP.2', 'provider_station_id' => 'water_levels:22', 'gauge_zero_msl' => 294.2, 'bank_level_msl' => 303.135],
            ['provider_station_code' => 'CMI03', 'provider_station_id' => 'disaster:CMI03', 'gauge_zero_msl' => 299.62, 'bank_level_msl' => 303.235],
            ['provider_station_code' => 'FBP.3', 'provider_station_id' => 'water_levels:21', 'gauge_zero_msl' => 292.93, 'bank_level_msl' => 302.886],
            ['provider_station_code' => 'P.104', 'provider_station_id' => 'flagship:ST.16', 'gauge_zero_msl' => 294.05, 'bank_level_msl' => 301.118],
        ];
        $points = (new CmfloodProvider())->fetchLatestMeasurements($stations);
        self::assertCount(6, $points);
        self::assertEqualsCanonicalizing(array_column($stations, 'provider_station_code'), array_column($points, 'station_code'));
        foreach ($points as $point) {
            self::assertIsFloat($point['water_level_gauge_m']);
            self::assertIsFloat($point['water_level_msl_m']);
        }
    }
}
