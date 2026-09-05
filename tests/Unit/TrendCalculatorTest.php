<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PingFloodWatch\Services\TrendCalculator;

final class TrendCalculatorTest extends TestCase
{
    public function testFindsNearestPointWithinTolerance(): void
    {
        $points = [
            ['measured_at' => '2026-08-21 12:00:00', 'value' => 2.10],
            ['measured_at' => '2026-08-21 11:07:00', 'value' => 1.95],
            ['measured_at' => '2026-08-21 10:00:00', 'value' => 1.70],
        ];
        self::assertEqualsWithDelta(0.15, TrendCalculator::change($points, 1, 10), 0.0001);
    }

    public function testReturnsNullOutsideToleranceOrWithNullLatest(): void
    {
        self::assertNull(TrendCalculator::change([
            ['measured_at' => '2026-08-21 12:00:00', 'value' => 2.1],
            ['measured_at' => '2026-08-21 10:30:00', 'value' => 1.9],
        ], 1, 10));
        self::assertNull(TrendCalculator::change([['measured_at' => '2026-08-21 12:00:00', 'value' => null]], 1));
    }

    public function testAllRequiredHorizons(): void
    {
        $latest = new \DateTimeImmutable('2026-08-21 12:00:00 UTC');
        $points = [['measured_at' => $latest->format('Y-m-d H:i:s'), 'value' => 2.5]];
        foreach ([1, 3, 6, 12, 24] as $hours) {
            $points[] = ['measured_at' => $latest->modify('-' . $hours . ' hours')->format('Y-m-d H:i:s'), 'value' => 2.5 - ($hours / 100)];
        }
        foreach ([1, 3, 6, 12, 24] as $hours) {
            self::assertEqualsWithDelta($hours / 100, TrendCalculator::change($points, $hours), 0.0001);
        }
    }
}
