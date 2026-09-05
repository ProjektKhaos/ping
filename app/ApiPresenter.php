<?php

declare(strict_types=1);

namespace PingFloodWatch;

final class ApiPresenter
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function station(array $row, string $language): array
    {
        return [
            'code' => (string) $row['provider_station_code'],
            'provider_id' => (string) $row['provider_station_id'],
            'name' => (string) $row['display_name_' . $language],
            'river_name' => (string) $row['river_name_' . $language],
            'latitude' => self::number($row['latitude']), 'longitude' => self::number($row['longitude']),
            'provider' => (string) $row['provider'],
            'is_primary' => (bool) $row['is_primary'], 'river_role' => (string) ($row['river_role'] ?? 'upstream'),
            'gauge_zero_msl_m' => self::number($row['gauge_zero_msl']),
            'bank_level_msl_m' => self::number($row['bank_level_msl']),
            'measurement' => [
                'measured_at' => $row['measured_at'] ?? null,
                'source_measured_at' => $row['source_measured_at'] ?? null,
                'received_at' => $row['received_at'] ?? null,
                'water_level_gauge_m' => self::number($row['water_level_gauge_m'] ?? null),
                'water_level_msl_m' => self::number($row['water_level_msl_m'] ?? null),
                'discharge_m3s' => self::number($row['discharge_m3s'] ?? null),
                'capacity_percent' => self::number($row['capacity_percent'] ?? null),
                'source_situation' => $row['source_situation'] ?? null,
                'freshness' => $row['freshness_status'] ?? 'offline',
                'freshness_label' => t('freshness.' . ($row['freshness_status'] ?? 'offline'), [], $language),
            ],
            'trends' => [
                'change_1h_m' => self::number($row['change_1h_m'] ?? null),
                'change_3h_m' => self::number($row['change_3h_m'] ?? null),
                'change_6h_m' => self::number($row['change_6h_m'] ?? null),
                'change_12h_m' => self::number($row['change_12h_m'] ?? null),
                'change_24h_m' => self::number($row['change_24h_m'] ?? null),
                'rate_1h_m_per_hour' => self::number($row['rate_1h_m_per_hour'] ?? null),
            ],
        ];
    }

    /** @param array<string,mixed> $risk @return array<string,mixed> */
    public static function risk(array $risk, string $language): array
    {
        return [
            'severity' => $risk['severity'], 'reason_codes' => $risk['reason_codes'],
            'severity_label' => t('severity.' . $risk['severity'], [], $language),
            'message_key' => $risk['message_key'], 'message' => t($risk['message_key'], [], $language),
            'values' => $risk['context'], 'calculated_at' => $risk['calculated_at'],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function weatherState(array $row, string $language): array
    {
        $result = [
            'code' => $row['code'], 'name' => $row['display_name_' . $language], 'zone_type' => $row['zone_type'],
            'affects_risk' => (bool) $row['affects_risk'], 'latitude' => self::number($row['latitude']),
            'longitude' => self::number($row['longitude']), 'freshness' => $row['freshness_status'] ?? 'offline',
            'forecast_received_at' => $row['latest_forecast_received_at'] ?? null,
        ];
        foreach ([1, 3, 6, 12, 24, 48] as $hours) {
            $result['rain_' . $hours . 'h_mm'] = self::number($row['rain_' . $hours . 'h_mm'] ?? null);
        }
        $result['max_hourly_rain_mm'] = self::number($row['max_hourly_rain_mm'] ?? null);
        $result['max_probability_pct'] = self::number($row['max_probability_pct'] ?? null);
        return $result;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function alert(array $row, string $language): array
    {
        return [
            'id' => (int) $row['id'], 'severity' => (string) $row['severity'], 'status' => (string) $row['status'],
            'status_label' => t('alerts.' . (string) $row['status'], [], $language),
            'title_key' => (string) $row['title_key'], 'title' => t((string) $row['title_key'], [], $language),
            'message_key' => (string) $row['message_key'], 'message' => t((string) $row['message_key'], [], $language),
            'reason_codes' => $row['reason_codes'] ?? [], 'triggered_at' => $row['triggered_at'],
            'last_seen_at' => $row['last_seen_at'], 'pending_since' => $row['pending_since'],
            'cleared_at' => $row['cleared_at'],
        ];
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
