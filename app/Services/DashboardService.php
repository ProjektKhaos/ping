<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use PDO;
use PingFloodWatch\Repositories\AlertRepository;
use PingFloodWatch\Repositories\ForecastRepository;
use PingFloodWatch\Repositories\MeasurementRepository;
use PingFloodWatch\Repositories\StationRepository;

final class DashboardService
{
    private MeasurementRepository $measurements;
    private ForecastRepository $forecasts;
    private StationRepository $stations;
    private HealthService $health;

    public function __construct(private readonly PDO $db)
    {
        $this->measurements = new MeasurementRepository($db);
        $this->forecasts = new ForecastRepository($db);
        $this->stations = new StationRepository($db);
        $this->health = new HealthService($db);
    }

    /** @return array<string,mixed> */
    public function home(): array
    {
        $stations = $this->measurements->stationsWithState();
        $primary = $this->stations->primary();
        $thresholds = $primary ? $this->stations->thresholdsForStation((int) $primary['id']) : [];
        $weatherStates = $this->forecasts->states();
        $river = (new WarningEngine())->evaluate($stations, $thresholds, $this->health->provider('water'));
        $weather = (new WeatherRiskEngine())->evaluate($weatherStates, $this->health->provider('weather'));
        $combined = (new AdvisoryEngine())->evaluate($river, $weather);

        return [
            'stations' => $stations,
            'primary' => array_values(array_filter($stations, static fn (array $station): bool => (int) $station['is_primary'] === 1))[0] ?? null,
            'thresholds' => $thresholds,
            'weather_states' => $weatherStates,
            'risks' => ['river' => $river, 'weather' => $weather, 'combined' => $combined],
            'active_alert' => (new AlertRepository($this->db))->active(),
            'generated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function stations(): array
    {
        return $this->measurements->stationsWithState();
    }

    /** @return array<string,mixed>|null */
    public function station(string $code, int $hours = 24): ?array
    {
        $station = $this->measurements->stationWithState($code);
        if ($station === null) {
            return null;
        }
        $station['thresholds'] = $this->stations->thresholdsForStation((int) $station['id']);
        $station['history'] = $this->measurements->history((int) $station['id'], $hours);
        return $station;
    }

    /** @return list<array<string,mixed>> */
    public function weatherStates(): array
    {
        return $this->forecasts->states();
    }

    /** @return list<array<string,mixed>> */
    public function forecast(string $code, int $hours): array
    {
        $zone = $this->forecasts->zoneByCode($code);
        return $zone ? $this->forecasts->forecast((int) $zone['id'], $hours) : [];
    }

    /** @return list<array<string,mixed>> */
    public function alerts(): array
    {
        return (new AlertRepository($this->db))->recent();
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        return $this->health->evaluate();
    }
}
