<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Services\AdvisoryEngine;

final class AdvisoryEngineTest extends TestCase
{
    /** @return iterable<string,array{string,string,string}> */
    public static function matrix(): iterable
    {
        yield ['normal', 'low', 'normal'];
        yield ['normal', 'moderate', 'normal'];
        yield ['normal', 'high', 'watch'];
        yield ['normal', 'very_high', 'warning'];
        yield ['watch', 'low', 'watch'];
        yield ['watch', 'high', 'warning'];
        yield ['warning', 'high', 'critical'];
        yield ['critical', 'low', 'critical'];
        yield ['critical', 'unknown', 'critical'];
        yield ['unknown', 'low', 'unknown'];
        yield ['normal', 'unknown', 'unknown'];
    }

    #[DataProvider('matrix')]
    public function testCombinedMatrix(string $river, string $weather, string $expected): void
    {
        $base = ['context' => ['station_ids' => []], 'reason_codes' => []];
        $result = (new AdvisoryEngine())->evaluate($base + ['severity' => $river], $base + ['severity' => $weather]);
        self::assertSame($expected, $result['severity']);
    }
}
