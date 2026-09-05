<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PingFloodWatch\Config;
use PingFloodWatch\Repositories\ProviderHealthRepository;

final class HealthService
{
    private DateTimeImmutable $now;

    public function __construct(private readonly PDO $db, ?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{status:string,database:string,providers:list<array<string,mixed>>,collectors:list<array<string,mixed>>,generated_at:string} */
    public function evaluate(): array
    {
        $providers = [];
        $collectors = [];
        $degraded = false;
        foreach (['water', 'weather'] as $type) {
            $provider = $this->provider($type);
            $collector = $this->collector($type);
            $providers[] = $provider;
            $collectors[] = $collector;
            $degraded = $degraded || $provider['status'] !== 'ok' || $collector['status'] !== 'ok';
        }
        return [
            'status' => $degraded ? 'degraded' : 'ok',
            'database' => 'ok',
            'providers' => $providers,
            'collectors' => $collectors,
            'generated_at' => $this->now->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string,mixed> */
    public function provider(string $type): array
    {
        $name = (string) Config::get('providers.' . $type, '');
        $row = $name !== '' ? (new ProviderHealthRepository($this->db))->get($name) : null;
        $maxAge = $this->maxAge($type);
        if ($row === null) {
            return ['name' => $name, 'type' => $type, 'status' => 'missing', 'last_success_at' => null,
                'age_minutes' => null, 'consecutive_failures' => 0, 'max_age_minutes' => $maxAge];
        }
        $age = $this->ageMinutes($row['last_success_at'] ?? null);
        $failures = (int) ($row['consecutive_failures'] ?? 0);
        $status = $failures > 0 ? 'failed' : ($age === null ? 'missing' : ($age > $maxAge ? 'stale' : 'ok'));
        return [
            'name' => $name,
            'type' => $type,
            'status' => $status,
            'last_success_at' => $row['last_success_at'] ?? null,
            'last_failure_at' => $row['last_failure_at'] ?? null,
            'age_minutes' => $age,
            'consecutive_failures' => $failures,
            'last_error_code' => $row['last_error_code'] ?? null,
            'max_age_minutes' => $maxAge,
        ];
    }

    /** @return array<string,mixed> */
    public function collector(string $name): array
    {
        $latestStatement = $this->db->prepare('SELECT * FROM collector_runs WHERE collector = :name ORDER BY id DESC LIMIT 1');
        $latestStatement->execute(['name' => $name]);
        $latest = $latestStatement->fetch();
        $maxAge = $this->maxAge($name);
        $maxRuntime = (int) Config::get('health.' . $name . '_collector_max_runtime_minutes', $name === 'water' ? 3 : 5);
        if (!is_array($latest)) {
            return ['name' => $name, 'status' => 'missing', 'last_started_at' => null, 'last_finished_at' => null,
                'age_minutes' => null, 'runtime_minutes' => null, 'max_age_minutes' => $maxAge,
                'max_runtime_minutes' => $maxRuntime];
        }

        $startedAge = $this->ageMinutes($latest['started_at'] ?? null);
        $status = (string) $latest['status'];
        if ($status === 'running') {
            if ($startedAge !== null && $startedAge > $maxRuntime) {
                $healthStatus = 'hung';
            } else {
                $successful = $this->lastSuccessfulCollector($name);
                $successAge = $this->ageMinutes($successful['finished_at'] ?? null);
                $healthStatus = $successAge === null ? 'missing' : ($successAge > $maxAge ? 'stale' : 'ok');
            }
        } elseif ($status === 'failed') {
            $healthStatus = 'failed';
        } else {
            $completedAge = $this->ageMinutes($latest['finished_at'] ?? $latest['started_at']);
            $healthStatus = $completedAge !== null && $completedAge <= $maxAge ? 'ok' : 'stale';
        }

        return [
            'name' => $name,
            'status' => $healthStatus,
            'last_started_at' => $latest['started_at'],
            'last_finished_at' => $latest['finished_at'],
            'age_minutes' => $startedAge,
            'runtime_minutes' => $status === 'running' ? $startedAge : $this->runtimeMinutes($latest),
            'max_age_minutes' => $maxAge,
            'max_runtime_minutes' => $maxRuntime,
            'latest_run_status' => $status,
            'error_code' => $latest['error_code'] ?? null,
        ];
    }

    /** @return array<string,mixed>|null */
    private function lastSuccessfulCollector(string $name): ?array
    {
        $statement = $this->db->prepare(
            "SELECT * FROM collector_runs WHERE collector = :name AND status = 'success' ORDER BY id DESC LIMIT 1"
        );
        $statement->execute(['name' => $name]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private function maxAge(string $type): int
    {
        return (int) Config::get('health.' . $type . '_collector_max_age_minutes', $type === 'water' ? 15 : 45);
    }

    private function ageMinutes(mixed $value): ?float
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            $time = new DateTimeImmutable($value . ' UTC');
            return round(max(0, $this->now->getTimestamp() - $time->getTimestamp()) / 60, 1);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $run */
    private function runtimeMinutes(array $run): ?float
    {
        if (empty($run['started_at']) || empty($run['finished_at'])) {
            return null;
        }
        try {
            $start = new DateTimeImmutable((string) $run['started_at'] . ' UTC');
            $finish = new DateTimeImmutable((string) $run['finished_at'] . ' UTC');
            return round(max(0, $finish->getTimestamp() - $start->getTimestamp()) / 60, 1);
        } catch (\Throwable) {
            return null;
        }
    }
}
