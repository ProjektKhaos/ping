<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Config;
use PingFloodWatch\Services\RateLimiter;

final class RateLimiterTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $dsn = getenv('PFW_TEST_DSN') ?: '';
        if ($dsn === '') {
            self::markTestSkipped('PFW_TEST_DSN is not configured.');
        }
        $this->db = new PDO($dsn, getenv('PFW_TEST_DB_USER') ?: '', getenv('PFW_TEST_DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->db->exec('TRUNCATE TABLE api_rate_limits');
        Config::overrideForTests(['security' => ['rate_limit_key' => 'unit-test-rate-secret']]);
    }

    public function testFixedWindowLimitsAndReturnsRetryAfter(): void
    {
        $limiter = new RateLimiter($this->db);
        $now = new DateTimeImmutable('2026-08-21 00:00:00', new DateTimeZone('UTC'));
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $result = $limiter->consume('push-subscribe', '203.0.113.7', 10, 600, $now);
            self::assertTrue($result['allowed']);
            self::assertSame($attempt, $result['count']);
        }
        $blocked = $limiter->consume('push-subscribe', '203.0.113.7', 10, 600, $now);
        self::assertFalse($blocked['allowed']);
        self::assertSame(600, $blocked['retry_after']);
        self::assertSame(11, $blocked['count']);

        $reset = $limiter->consume('push-subscribe', '203.0.113.7', 10, 600, $now->modify('+10 minutes'));
        self::assertTrue($reset['allowed']);
        self::assertSame(1, $reset['count']);
    }

    public function testDatabaseStoresOnlyHmacAndSeparatesRoutesAndAddresses(): void
    {
        $limiter = new RateLimiter($this->db);
        $now = new DateTimeImmutable('2026-08-21 00:00:00', new DateTimeZone('UTC'));
        $limiter->consume('push-subscribe', '203.0.113.7', 10, 600, $now);
        $limiter->consume('push-unsubscribe', '203.0.113.7', 20, 600, $now);
        $limiter->consume('push-subscribe', '203.0.113.8', 10, 600, $now);
        self::assertSame(3, (int) $this->db->query('SELECT COUNT(*) FROM api_rate_limits')->fetchColumn());
        $stored = (string) $this->db->query("SELECT client_hash FROM api_rate_limits WHERE route = 'push-subscribe' ORDER BY client_hash LIMIT 1")->fetchColumn();
        self::assertSame(64, strlen($stored));
        self::assertNotSame('203.0.113.7', $stored);
    }
}
