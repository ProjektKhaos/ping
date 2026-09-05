<?php

declare(strict_types=1);

namespace PingFloodWatch\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use PingFloodWatch\Repositories\AlertRepository;
use PingFloodWatch\Repositories\MeasurementRepository;
use PingFloodWatch\Repositories\ProviderHealthRepository;
use PingFloodWatch\Repositories\ForecastRepository;
use PingFloodWatch\Providers\MockWeatherProvider;
use PingFloodWatch\Services\AlertManager;
use PingFloodWatch\Services\DashboardService;
use PingFloodWatch\Config;

final class DatabaseLifecycleTest extends TestCase
{
    private PDO $db;
    private int $stationId;

    protected function setUp(): void
    {
        $dsn = getenv('PFW_TEST_DSN') ?: '';
        if ($dsn === '') {
            self::markTestSkipped('PFW_TEST_DSN is not configured.');
        }
        $this->db = new PDO($dsn, getenv('PFW_TEST_DB_USER') ?: '', getenv('PFW_TEST_DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['push_deliveries', 'notification_outbox', 'push_subscriptions', 'api_rate_limits',
            'alert_stations', 'alert_events', 'alerts', 'measurement_revisions', 'station_state', 'measurements',
            'station_thresholds', 'stations', 'weather_state', 'weather_forecast_points', 'forecast_runs', 'forecast_zones', 'provider_health'] as $table) {
            $this->db->exec('TRUNCATE TABLE ' . $table);
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        $this->db->exec("INSERT INTO stations
            (provider, provider_station_id, provider_station_code, display_name_en, display_name_th, river_name_en, river_name_th,
             latitude, longitude, gauge_zero_msl, enabled, is_primary, sort_order)
            VALUES ('thaiwater','3226','P.1','Nawarat Bridge','สะพานนวรัฐ','Ping River','แม่น้ำปิง',18.786961,99.005089,300.5,1,1,10)");
        $this->stationId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO forecast_zones
            (code,display_name_en,display_name_th,latitude,longitude,zone_type,affects_risk,enabled,sort_order)
            VALUES ('P.67','Mae Tae','แม่แต',19.00985,98.95974,'upstream',1,1,10)");
    }

    public function testMeasurementDedupAndRevisionHistory(): void
    {
        $repository = new MeasurementRepository($this->db);
        $point = $this->point(1.25);
        self::assertSame('inserted', $repository->store($point, $this->stationId));
        self::assertSame('unchanged', $repository->store($point, $this->stationId));
        $revised = $this->point(1.31);
        self::assertSame('updated', $repository->store($revised, $this->stationId));
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM measurements')->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM measurement_revisions')->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT revision_count FROM measurements')->fetchColumn());
    }

    public function testProviderHealthTransitions(): void
    {
        $repository = new ProviderHealthRepository($this->db);
        $repository->failure('thaiwater', 'water', 'UPSTREAM', 'Sanitized test failure');
        $repository->failure('thaiwater', 'water', 'UPSTREAM', 'Sanitized test failure');
        self::assertSame(2, (int) $repository->get('thaiwater')['consecutive_failures']);
        $repository->success('thaiwater', 'water');
        self::assertSame(0, (int) $repository->get('thaiwater')['consecutive_failures']);
    }

    public function testWaterFreshnessUsesConfigThresholds(): void
    {
        Config::overrideForTests(['freshness' => ['water_live_minutes' => 1, 'water_delayed_minutes' => 2]]);
        try {
            $repository = new MeasurementRepository($this->db);
            $repository->store($this->point(1.25), $this->stationId);
            $repository->refreshState($this->stationId, new DateTimeImmutable('2026-08-21 00:01:30', new DateTimeZone('UTC')));
            self::assertSame('delayed', $this->db->query('SELECT freshness_status FROM station_state')->fetchColumn());
            $repository->refreshState($this->stationId, new DateTimeImmutable('2026-08-21 00:03:00', new DateTimeZone('UTC')));
            self::assertSame('stale', $this->db->query('SELECT freshness_status FROM station_state')->fetchColumn());
        } finally {
            Config::overrideForTests(['freshness' => ['water_live_minutes' => 75, 'water_delayed_minutes' => 120]]);
        }
    }

    public function testAlertEscalatesImmediatelyAndClearsAfterTwentyMinutes(): void
    {
        $repository = new AlertRepository($this->db);
        $manager = new AlertManager($this->db, $repository);
        $start = new DateTimeImmutable('2026-08-21 00:00:00', new DateTimeZone('UTC'));
        $manager->reconcile($this->risk('watch'), $start);
        self::assertSame('watch', $repository->active()['severity']);
        $manager->reconcile($this->risk('warning'), $start->modify('+1 minute'));
        self::assertSame('warning', $repository->active()['severity']);
        $manager->reconcile($this->risk('normal'), $start->modify('+2 minutes'));
        self::assertNotNull($repository->active()['pending_since']);
        $manager->reconcile($this->risk('normal'), $start->modify('+21 minutes'));
        self::assertNotNull($repository->active());
        $manager->reconcile($this->risk('normal'), $start->modify('+22 minutes'));
        self::assertNull($repository->active());
    }

    public function testUnknownNeverClearsAnAlert(): void
    {
        $repository = new AlertRepository($this->db);
        $manager = new AlertManager($this->db, $repository);
        $start = new DateTimeImmutable('2026-08-21 00:00:00', new DateTimeZone('UTC'));
        $manager->reconcile($this->risk('warning'), $start);
        $manager->reconcile($this->risk('unknown'), $start->modify('+60 minutes'));
        self::assertSame('warning', $repository->active()['severity']);
    }

    public function testRepeatedAlertStateDoesNotCreateNotificationSpam(): void
    {
        $repository = new AlertRepository($this->db);
        $manager = new AlertManager($this->db, $repository);
        $start = new DateTimeImmutable('2026-08-21 00:00:00', new DateTimeZone('UTC'));
        $manager->reconcile($this->risk('watch'), $start);
        $manager->reconcile($this->risk('watch'), $start->modify('+5 minutes'));
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM alerts')->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM alert_events')->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
    }

    public function testForecastRunDeduplicatesAndStoresAllHorizons(): void
    {
        $repository = new ForecastRepository($this->db);
        $forecast = (new MockWeatherProvider())->fetchForecast($repository->zones());
        self::assertSame('inserted', $repository->storeRun('mock-weather', $forecast)['status']);
        self::assertSame('unchanged', $repository->storeRun('mock-weather', $forecast)['status']);
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM forecast_runs')->fetchColumn());
        self::assertSame(48, (int) $this->db->query('SELECT COUNT(*) FROM weather_forecast_points')->fetchColumn());
        $state = $repository->states()[0];
        foreach ([1, 3, 6, 12, 24, 48] as $hours) {
            self::assertIsNumeric($state['rain_' . $hours . 'h_mm']);
        }
    }

    public function testDashboardDegradesSafelyWithEmptyDatabase(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['weather_state', 'weather_forecast_points', 'forecast_runs', 'forecast_zones', 'station_state',
            'measurement_revisions', 'measurements', 'station_thresholds', 'stations', 'provider_health'] as $table) {
            $this->db->exec('TRUNCATE TABLE ' . $table);
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        $dashboard = (new DashboardService($this->db))->home();
        self::assertNull($dashboard['primary']);
        self::assertSame('unknown', $dashboard['risks']['river']['severity']);
        self::assertSame('unknown', $dashboard['risks']['weather']['severity']);
        self::assertSame('unknown', $dashboard['risks']['combined']['severity']);
    }

    /** @return array<string,mixed> */
    private function point(float $gauge): array
    {
        return ['provider_record_id' => 'test', 'station_code' => 'P.1', 'measured_at' => '2026-08-21 00:00:00',
            'source_measured_at' => '2026-08-21T07:00:00+07:00', 'water_level_gauge_m' => $gauge,
            'water_level_msl_m' => 300.5 + $gauge, 'discharge_m3s' => null, 'rainfall_mm' => null,
            'capacity_percent' => 50.0, 'source_situation' => 'normal', 'source_status' => 'test',
            'raw_payload' => ['level' => $gauge]];
    }

    /** @return array<string,mixed> */
    private function risk(string $severity): array
    {
        return ['severity' => $severity, 'reason_codes' => ['TEST_' . strtoupper($severity)],
            'message_key' => 'risk.combined.' . $severity, 'context' => ['station_ids' => [$this->stationId]]];
    }
}
