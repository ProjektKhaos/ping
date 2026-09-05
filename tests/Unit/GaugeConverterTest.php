<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PingFloodWatch\Services\GaugeConverter;

final class GaugeConverterTest extends TestCase
{
    public function testConvertsMslUsingVerifiedOffset(): void
    {
        self::assertEqualsWithDelta(3.7, GaugeConverter::fromMsl(304.2, 300.5), 0.000001);
        self::assertEqualsWithDelta(1.25, GaugeConverter::fromMsl(317.179993, 315.929993), 0.000001);
    }

    public function testDoesNotInventGaugeWithoutOffset(): void
    {
        self::assertNull(GaugeConverter::fromMsl(304.2, null));
        self::assertNull(GaugeConverter::fromMsl(null, 300.5));
    }
}
