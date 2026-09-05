<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

final class PushDeliveryResult
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $permanentFailure,
        public readonly ?int $httpStatus = null,
        public readonly string $errorCode = 'OK'
    ) {
    }
}
