<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

interface WeatherProviderInterface
{
    public function getName(): string;

    /** @param array<int,array<string,mixed>> $zones @return array{issued_at:?string,points:array<int,array<string,mixed>>,raw_payload:array<mixed>} */
    public function fetchForecast(array $zones): array;
}

