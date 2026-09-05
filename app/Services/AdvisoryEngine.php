<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

final class AdvisoryEngine
{
    /** @param array<string,mixed> $river @param array<string,mixed> $weather @return array<string,mixed> */
    public function evaluate(array $river, array $weather): array
    {
        $r = (string) $river['severity'];
        $w = (string) $weather['severity'];
        if ($r === 'critical') {
            $severity = 'critical';
            $reasons = ['COMBINED_RIVER_CRITICAL'];
        } elseif ($r === 'unknown' || $w === 'unknown') {
            $severity = 'unknown';
            $reasons = ['COMBINED_INPUT_UNKNOWN'];
        } else {
            $riverRank = ['normal' => 0, 'watch' => 1, 'warning' => 2, 'critical' => 3][$r] ?? 0;
            $weatherRank = ['low' => 0, 'moderate' => 1, 'high' => 2, 'very_high' => 3][$w] ?? 0;
            $rank = max($riverRank, $weatherRank >= 2 ? $weatherRank - 1 : 0);
            if ($riverRank >= 1 && $weatherRank >= 2) {
                $rank = min(3, max($rank, $riverRank + 1));
            }
            $severity = ['normal', 'watch', 'warning', 'critical'][$rank];
            $reasons = ['COMBINED_RIVER_' . strtoupper($r), 'COMBINED_WEATHER_' . strtoupper($w)];
        }
        return [
            'severity' => $severity,
            'reason_codes' => $reasons,
            'message_key' => 'risk.combined.' . $severity,
            'context' => [
                'river_severity' => $r,
                'weather_severity' => $w,
                'stations' => $river['context']['stations'] ?? [],
                'station_ids' => $river['context']['station_ids'] ?? [],
            ],
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
}
