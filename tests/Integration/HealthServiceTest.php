<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Config;
use PingFloodWatch\Services\HealthService;

final class HealthServiceTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $now;

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
        $this->db->exec('TRUNCATE TABLE collector_runs');
        $this->db->exec('TRUNCATE TABLE provider_health');
        $this->now = new DateTimeImmutable('2026-08-21 12:00:00', new DateTimeZone('UTC'));
        Config::overrideForTests([
            'providers' => ['water' => 'thaiwater', 'weather' => 'openmeteo'],
            'health' => [
                'water_collector_max_age_minutes' => 15, 'weather_collector_max_age_minutes' => 45,
                'water_collector_max_runtime_minutes' => 3, 'weather_collector_max_runtime_minutes' => 5,
            ],
        ]);
    }

    public function testMissingProviderAndCollectorAreExplicitlyDegraded(): void
    {
        $health = (new HealthService($this->db, $this->now))->evaluate();
        self::assertSame('degraded', $health['status']);
        self::assertSame(['missing', 'missing'], array_column($health['providers'], 'status'));
        self::assertSame(['missing', 'missing'], array_column($health['collectors'], 'status'));
    }

    public function testProviderNameAndAgeComeFromConfigAndFailureOverridesFreshSuccess(): void
    {
        Config::overrideForTests(['providers' => ['water' => 'configured-water'], 'health' => ['water_collector_max_age_minutes' => 7]]);
        try {
            $this->provider('configured-water', 'water', $this->now->modify('-6 minutes'), 0);
            $service = new HealthService($this->db, $this->now);
            $provider = $service->provider('water');
            self::assertSame('configured-water', $provider['name']);
            self::assertSame(7, $provider['max_age_minutes']);
            self::assertSame('ok', $provider['status']);

            $this->db->exec("UPDATE provider_health SET consecutive_failures = 1, last_failure_at = '2026-08-21 11:59:00', last_error_code = 'TEST_FAILURE'");
            self::assertSame('failed', $service->provider('water')['status']);
            $this->db->exec("UPDATE provider_health SET consecutive_failures = 0, last_success_at = '2026-08-21 11:52:00'");
            self::assertSame('stale', $service->provider('water')['status']);
        } finally {
            Config::overrideForTests(['providers' => ['water' => 'thaiwater'], 'health' => ['water_collector_max_age_minutes' => 15]]);
        }
    }

    public function testCompletedCollectorCanBeOkStaleOrFailed(): void
    {
        $service = new HealthService($this->db, $this->now);
        $this->insertRun('water', 'success', $this->now->modify('-2 minutes'), $this->now->modify('-1 minute'));
        self::assertSame('ok', $service->collector('water')['status']);

        $this->db->exec('TRUNCATE TABLE collector_runs');
        $this->insertRun('water', 'success', $this->now->modify('-20 minutes'), $this->now->modify('-16 minutes'));
        self::assertSame('stale', $service->collector('water')['status']);

        $this->insertRun('water', 'failed', $this->now->modify('-1 minute'), $this->now, 'TEST_FAILURE');
        self::assertSame('failed', $service->collector('water')['status']);
    }

    public function testRunningCollectorRequiresRecentPreviousSuccessAndHasHungLimit(): void
    {
        $service = new HealthService($this->db, $this->now);
        $this->insertRun('water', 'success', $this->now->modify('-6 minutes'), $this->now->modify('-5 minutes'));
        $this->insertRun('water', 'running', $this->now->modify('-2 minutes'), null);
        $running = $service->collector('water');
        self::assertSame('ok', $running['status']);
        self::assertSame(2.0, $running['runtime_minutes']);
        self::assertSame(3, $running['max_runtime_minutes']);

        $this->insertRun('water', 'running', $this->now->modify('-4 minutes'), null);
        self::assertSame('hung', $service->collector('water')['status']);

        $this->db->exec('TRUNCATE TABLE collector_runs');
        $this->insertRun('weather', 'running', $this->now->modify('-1 minute'), null);
        self::assertSame('missing', $service->collector('weather')['status']);
    }

    private function provider(string $name, string $type, DateTimeImmutable $success, int $failures): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO provider_health (provider, provider_type, last_success_at, consecutive_failures) VALUES (?,?,?,?)'
        );
        $statement->execute([$name, $type, $success->format('Y-m-d H:i:s'), $failures]);
    }

    private function insertRun(string $collector, string $status, DateTimeImmutable $started, ?DateTimeImmutable $finished, ?string $error = null): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO collector_runs (collector,status,started_at,finished_at,error_code) VALUES (?,?,?,?,?)'
        );
        $statement->execute([$collector, $status, $started->format('Y-m-d H:i:s'), $finished?->format('Y-m-d H:i:s'), $error]);
    }
}
