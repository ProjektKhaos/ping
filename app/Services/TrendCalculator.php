<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

use DateTimeImmutable;

final class TrendCalculator
{
    /**
     * @param list<array{measured_at:string,value:float|null}> $measurements Newest first.
     */
    public static function change(array $measurements, int $hours, int $toleranceMinutes = 10): ?float
    {
        if ($measurements === [] || !is_numeric($measurements[0]['value'] ?? null)) {
            return null;
        }
        $latestTime = new DateTimeImmutable($measurements[0]['measured_at'] . ' UTC');
        $target = $latestTime->modify(sprintf('-%d hours', $hours));
        $best = null;
        $bestDelta = PHP_INT_MAX;
        foreach (array_slice($measurements, 1) as $point) {
            if (!is_numeric($point['value'] ?? null)) {
                continue;
            }
            $time = new DateTimeImmutable($point['measured_at'] . ' UTC');
            $delta = abs($time->getTimestamp() - $target->getTimestamp());
            if ($delta <= $toleranceMinutes * 60 && $delta < $bestDelta) {
                $best = (float) $point['value'];
                $bestDelta = $delta;
            }
        }
        return $best === null ? null : (float) $measurements[0]['value'] - $best;
    }
}
