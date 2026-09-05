<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use PingFloodWatch\Config;

final class WarningEngine
{
    /** @param array<string,float|int|null>|null $riseThresholds @param array<string,float|int|null>|null $upstreamThresholds */
    public function __construct(
        private readonly ?array $riseThresholds = null,
        private readonly ?array $upstreamThresholds = null
    ) {
    }

    /**
     * @param list<array<string,mixed>> $stations
     * @param array<string,array<string,mixed>> $thresholds
     * @param array<string,mixed>|null $providerHealth
     * @return array<string,mixed>
     */
    public function evaluate(array $stations, array $thresholds, ?array $providerHealth = null): array
    {
        $primary = null;
        foreach ($stations as $station) {
            if ((int) ($station['is_primary'] ?? 0) === 1) {
                $primary = $station;
                break;
            }
        }
        $base = [
            'severity' => 'unknown', 'reason_codes' => [], 'message_key' => 'risk.river.unknown',
            'context' => ['stations' => [], 'station_ids' => [], 'upstream_trends' => []],
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($primary === null || !is_numeric($primary['water_level_gauge_m'] ?? null)) {
            $base['reason_codes'][] = 'RIVER_PRIMARY_NO_DATA';
            return $base;
        }
        if (!in_array((string) ($primary['freshness_status'] ?? 'offline'), ['live', 'delayed'], true)) {
            $base['reason_codes'][] = 'RIVER_PRIMARY_STALE';
            $base['context']['stations'][] = $primary['provider_station_code'];
            $base['context']['station_ids'][] = (int) $primary['id'];
            return $base;
        }

        $severity = 'normal';
        $reasons = ['RIVER_LEVEL_NORMAL'];
        $affected = [(string) $primary['provider_station_code']];
        $affectedIds = [(int) $primary['id']];
        $upstreamContext = [];
        $level = (float) $primary['water_level_gauge_m'];
        $critical = isset($thresholds['critical']['value_m']) ? (float) $thresholds['critical']['value_m'] : null;
        $warning = isset($thresholds['warning']['value_m']) ? (float) $thresholds['warning']['value_m'] : null;

        if ($critical !== null && $level >= $critical) {
            $severity = 'critical';
            $reasons = ['RIVER_PRIMARY_CRITICAL_LEVEL'];
        } elseif (($warning !== null && $level >= $warning) || ($primary['source_situation'] ?? '') === 'overflow') {
            $severity = 'warning';
            $reasons = [($primary['source_situation'] ?? '') === 'overflow'
                ? 'RIVER_PRIMARY_PROVIDER_OVERFLOW' : 'RIVER_PRIMARY_WARNING_LEVEL'];
        } elseif (($primary['source_situation'] ?? '') === 'high') {
            $severity = 'watch';
            $reasons = ['RIVER_PRIMARY_PROVIDER_HIGH'];
        }

        $watchLimit = $this->upstreamThresholds['watch'] ?? Config::get('alerts.upstream_rise_watch_cm_per_hour');
        $warningLimit = $this->upstreamThresholds['warning'] ?? Config::get('alerts.upstream_rise_warning_cm_per_hour');
        $minimumMulti = max(2, (int) Config::get('alerts.upstream_min_stations_for_multi_rise', 2));
        $crossingCount = 0;
        foreach ($stations as $station) {
            if ((int) ($station['is_primary'] ?? 0) === 1
                || (string) ($station['river_role'] ?? 'upstream') !== 'upstream'
                || !in_array((string) ($station['freshness_status'] ?? ''), ['live', 'delayed'], true)) {
                continue;
            }
            $stationCode = (string) $station['provider_station_code'];
            $stationId = (int) $station['id'];
            $rate = is_numeric($station['rate_1h_m_per_hour'] ?? null)
                ? (float) $station['rate_1h_m_per_hour'] * 100 : null;
            $crossed = null;

            if (in_array((string) ($station['source_situation'] ?? ''), ['high', 'overflow'], true)) {
                if ($this->rank($severity) < $this->rank('watch')) {
                    $severity = 'watch';
                    $reasons = [];
                }
                $reasons[] = ($station['source_situation'] === 'overflow')
                    ? 'RIVER_UPSTREAM_PROVIDER_OVERFLOW' : 'RIVER_UPSTREAM_PROVIDER_HIGH';
                $affected[] = $stationCode;
                $affectedIds[] = $stationId;
            }

            if ($rate !== null && is_numeric($warningLimit) && $rate >= (float) $warningLimit) {
                $crossed = 'warning';
                $crossingCount++;
                if ($this->rank($severity) < $this->rank('warning')) {
                    $severity = 'warning';
                    $reasons = [];
                }
                $reasons[] = 'RIVER_UPSTREAM_RISE_WARNING';
            } elseif ($rate !== null && is_numeric($watchLimit) && $rate >= (float) $watchLimit) {
                $crossed = 'watch';
                $crossingCount++;
                if ($this->rank($severity) < $this->rank('watch')) {
                    $severity = 'watch';
                    $reasons = [];
                }
                $reasons[] = 'RIVER_UPSTREAM_RISE_WATCH';
            }
            if ($crossed !== null) {
                $affected[] = $stationCode;
                $affectedIds[] = $stationId;
                $upstreamContext[] = [
                    'station_code' => $stationCode, 'station_id' => $stationId,
                    'rate_cm_per_hour' => $rate,
                    'change_1h_m' => is_numeric($station['change_1h_m'] ?? null) ? (float) $station['change_1h_m'] : null,
                    'signal' => 'rising_fast', 'threshold' => $crossed,
                ];
            }
        }
        if ($crossingCount >= $minimumMulti) {
            if ($this->rank($severity) < $this->rank('watch')) {
                $severity = 'watch';
                $reasons = [];
            }
            $reasons[] = 'RIVER_UPSTREAM_MULTI_STATION_RISE';
        }

        $primaryRate = is_numeric($primary['rate_1h_m_per_hour'] ?? null)
            ? (float) $primary['rate_1h_m_per_hour'] * 100 : null;
        foreach (['critical', 'warning', 'watch'] as $candidate) {
            $limit = $this->riseThresholds[$candidate] ?? Config::get('alerts.rise_' . $candidate . '_cm_per_hour');
            if ($primaryRate !== null && is_numeric($limit) && $primaryRate >= (float) $limit
                && $this->rank($severity) < $this->rank($candidate)) {
                $severity = $candidate;
                $reasons = ['RIVER_RISE_RATE_' . strtoupper($candidate)];
                break;
            }
        }

        $providerUnhealthy = (int) ($providerHealth['consecutive_failures'] ?? 0) > 0
            || (isset($providerHealth['status']) && $providerHealth['status'] !== 'ok');
        if ($severity === 'normal' && $providerUnhealthy) {
            $severity = 'unknown';
            $reasons = ['RIVER_PROVIDER_FAILURE'];
        }
        $hasUpstreamRise = array_filter(
            $reasons,
            static fn (string $reason): bool => str_starts_with($reason, 'RIVER_UPSTREAM_RISE_')
        ) !== [];
        $messageKey = $hasUpstreamRise && in_array($severity, ['watch', 'warning'], true)
            ? 'risk.river.upstream_' . $severity : 'risk.river.' . $severity;

        return [
            'severity' => $severity,
            'reason_codes' => array_values(array_unique($reasons)),
            'message_key' => $messageKey,
            'context' => [
                'stations' => array_values(array_unique($affected)),
                'station_ids' => array_values(array_unique($affectedIds)),
                'primary_level_m' => $level, 'warning_level_m' => $warning, 'critical_level_m' => $critical,
                'rate_cm_per_hour' => $primaryRate, 'upstream_trends' => $upstreamContext,
            ],
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    private function rank(string $severity): int
    {
        return ['unknown' => -1, 'normal' => 0, 'watch' => 1, 'warning' => 2, 'critical' => 3][$severity] ?? -1;
    }
}
