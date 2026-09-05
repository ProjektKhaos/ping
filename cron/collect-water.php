#!/usr/bin/env php
<?php

declare(strict_types=1);

use PingFloodWatch\Config;
use PingFloodWatch\Database;
use PingFloodWatch\Logger;
use PingFloodWatch\Providers\CmfloodProvider;
use PingFloodWatch\Providers\MockWaterProvider;
use PingFloodWatch\Providers\ProviderException;
use PingFloodWatch\Providers\ThaiWaterProvider;
use PingFloodWatch\Repositories\MeasurementRepository;
use PingFloodWatch\Repositories\ProviderHealthRepository;
use PingFloodWatch\Repositories\StationRepository;
use PingFloodWatch\Services\CollectorLock;
use PingFloodWatch\Services\CollectorRun;
use PingFloodWatch\Services\RiskCoordinator;

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$lock = new CollectorLock();
if (!$lock->acquire('water-collector')) {
    fwrite(STDERR, "Water collector is already running.\n");
    exit(75);
}

$db = Database::connection();
$run = new CollectorRun($db, 'water');
$health = new ProviderHealthRepository($db);
$stations = new StationRepository($db);
$measurements = new MeasurementRepository($db);
$providerName = (string) Config::get('providers.water', 'thaiwater');
$provider = $providerName === 'mock'
    ? new MockWaterProvider()
    : new ThaiWaterProvider();
$inserted = 0;
$updated = 0;

try {
    $stationRows = $stations->allEnabled();
    $primaryRows = $providerName === 'mock'
        ? $stationRows
        : array_values(array_filter($stationRows, static fn (array $station): bool => $station['provider'] === $providerName));
    $cmfloodRows = $providerName === 'mock'
        ? []
        : array_values(array_filter($stationRows, static fn (array $station): bool => $station['provider'] === 'cmflood'));
    $backfill = 0;
    foreach ($argv as $argument) {
        if (preg_match('/^--backfill=(\d+)h?$/', $argument, $matches)) {
            $backfill = min(720, max(1, (int) $matches[1]));
        }
    }
    $records = [];
    if ($backfill > 0) {
        $to = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $from = $to->modify('-' . $backfill . ' hours');
        foreach ($primaryRows as $station) {
            $records = array_merge($records, $provider->fetchHistory($station, $from, $to));
        }
    }
    $records = array_merge($records, $provider->fetchLatestMeasurements($primaryRows));
    if ($cmfloodRows !== []) {
        $cmflood = new CmfloodProvider();
        try {
            if ($backfill > 0) {
                foreach ($cmfloodRows as $station) {
                    $records = array_merge($records, $cmflood->fetchHistory($station, $from, $to));
                }
            }
            $records = array_merge($records, $cmflood->fetchLatestMeasurements($cmfloodRows));
            $health->success($cmflood->getName(), 'water');
        } catch (Throwable $secondaryError) {
            $secondaryCode = $secondaryError instanceof ProviderException ? $secondaryError->providerCode : 'CMFLOOD_COLLECTOR_FAILED';
            $health->failure($cmflood->getName(), 'water', $secondaryCode, $secondaryError->getMessage());
            Logger::error('cmflood_collector_failed', ['code' => $secondaryCode, 'message' => $secondaryError->getMessage()]);
        }
    }
    $byCode = [];
    foreach ($stationRows as $station) {
        $byCode[(string) $station['provider_station_code']] = $station;
    }
    foreach ($records as $record) {
        $station = $byCode[(string) $record['station_code']] ?? null;
        if ($station === null) {
            continue;
        }
        $status = $measurements->store($record, (int) $station['id']);
        $inserted += $status === 'inserted' ? 1 : 0;
        $updated += $status === 'updated' ? 1 : 0;
    }
    foreach ($stationRows as $station) {
        $measurements->refreshState((int) $station['id']);
    }
    $health->success($provider->getName(), 'water');
    (new RiskCoordinator($db))->calculate();
    $run->finish($inserted, $updated, sprintf('%d records processed.', count($records)));
    Logger::info('water_collector_success', compact('inserted', 'updated'));
    fwrite(STDOUT, sprintf("Water collection complete: %d inserted, %d revised.\n", $inserted, $updated));
} catch (Throwable $error) {
    $code = $error instanceof ProviderException ? $error->providerCode : 'WATER_COLLECTOR_FAILED';
    $health->failure($provider->getName(), 'water', $code, $error->getMessage());
    try {
        foreach ($stations->allEnabled() as $station) {
            $measurements->refreshState((int) $station['id']);
        }
        (new RiskCoordinator($db))->calculate();
    } catch (Throwable $secondary) {
        Logger::error('risk_recalculation_failed', ['message' => $secondary->getMessage()]);
    }
    $run->fail($code, $error->getMessage());
    Logger::error('water_collector_failed', ['code' => $code, 'message' => $error->getMessage()]);
    fwrite(STDERR, $code . ': ' . $error->getMessage() . "\n");
    exit(1);
}
