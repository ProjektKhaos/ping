<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

final class GaugeConverter
{
    public static function fromMsl(?float $msl, mixed $verifiedOffset): ?float
    {
        if ($msl === null || !is_numeric($verifiedOffset)) {
            return null;
        }
        return $msl - (float) $verifiedOffset;
    }
}
