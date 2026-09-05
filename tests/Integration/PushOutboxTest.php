<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Config;
use PingFloodWatch\Repositories\AlertRepository;
use PingFloodWatch\Repositories\NotificationOutboxRepository;
use PingFloodWatch\Repositories\PushSubscriptionRepository;
use PingFloodWatch\Services\AlertManager;
use PingFloodWatch\Services\NotificationDispatcher;
use PingFloodWatch\Services\PushDeliveryResult;
use PingFloodWatch\Services\PushSenderInterface;

final class QueuedPushSender implements PushSenderInterface
{
    /** @var list<array{subscription:array<string,mixed>,payload:array<string,mixed>,options:array<string,mixed>}> */
    public array $calls = [];

    /** @param list<PushDeliveryResult> $results */
    public function __construct(private array $results) {}

    public function send(array $subscription, string $payload, array $options): PushDeliveryResult
    {
        $this->calls[] = ['subscription' => $subscription, 'payload' => json_decode($payload, true, 16, JSON_THROW_ON_ERROR), 'options' => $options];
        return array_shift($this->results) ?? new PushDeliveryResult(true, false, 201);
    }
}

final class PushOutboxTest extends TestCase
{
    private PDO $db;
    private int $stationId;
    private DateTimeImmutable $start;

    protected function setUp(): void
    {
        $dsn = getenv('PFW_TEST_DSN') ?: '';
        if ($dsn === '') {
            self::markTestSkipped('PFW_TEST_DSN is not configured.');
        }
        $this->db = $this->connection();
        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['push_deliveries', 'notification_outbox', 'push_subscriptions', 'api_rate_limits',
            'alert_stations', 'alert_events', 'alerts', 'station_state', 'measurements', 'station_thresholds', 'stations'] as $table) {
            $this->db->exec('TRUNCATE TABLE ' . $table);
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        $this->db->exec("INSERT INTO stations
            (provider, provider_station_id, provider_station_code, display_name_en, display_name_th, river_name_en, river_name_th,
             latitude, longitude, gauge_zero_msl, enabled, is_primary, sort_order)
            VALUES ('thaiwater','3226','P.1','Nawarat Bridge','สะพานนวรัฐ','Ping River','แม่น้ำปิง',18.786961,99.005089,300.5,1,1,10)");
        $this->stationId = (int) $this->db->lastInsertId();
        $this->start = new DateTimeImmutable('2026-08-21 00:00:00', new DateTimeZone('UTC'));
        Config::overrideForTests(['push' => [
            'claim_timeout_seconds' => 300, 'batch_size' => 20, 'max_attempts' => 5,
            'retry_delays_seconds' => [60, 120, 300, 600], 'active_ttl_seconds' => 1800, 'cleared_ttl_seconds' => 7200,
        ]]);
    }

    public function testAlertEventAndOneLanguageNeutralOutboxRowAreAtomic(): void
    {
        $this->subscribe(1, 'en');
        $this->manager()->reconcile($this->risk('watch'), $this->start);
        self::assertSame(1, $this->tableCount('alerts'));
        self::assertSame(1, $this->tableCount('alert_events'));
        self::assertSame(1, $this->tableCount('notification_outbox'));
        $row = $this->db->query('SELECT * FROM notification_outbox')->fetch();
        $payload = json_decode((string) $row['payload_json'], true, 16, JSON_THROW_ON_ERROR);
        self::assertSame('opened', $payload['event_type']);
        self::assertSame('risk.combined.watch', $payload['message_key']);
        self::assertArrayNotHasKey('title', $payload);
        self::assertArrayNotHasKey('body', $payload);
    }

    public function testOutboxFailureRollsBackAlertAndEvent(): void
    {
        $outbox = new class($this->db) extends NotificationOutboxRepository {
            public function enqueue(int $alertId, int $eventId, string $eventType, string $severity, array $payload, string $now): int
            {
                throw new \RuntimeException('simulated outbox failure');
            }
        };
        try {
            (new AlertManager($this->db, new AlertRepository($this->db), $outbox))->reconcile($this->risk('watch'), $this->start);
            self::fail('The transaction should fail.');
        } catch (\RuntimeException $error) {
            self::assertSame('simulated outbox failure', $error->getMessage());
        }
        self::assertSame(0, $this->tableCount('alerts'));
        self::assertSame(0, $this->tableCount('alert_events'));
        self::assertSame(0, $this->tableCount('notification_outbox'));
    }

    public function testTwoClaimersCannotClaimTheSameOutboxAndAbandonedClaimIsRecovered(): void
    {
        $this->manager()->reconcile($this->risk('watch'), $this->start);
        $first = (new NotificationOutboxRepository($this->db))->claimNext(str_repeat('1', 32), $this->start);
        self::assertNotNull($first);
        $secondConnection = $this->connection();
        self::assertNull((new NotificationOutboxRepository($secondConnection))->claimNext(str_repeat('2', 32), $this->start));
        self::assertSame(1, (new NotificationOutboxRepository($secondConnection))->recoverAbandoned(300, $this->start->modify('+6 minutes')));
        self::assertNotNull((new NotificationOutboxRepository($secondConnection))->claimNext(str_repeat('3', 32), $this->start->modify('+6 minutes')));
    }

    public function testDeliveryFanoutIsDeduplicatedForEventAndSubscription(): void
    {
        $this->subscribe(1, 'en');
        $this->subscribe(2, 'th');
        $this->manager()->reconcile($this->risk('watch'), $this->start);
        $repository = new NotificationOutboxRepository($this->db);
        $item = $repository->claimNext(str_repeat('a', 32), $this->start);
        self::assertNotNull($item);
        $repository->createDeliveries($item, $this->start->format('Y-m-d H:i:s'));
        $repository->createDeliveries($item, $this->start->format('Y-m-d H:i:s'));
        self::assertSame(2, $this->tableCount('push_deliveries'));
    }

    public function testRetryScheduleAndPartialFanoutCompleteWithoutResendingSuccess(): void
    {
        $this->subscribe(1, 'en');
        $this->subscribe(2, 'th');
        $this->manager()->reconcile($this->risk('warning'), $this->start);
        $sender = new QueuedPushSender([
            new PushDeliveryResult(true, false, 201),
            new PushDeliveryResult(false, false, 503, 'PUSH_TEMPORARY'),
            new PushDeliveryResult(true, false, 201),
        ]);
        $dispatcher = new NotificationDispatcher($this->db, $sender);
        $first = $dispatcher->dispatch($this->start);
        self::assertSame(1, $first['delivered']);
        self::assertSame(1, $first['failed']);
        self::assertSame('pending', $this->scalar('SELECT status FROM notification_outbox'));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM push_deliveries WHERE status = 'delivered'"));
        self::assertSame($this->start->modify('+1 minute')->format('Y-m-d H:i:s'), $this->scalar("SELECT available_at FROM push_deliveries WHERE status = 'temporary_failure'"));

        $second = $dispatcher->dispatch($this->start->modify('+1 minute'));
        self::assertSame(1, $second['delivered']);
        self::assertSame('delivered', $this->scalar('SELECT status FROM notification_outbox'));
        self::assertSame(2, (int) $this->scalar("SELECT COUNT(*) FROM push_deliveries WHERE status = 'delivered'"));
        self::assertCount(3, $sender->calls);
        self::assertSame('high', $sender->calls[0]['options']['urgency']);
        self::assertSame(1800, $sender->calls[0]['options']['TTL']);
        self::assertStringStartsWith('pfw-a-', $sender->calls[0]['options']['topic']);
        self::assertSame('en', str_contains($sender->calls[0]['payload']['url'], 'lang=en') ? 'en' : 'other');
        self::assertSame('th', str_contains($sender->calls[1]['payload']['url'], 'lang=th') ? 'th' : 'other');
    }

    public function testExpiredAndSupersededEventsAreNeverSent(): void
    {
        $this->subscribe(1, 'en');
        $manager = $this->manager();
        $manager->reconcile($this->risk('watch'), $this->start);
        $sender = new QueuedPushSender([]);
        $expired = (new NotificationDispatcher($this->db, $sender))->dispatch($this->start->modify('+31 minutes'));
        self::assertSame(1, $expired['expired']);
        self::assertCount(0, $sender->calls);

        $this->resetAlertsOnly();
        $manager = $this->manager();
        $manager->reconcile($this->risk('watch'), $this->start);
        $manager->reconcile($this->risk('warning'), $this->start->modify('+1 minute'));
        $superseded = (new NotificationDispatcher($this->db, $sender))->dispatch($this->start->modify('+1 minute'));
        self::assertSame(1, $superseded['superseded']);
        self::assertSame(1, $superseded['delivered']);
        self::assertCount(1, $sender->calls);
        self::assertSame('warning', $sender->calls[0]['payload']['severity']);
    }

    public function testPermanentGoneResponseDisablesSubscription(): void
    {
        $subscriptionId = $this->subscribe(1, 'en');
        $this->manager()->reconcile($this->risk('watch'), $this->start);
        $sender = new QueuedPushSender([new PushDeliveryResult(false, true, 410, 'PUSH_EXPIRED')]);
        (new NotificationDispatcher($this->db, $sender))->dispatch($this->start);
        self::assertNotNull($this->scalar('SELECT disabled_at FROM push_subscriptions WHERE id = ' . $subscriptionId));
        self::assertSame('permanent_failure', $this->scalar('SELECT status FROM push_deliveries'));
        self::assertSame('failed', $this->scalar('SELECT status FROM notification_outbox'));
    }

    public function testTemporaryFailuresStopAfterFiveTotalAttemptsWithRequiredSchedule(): void
    {
        $this->subscribe(1, 'en');
        $this->manager()->reconcile($this->risk('watch'), $this->start);
        $sender = new QueuedPushSender(array_fill(0, 5, new PushDeliveryResult(false, false, 503, 'PUSH_TEMPORARY')));
        $dispatcher = new NotificationDispatcher($this->db, $sender);
        foreach ([0, 1, 3, 8, 18] as $minutes) {
            $dispatcher->dispatch($this->start->modify('+' . $minutes . ' minutes'));
        }
        self::assertCount(5, $sender->calls);
        self::assertSame(5, (int) $this->scalar('SELECT attempt_count FROM push_deliveries'));
        self::assertSame('failed', $this->scalar('SELECT status FROM push_deliveries'));
        self::assertSame('failed', $this->scalar('SELECT status FROM notification_outbox'));
    }

    public function testOldClearIsSupersededWhenANewIncidentHasOpened(): void
    {
        $this->subscribe(1, 'en');
        $manager = $this->manager();
        $manager->reconcile($this->risk('watch'), $this->start);
        $manager->reconcile($this->risk('normal'), $this->start->modify('+1 minute'));
        $manager->reconcile($this->risk('normal'), $this->start->modify('+22 minutes'));
        $manager->reconcile($this->risk('watch'), $this->start->modify('+23 minutes'));
        $sender = new QueuedPushSender([]);
        $result = (new NotificationDispatcher($this->db, $sender))->dispatch($this->start->modify('+23 minutes'));
        self::assertSame(2, $result['superseded']);
        self::assertSame(1, $result['delivered']);
        self::assertCount(1, $sender->calls);
        self::assertSame('opened', $sender->calls[0]['payload']['event_type']);
    }

    public function testOnlyOpenedEscalatedAndClearedCreateOutboxRows(): void
    {
        $manager = $this->manager();
        $manager->reconcile($this->risk('warning'), $this->start);
        $manager->reconcile($this->risk('warning'), $this->start->modify('+1 minute'));
        $manager->reconcile($this->risk('watch'), $this->start->modify('+2 minutes'));
        $manager->reconcile($this->risk('watch'), $this->start->modify('+23 minutes'));
        self::assertSame(1, $this->tableCount('notification_outbox'));
        $manager->reconcile($this->risk('normal'), $this->start->modify('+24 minutes'));
        $manager->reconcile($this->risk('normal'), $this->start->modify('+45 minutes'));
        self::assertSame(2, $this->tableCount('notification_outbox'));
        self::assertSame(['opened', 'cleared'], $this->db->query('SELECT event_type FROM notification_outbox ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    private function manager(): AlertManager
    {
        return new AlertManager($this->db, new AlertRepository($this->db));
    }

    private function subscribe(int $suffix, string $language): int
    {
        return (new PushSubscriptionRepository($this->db))->upsert(
            'https://push.example.test/subscription/' . $suffix, 'public-key-' . $suffix, 'auth-' . $suffix,
            'aes128gcm', $language, $language === 'th' ? 'android' : 'desktop', $this->start->format('Y-m-d H:i:s')
        );
    }

    /** @return array<string,mixed> */
    private function risk(string $severity): array
    {
        return ['severity' => $severity, 'reason_codes' => ['TEST_' . strtoupper($severity)],
            'message_key' => 'risk.combined.' . $severity, 'context' => ['station_ids' => [$this->stationId]]];
    }

    private function tableCount(string $table): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function scalar(string $sql): mixed
    {
        return $this->db->query($sql)->fetchColumn();
    }

    private function connection(): PDO
    {
        return new PDO((string) getenv('PFW_TEST_DSN'), getenv('PFW_TEST_DB_USER') ?: '', getenv('PFW_TEST_DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function resetAlertsOnly(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['push_deliveries', 'notification_outbox', 'alert_stations', 'alert_events', 'alerts'] as $table) {
            $this->db->exec('TRUNCATE TABLE ' . $table);
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
