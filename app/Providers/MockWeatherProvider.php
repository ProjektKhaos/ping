<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

use DateTimeImmutable;
use DateTimeZone;

final class MockWeatherProvider implements WeatherProviderInterface
{
    public function getName(): string
    {
        return 'mock-weather';
    }

    public function fetchForecast(array $zones): array
    {
        $points = [];
        $start = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTime((int) gmdate('G'), 0);
        foreach ($zones as $zone) {
            for ($hour = 0; $hour < 48; $hour++) {
                $valid = $start->modify('+' . $hour . ' hours');
                $points[] = [
                    'zone_code' => (string) $zone['code'],
                    'valid_from' => $valid->format('Y-m-d H:i:s'),
                    'valid_to' => $valid->modify('+1 hour')->format('Y-m-d H:i:s'),
                    'rainfall_mm' => $hour < 12 ? 1.5 : 0.2,
                    'rainfall_probability_pct' => 70.0,
                    'weather_code' => '61',
                    'source_status' => 'demo',
                ];
            }
        }
        return ['issued_at' => null, 'points' => $points, 'raw_payload' => ['demo' => true]];
    }
}

