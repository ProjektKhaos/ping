<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use PDO;
use PingFloodWatch\Repositories\AlertRepository;
use PingFloodWatch\Repositories\ForecastRepository;
use PingFloodWatch\Repositories\MeasurementRepository;
use PingFloodWatch\Repositories\RiskStateRepository;
use PingFloodWatch\Repositories\StationRepository;

final class RiskCoordinator
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,array<string,mixed>> */
    public function calculate(): array
    {
        $measurements = new MeasurementRepository($this->db);
        $stations = new StationRepository($this->db);
        $forecasts = new ForecastRepository($this->db);
        $health = new HealthService($this->db);
        $risks = new RiskStateRepository($this->db);

        $stationStates = $measurements->stationsWithState();
        $primary = $stations->primary();
        $thresholds = $primary ? $stations->thresholdsForStation((int) $primary['id']) : [];
        $river = (new WarningEngine())->evaluate($stationStates, $thresholds, $health->provider('water'));
        $weather = (new WeatherRiskEngine())->evaluate($forecasts->states(), $health->provider('weather'));
        $combined = (new AdvisoryEngine())->evaluate($river, $weather);
        $risks->save('river', $river);
        $risks->save('weather', $weather);
        $risks->save('combined', $combined);

        $alerts = new AlertRepository($this->db);
        (new AlertManager($this->db, $alerts))->reconcile($combined);
        return ['river' => $river, 'weather' => $weather, 'combined' => $combined];
    }
}
