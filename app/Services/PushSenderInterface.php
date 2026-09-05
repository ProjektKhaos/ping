<?php

declare(strict_types=1);

namespace PingFloodWatch\Services;

interface PushSenderInterface
{
    /** @param array<string,mixed> $subscription @param array<string,mixed> $options */
    public function send(array $subscription, string $payload, array $options): PushDeliveryResult;
}
