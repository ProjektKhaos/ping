<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use PingFloodWatch\Config;

final class WeatherRiskEngine
{
    /** @param list<array<string,mixed>> $states @param array<string,mixed>|null $providerHealth @return array<string,mixed> */
    public function evaluate(array $states, ?array $providerHealth = null): array
    {
        $riskZones = array_values(array_filter($states, static fn (array $state): bool => (int) ($state['affects_risk'] ?? 0) === 1));
        $base = [
            'severity' => 'unknown', 'reason_codes' => [], 'message_key' => 'risk.weather.unknown',
            'context' => ['zones' => [], 'rain_24h_min_mm' => null, 'rain_24h_max_mm' => null,
                'max_hourly_rain_mm' => null, 'max_probability_pct' => null, 'forecast_received_at' => null],
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($riskZones === []) {
            $base['reason_codes'][] = 'WEATHER_NO_ZONES';
            return $base;
        }
        foreach ($riskZones as $state) {
            if (!in_array((string) ($state['freshness_status'] ?? 'offline'), ['current', 'aging'], true)
                || !is_numeric($state['rain_24h_mm'] ?? null)) {
                $base['reason_codes'][] = 'WEATHER_DATA_STALE';
                $base['context']['zones'][] = $state['code'];
                return $base;
            }
        }
        if ((int) ($providerHealth['consecutive_failures'] ?? 0) > 0
            || (isset($providerHealth['status']) && $providerHealth['status'] !== 'ok')) {
            $base['reason_codes'][] = 'WEATHER_PROVIDER_FAILURE';
            $base['context']['zones'] = array_column($riskZones, 'code');
            return $base;
        }

        $rain24 = array_map(static fn (array $state): float => (float) $state['rain_24h_mm'], $riskZones);
        $hourly = array_map(static fn (array $state): float => (float) ($state['max_hourly_rain_mm'] ?? 0), $riskZones);
        $probabilities = array_map(static fn (array $state): float => (float) ($state['max_probability_pct'] ?? 0), $riskZones);
        $rainSeverity = $this->classify(max($rain24), Config::get('weather_risk.24h'));
        $hourlySeverity = $this->classify(max($hourly), Config::get('weather_risk.hourly'));
        $severity = $this->rank($rainSeverity) >= $this->rank($hourlySeverity) ? $rainSeverity : $hourlySeverity;
        $reason = $this->rank($rainSeverity) >= $this->rank($hourlySeverity)
            ? 'WEATHER_24H_' . strtoupper($rainSeverity)
            : 'WEATHER_HOURLY_' . strtoupper($hourlySeverity);

        $received = array_values(array_filter(array_column($riskZones, 'latest_forecast_received_at')));
        $context = [
            'zones' => array_column($riskZones, 'code'),
            'max_hourly_rain_mm' => max($hourly),
            'max_probability_pct' => max($probabilities),
            'forecast_received_at' => $received === [] ? null : max($received),
        ];
        foreach ([1, 3, 6, 12, 24, 48] as $hours) {
            $values = array_values(array_filter(array_map(
                static fn (array $state): ?float => is_numeric($state['rain_' . $hours . 'h_mm'] ?? null)
                    ? (float) $state['rain_' . $hours . 'h_mm'] : null,
                $riskZones
            ), static fn (?float $value): bool => $value !== null));
            $context['rain_' . $hours . 'h_min_mm'] = count($values) === count($riskZones) ? min($values) : null;
            $context['rain_' . $hours . 'h_max_mm'] = count($values) === count($riskZones) ? max($values) : null;
        }

        return [
            'severity' => $severity,
            'reason_codes' => [$reason],
            'message_key' => 'risk.weather.' . $severity,
            'context' => $context,
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @param array<string,float|int> $thresholds */
    private function classify(float $value, array $thresholds): string
    {
        if ($value >= (float) $thresholds['very_high']) {
            return 'very_high';
        }
        if ($value >= (float) $thresholds['high']) {
            return 'high';
        }
        if ($value >= (float) $thresholds['moderate']) {
            return 'moderate';
        }
        return 'low';
    }

    private function rank(string $severity): int
    {
        return ['unknown' => -1, 'low' => 0, 'moderate' => 1, 'high' => 2, 'very_high' => 3][$severity] ?? -1;
    }
}
