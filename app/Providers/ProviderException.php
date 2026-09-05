<?php

declare(strict_types=1);

namespace PingFloodWatch\Providers;

final class ProviderException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $providerCode = 'PROVIDER_UNAVAILABLE')
    {
        parent::__construct($message);
    }
}
