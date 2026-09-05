<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LanguageUrlTest extends TestCase
{
    public function testLanguageSwitchPreservesAllQueryParameters(): void
    {
        $_SERVER['REQUEST_URI'] = '/station.php?code=P.1&period=48h&lang=en';
        self::assertSame('/station.php?code=P.1&period=48h&lang=th', language_url('th'));
    }
}
