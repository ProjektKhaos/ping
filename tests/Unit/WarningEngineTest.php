<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Services\WarningEngine;

final class WarningEngineTest extends TestCase
{
    /** @return iterable<string,array{float,string,string}> */
    public static function levels(): iterable
    {
        yield 'normal' => [2.0, 'normal', 'normal'];
        yield 'high capacity status' => [2.0, 'high', 'watch'];
        yield 'warning level' => [3.7, 'normal', 'warning'];
        yield 'critical level' => [4.2, 'normal', 'critical'];
    }

    #[DataProvider('levels')]
    public function testPrimaryRiskLevels(float $level, string $situation, string $expected): void
    {
        $risk = (new WarningEngine())->evaluate([$this->primary($level, $situation)], $this->thresholds(), $this->health());
        self::assertSame($expected, $risk['severity']);
        self::assertNotEmpty($risk['reason_codes']);
    }

    public function testStaleOrMissingDataIsUnknown(): void
    {
        $stale = $this->primary(1.0, 'normal');
        $stale['freshness_status'] = 'stale';
        self::assertSame('unknown', (new WarningEngine())->evaluate([$stale], $this->thresholds(), $this->health())['severity']);
        $missing = $this->primary(1.0, 'normal');
        $missing['water_level_gauge_m'] = null;
        self::assertSame('unknown', (new WarningEngine())->evaluate([$missing], $this->thresholds(), $this->health())['severity']);
    }

    public function testProviderFailureCannotProduceNormal(): void
    {
        $risk = (new WarningEngine())->evaluate([$this->primary(1.0, 'normal')], $this->thresholds(), ['consecutive_failures' => 1]);
        self::assertSame('unknown', $risk['severity']);
    }

    public function testConfiguredRiseRateRulesAreSupported(): void
    {
        $primary = $this->primary(2.0, 'normal');
        $primary['rate_1h_m_per_hour'] = 0.31;
        $risk = (new WarningEngine(['watch' => 10, 'warning' => 20, 'critical' => 30]))
            ->evaluate([$primary], $this->thresholds(), $this->health());
        self::assertSame('critical', $risk['severity']);
        self::assertSame(['RIVER_RISE_RATE_CRITICAL'], $risk['reason_codes']);
    }

    public function testUpstreamHighWaterCreatesWatch(): void
    {
        $upstream = ['id' => 1, 'provider_station_code' => 'P.67', 'is_primary' => 0,
            'water_level_gauge_m' => 1.0, 'freshness_status' => 'live', 'source_situation' => 'high'];
        $risk = (new WarningEngine())->evaluate([$this->primary(2.0, 'normal'), $upstream], $this->thresholds(), $this->health());
        self::assertSame('watch', $risk['severity']);
        self::assertContains('P.67', $risk['context']['stations']);
    }

    public function testDownstreamHighWaterDoesNotChangeRiverRisk(): void
    {
        $downstream = ['id' => 6, 'provider_station_code' => 'FBP.2', 'is_primary' => 0,
            'river_role' => 'downstream', 'water_level_gauge_m' => 8.0,
            'freshness_status' => 'live', 'source_situation' => 'overflow'];
        $risk = (new WarningEngine())->evaluate([$this->primary(2.0, 'normal'), $downstream], $this->thresholds(), $this->health());
        self::assertSame('normal', $risk['severity']);
        self::assertNotContains('FBP.2', $risk['context']['stations']);
    }

    public function testMissingThresholdDoesNotInventAWarning(): void
    {
        $risk = (new WarningEngine())->evaluate([$this->primary(3.9, 'normal')], [], $this->health());
        self::assertSame('normal', $risk['severity']);
    }

    public function testUpstreamRiseNeedsConfiguredThresholds(): void
    {
        $upstream = $this->upstream(0.90);
        $risk = (new WarningEngine())->evaluate([$this->primary(2.0, 'normal'), $upstream], $this->thresholds(), $this->health());
        self::assertSame('normal', $risk['severity']);
        self::assertSame([], $risk['context']['upstream_trends']);
    }

    public function testConfiguredUpstreamRiseCanWatchOrWarnButNeverCreatesCritical(): void
    {
        $watch = (new WarningEngine(null, ['watch' => 20, 'warning' => 50]))
            ->evaluate([$this->primary(2.0, 'normal'), $this->upstream(0.25)], $this->thresholds(), $this->health());
        self::assertSame('watch', $watch['severity']);
        self::assertContains('RIVER_UPSTREAM_RISE_WATCH', $watch['reason_codes']);

        $warning = (new WarningEngine(null, ['watch' => 20, 'warning' => 50]))
            ->evaluate([$this->primary(2.0, 'normal'), $this->upstream(5.0)], $this->thresholds(), $this->health());
        self::assertSame('warning', $warning['severity']);
        self::assertContains('RIVER_UPSTREAM_RISE_WARNING', $warning['reason_codes']);
    }

    /** @return array<string,mixed> */
    private function primary(float $level, string $situation): array
    {
        return ['id' => 3, 'provider_station_code' => 'P.1', 'is_primary' => 1, 'water_level_gauge_m' => $level,
            'freshness_status' => 'live', 'source_situation' => $situation, 'rate_1h_m_per_hour' => null];
    }

    /** @return array<string,mixed> */
    private function upstream(float $rate): array
    {
        return ['id' => 1, 'provider_station_code' => 'P.67', 'is_primary' => 0,
            'water_level_gauge_m' => 1.0, 'freshness_status' => 'live', 'source_situation' => 'normal',
            'rate_1h_m_per_hour' => $rate, 'change_1h_m' => $rate];
    }

    /** @return array<string,array<string,float>> */
    private function thresholds(): array
    {
        return ['warning' => ['value_m' => 3.7], 'critical' => ['value_m' => 4.2]];
    }

    /** @return array{consecutive_failures:int} */
    private function health(): array
    {
        return ['consecutive_failures' => 0];
    }
}
