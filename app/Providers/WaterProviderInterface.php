<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

interface WaterProviderInterface
{
    public function getName(): string;

    /** @param array<int,array<string,mixed>> $stations @return array<int,array<string,mixed>> */
    public function fetchLatestMeasurements(array $stations): array;

    /** @param array<string,mixed> $station @return array<int,array<string,mixed>> */
    public function fetchHistory(array $station, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
}

