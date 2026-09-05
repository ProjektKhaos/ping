<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Services\WeatherRiskEngine;

final class WeatherRiskEngineTest extends TestCase
{
    /** @return iterable<string,array{float,float,string}> */
    public static function bands(): iterable
    {
        yield 'low' => [10.0, 5.0, 'low'];
        yield 'moderate 24h' => [10.1, 0.0, 'moderate'];
        yield 'high 24h' => [35.1, 0.0, 'high'];
        yield 'very high 24h' => [90.1, 0.0, 'very_high'];
        yield 'moderate hourly' => [0.0, 5.1, 'moderate'];
        yield 'high hourly' => [0.0, 25.1, 'high'];
        yield 'very high hourly' => [0.0, 50.1, 'very_high'];
    }

    #[DataProvider('bands')]
    public function testTmdBands(float $rain24, float $hourly, string $expected): void
    {
        $risk = (new WeatherRiskEngine())->evaluate([$this->state('P.67', $rain24, $hourly), $this->state('P.103', $rain24, $hourly)], ['consecutive_failures' => 0]);
        self::assertSame($expected, $risk['severity']);
    }

    public function testStaleAndProviderFailureAreUnknown(): void
    {
        $state = $this->state('P.67', 0, 0);
        $state['freshness_status'] = 'stale';
        self::assertSame('unknown', (new WeatherRiskEngine())->evaluate([$state], ['consecutive_failures' => 0])['severity']);
        self::assertSame('unknown', (new WeatherRiskEngine())->evaluate([$this->state('P.67', 0, 0)], ['consecutive_failures' => 1])['severity']);
    }

    /** @return array<string,mixed> */
    private function state(string $code, float $rain24, float $hourly): array
    {
        return ['code' => $code, 'affects_risk' => 1, 'freshness_status' => 'current', 'rain_24h_mm' => $rain24,
            'max_hourly_rain_mm' => $hourly, 'max_probability_pct' => 75.0];
    }
}
