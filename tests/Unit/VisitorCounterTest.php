<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Unit;

use PingFloodWatch\Config;
use PingFloodWatch\Services\VisitorCounter;
use PHPUnit\Framework\TestCase;

final class VisitorCounterTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'pfw-visitor-') ?: throw new \RuntimeException('Unable to create test log.');
        Config::overrideForTests([
            'visitor_counter' => ['enabled' => true, 'log_file' => $this->logFile, 'hmac_key' => 'test-only-counter-key'],
        ]);
    }

    protected function tearDown(): void
    {
        Config::overrideForTests(['visitor_counter' => ['enabled' => false]]);
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testItCountsAVisitWithoutPersistingRawRequestIdentifiers(): void
    {
        $server = [
            'REMOTE_ADDR' => '203.0.113.55',
            'SCRIPT_NAME' => '/station.php',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile Safari/604.1',
            'HTTP_REFERER' => 'https://example.org/a/private/path?secret=yes',
            'HTTP_SEC_GPC' => '1',
        ];

        self::assertTrue(VisitorCounter::recordRequest($server, ['code' => 'P.1', 'secret' => 'not-logged'], 'en'));
        $contents = (string) file_get_contents($this->logFile);

        self::assertStringContainsString('`000000000001`', $contents);
        self::assertStringContainsString('/station.php (P.1)', $contents);
        self::assertStringContainsString('| Mobile | iOS | Safari | External: example.org | GPC |', $contents);
        self::assertStringNotContainsString('203.0.113.55', $contents);
        self::assertStringNotContainsString('private/path', $contents);
        self::assertStringNotContainsString('secret', $contents);
        self::assertStringNotContainsString('Mozilla/5.0', $contents);
    }

    public function testBotTrafficIsNotCounted(): void
    {
        self::assertFalse(VisitorCounter::recordRequest([
            'REMOTE_ADDR' => '203.0.113.56',
            'SCRIPT_NAME' => '/index.php',
            'HTTP_USER_AGENT' => 'ExampleBot/1.0',
        ], [], 'en'));
        self::assertSame('', file_get_contents($this->logFile));
    }
}
